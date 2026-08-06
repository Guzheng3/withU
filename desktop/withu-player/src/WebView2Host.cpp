#include "WebView2Host.h"

#include <QDir>
#include <QFileInfo>
#include <QLibrary>
#include <QJsonDocument>
#include <QList>
#include <QMetaObject>
#include <QStandardPaths>
#include <QShowEvent>

#include <windows.h>

#include <functional>

namespace {

using CreateEnvironmentWithOptions = HRESULT(WINAPI *)(
    PCWSTR,
    PCWSTR,
    ICoreWebView2EnvironmentOptions *,
    ICoreWebView2CreateCoreWebView2EnvironmentCompletedHandler *);

QString wideStringAndFree(LPWSTR value)
{
    if (!value) return {};
    const QString result = QString::fromWCharArray(value);
    CoTaskMemFree(value);
    return result;
}

template <typename Interface>
bool supportsInterface(REFIID requested, REFIID expected, void **object, Interface *self)
{
    if (IsEqualIID(requested, IID_IUnknown) || IsEqualIID(requested, expected)) {
        *object = self;
        self->AddRef();
        return true;
    }
    *object = nullptr;
    return false;
}

class EnvironmentCompletedHandler final : public ICoreWebView2CreateCoreWebView2EnvironmentCompletedHandler
{
public:
    explicit EnvironmentCompletedHandler(std::function<void(HRESULT, ICoreWebView2Environment *)> callback)
        : m_callback(std::move(callback))
    {
    }

    HRESULT STDMETHODCALLTYPE QueryInterface(REFIID riid, void **object) override
    {
        if (!object) return E_POINTER;
        if (supportsInterface(riid, IID_ICoreWebView2CreateCoreWebView2EnvironmentCompletedHandler, object, this)) {
            return S_OK;
        }
        return E_NOINTERFACE;
    }

    ULONG STDMETHODCALLTYPE AddRef() override { return ++m_refs; }

    ULONG STDMETHODCALLTYPE Release() override
    {
        const ULONG refs = --m_refs;
        if (!refs) delete this;
        return refs;
    }

    HRESULT STDMETHODCALLTYPE Invoke(HRESULT errorCode, ICoreWebView2Environment *result) override
    {
        if (m_callback) m_callback(errorCode, result);
        return S_OK;
    }

private:
    std::atomic<ULONG> m_refs{1};
    std::function<void(HRESULT, ICoreWebView2Environment *)> m_callback;
};

class ControllerCompletedHandler final : public ICoreWebView2CreateCoreWebView2ControllerCompletedHandler
{
public:
    explicit ControllerCompletedHandler(std::function<void(HRESULT, ICoreWebView2Controller *)> callback)
        : m_callback(std::move(callback))
    {
    }

    HRESULT STDMETHODCALLTYPE QueryInterface(REFIID riid, void **object) override
    {
        if (!object) return E_POINTER;
        if (supportsInterface(riid, IID_ICoreWebView2CreateCoreWebView2ControllerCompletedHandler, object, this)) {
            return S_OK;
        }
        return E_NOINTERFACE;
    }

    ULONG STDMETHODCALLTYPE AddRef() override { return ++m_refs; }

    ULONG STDMETHODCALLTYPE Release() override
    {
        const ULONG refs = --m_refs;
        if (!refs) delete this;
        return refs;
    }

    HRESULT STDMETHODCALLTYPE Invoke(HRESULT errorCode, ICoreWebView2Controller *result) override
    {
        if (m_callback) m_callback(errorCode, result);
        return S_OK;
    }

private:
    std::atomic<ULONG> m_refs{1};
    std::function<void(HRESULT, ICoreWebView2Controller *)> m_callback;
};

class NavigationStartingHandler final : public ICoreWebView2NavigationStartingEventHandler
{
public:
    explicit NavigationStartingHandler(std::function<void(ICoreWebView2NavigationStartingEventArgs *)> callback)
        : m_callback(std::move(callback))
    {
    }

    HRESULT STDMETHODCALLTYPE QueryInterface(REFIID riid, void **object) override
    {
        if (!object) return E_POINTER;
        if (supportsInterface(riid, IID_ICoreWebView2NavigationStartingEventHandler, object, this)) {
            return S_OK;
        }
        return E_NOINTERFACE;
    }

    ULONG STDMETHODCALLTYPE AddRef() override { return ++m_refs; }

    ULONG STDMETHODCALLTYPE Release() override
    {
        const ULONG refs = --m_refs;
        if (!refs) delete this;
        return refs;
    }

    HRESULT STDMETHODCALLTYPE Invoke(ICoreWebView2 *, ICoreWebView2NavigationStartingEventArgs *args) override
    {
        if (m_callback) m_callback(args);
        return S_OK;
    }

private:
    std::atomic<ULONG> m_refs{1};
    std::function<void(ICoreWebView2NavigationStartingEventArgs *)> m_callback;
};

class WebMessageReceivedHandler final : public ICoreWebView2WebMessageReceivedEventHandler
{
public:
    explicit WebMessageReceivedHandler(std::function<void(ICoreWebView2WebMessageReceivedEventArgs *)> callback)
        : m_callback(std::move(callback))
    {
    }

    HRESULT STDMETHODCALLTYPE QueryInterface(REFIID riid, void **object) override
    {
        if (!object) return E_POINTER;
        if (supportsInterface(riid, IID_ICoreWebView2WebMessageReceivedEventHandler, object, this)) {
            return S_OK;
        }
        return E_NOINTERFACE;
    }

    ULONG STDMETHODCALLTYPE AddRef() override { return ++m_refs; }

    ULONG STDMETHODCALLTYPE Release() override
    {
        const ULONG refs = --m_refs;
        if (!refs) delete this;
        return refs;
    }

    HRESULT STDMETHODCALLTYPE Invoke(ICoreWebView2 *, ICoreWebView2WebMessageReceivedEventArgs *args) override
    {
        if (m_callback) m_callback(args);
        return S_OK;
    }

private:
    std::atomic<ULONG> m_refs{1};
    std::function<void(ICoreWebView2WebMessageReceivedEventArgs *)> m_callback;
};

}

WebView2Host::WebView2Host(QWidget *parent)
    : QWidget(parent)
{
    setAttribute(Qt::WA_NativeWindow);
    setAttribute(Qt::WA_OpaquePaintEvent);
    setAutoFillBackground(true);
    QPalette palette = this->palette();
    palette.setColor(QPalette::Window, QColor(QStringLiteral("#0b0f14")));
    setPalette(palette);
}

WebView2Host::~WebView2Host()
{
    releaseWebView();
    if (m_loader) {
        if (m_loader->isLoaded()) m_loader->unload();
        delete m_loader;
        m_loader = nullptr;
    }
}

void WebView2Host::showEvent(QShowEvent *event)
{
    QWidget::showEvent(event);
    initialize();
}

void WebView2Host::resizeEvent(QResizeEvent *event)
{
    QWidget::resizeEvent(event);
    updateBounds();
}

void WebView2Host::initialize()
{
    if (m_initializationStarted) return;
    m_initializationStarted = true;
    if (m_pendingUrl.isEmpty()) m_pendingUrl = QUrl(QStringLiteral("http://127.0.0.1:8080/"));

    m_loader = new QLibrary(QStringLiteral("WebView2Loader.dll"), this);
    if (!m_loader->load()) {
        emitFailure(QStringLiteral("WebView2Loader.dll 加载失败：%1").arg(m_loader->errorString()));
        return;
    }
    auto createEnvironment = reinterpret_cast<CreateEnvironmentWithOptions>(
        m_loader->resolve("CreateCoreWebView2EnvironmentWithOptions"));
    if (!createEnvironment) {
        emitFailure(QStringLiteral("WebView2Loader.dll 缺少创建环境入口"));
        return;
    }

    const QString appData = QStandardPaths::writableLocation(QStandardPaths::AppLocalDataLocation);
    const QString userData = QDir(appData).filePath(QStringLiteral("WebView2"));
    QDir().mkpath(userData);
    const std::wstring userDataWide = userData.toStdWString();
    auto *handler = new EnvironmentCompletedHandler([this](HRESULT errorCode, ICoreWebView2Environment *environment) {
        if (FAILED(errorCode) || !environment) {
            emitFailure(QStringLiteral("WebView2 环境创建失败：0x%1").arg(QString::number(static_cast<quint32>(errorCode), 16)));
            return;
        }
        m_environment = environment;
        createController(environment);
    });
    const HRESULT result = createEnvironment(nullptr, userDataWide.c_str(), nullptr, handler);
    handler->Release();
    if (FAILED(result)) {
        emitFailure(QStringLiteral("WebView2 环境请求失败：0x%1").arg(QString::number(static_cast<quint32>(result), 16)));
    }
}

void WebView2Host::createController(ICoreWebView2Environment *environment)
{
    auto *handler = new ControllerCompletedHandler([this](HRESULT errorCode, ICoreWebView2Controller *controller) {
        if (FAILED(errorCode) || !controller) {
            emitFailure(QStringLiteral("WebView2 控制器创建失败：0x%1").arg(QString::number(static_cast<quint32>(errorCode), 16)));
            return;
        }
        m_controller = controller;
        m_controller->put_IsVisible(TRUE);
        updateBounds();
        if (FAILED(m_controller->get_CoreWebView2(&m_webView)) || !m_webView) {
            emitFailure(QStringLiteral("WebView2 核心对象创建失败"));
            return;
        }

        auto *navigationHandler = new NavigationStartingHandler([this](ICoreWebView2NavigationStartingEventArgs *args) {
            if (!args) return;
            LPWSTR uri = nullptr;
            if (SUCCEEDED(args->get_Uri(&uri))) {
                const QUrl navigationUrl(wideStringAndFree(uri));
                if (!isAllowedNavigation(navigationUrl)) {
                    args->put_Cancel(TRUE);
                    return;
                }
                emit navigationStarting(navigationUrl);
            }
        });
        m_webView->add_NavigationStarting(navigationHandler, &m_navigationToken);
        navigationHandler->Release();

        auto *messageHandler = new WebMessageReceivedHandler([this](ICoreWebView2WebMessageReceivedEventArgs *args) {
            if (!args) return;
            // Navigation is limited to WithU, but WebView2 can also surface
            // messages from embedded frames. Verify the message source as
            // well so an external frame cannot control the native libmpv
            // bridge even when it is rendered inside an otherwise trusted
            // page.
            LPWSTR source = nullptr;
            if (FAILED(args->get_Source(&source))) return;
            const QUrl sourceUrl(wideStringAndFree(source));
            if (!isAllowedNavigation(sourceUrl)) return;
            LPWSTR json = nullptr;
            if (FAILED(args->get_WebMessageAsJson(&json))) return;
            // JSON from WebView2 is UTF-16. Convert directly to UTF-8 rather
            // than through the local Windows code page so Chinese titles and
            // UI messages remain lossless on every physical machine.
            const QByteArray bytes = QString::fromWCharArray(json).toUtf8();
            CoTaskMemFree(json);
            QJsonParseError error{};
            const QJsonDocument document = QJsonDocument::fromJson(bytes, &error);
            if (error.error == QJsonParseError::NoError && document.isObject()) {
                emit webMessageReceived(document.object());
            }
        });
        m_webView->add_WebMessageReceived(messageHandler, &m_messageToken);
        messageHandler->Release();

        const std::wstring url = m_pendingUrl.toString(QUrl::FullyEncoded).toStdWString();
        m_webView->Navigate(url.c_str());
        m_initialized = true;
        emit ready();
    });
    const HRESULT result = environment->CreateCoreWebView2Controller(reinterpret_cast<HWND>(winId()), handler);
    handler->Release();
    if (FAILED(result)) {
        emitFailure(QStringLiteral("WebView2 控制器请求失败：0x%1").arg(QString::number(static_cast<quint32>(result), 16)));
    }
}

void WebView2Host::updateBounds()
{
    if (!m_controller) return;
    RECT bounds{0, 0, width(), height()};
    m_controller->put_Bounds(bounds);
    m_controller->NotifyParentWindowPositionChanged();
}

void WebView2Host::releaseWebView()
{
    if (m_webView) {
        if (m_navigationToken.value) m_webView->remove_NavigationStarting(m_navigationToken);
        if (m_messageToken.value) m_webView->remove_WebMessageReceived(m_messageToken);
        m_webView->Release();
        m_webView = nullptr;
    }
    if (m_controller) {
        m_controller->Close();
        m_controller->Release();
        m_controller = nullptr;
    }
    if (m_environment) {
        m_environment->Release();
        m_environment = nullptr;
    }
}

void WebView2Host::navigate(const QUrl &url)
{
    m_pendingUrl = url;
    if (!m_webView) return;
    const std::wstring value = url.toString(QUrl::FullyEncoded).toStdWString();
    m_webView->Navigate(value.c_str());
}

void WebView2Host::setAllowedOrigin(const QUrl &url)
{
    m_allowedOrigin = (url.isValid() && !url.host().isEmpty()) ? url : QUrl();
}

bool WebView2Host::isAllowedNavigation(const QUrl &url) const
{
    if (!m_allowedOrigin.isValid() || m_allowedOrigin.host().isEmpty()) {
        return true;
    }
    if (!url.isValid() || url.host().isEmpty()) {
        return false;
    }
    const auto defaultPort = [](const QUrl &value) {
        return value.port(value.scheme().compare(QStringLiteral("https"), Qt::CaseInsensitive) == 0 ? 443 : 80);
    };
    return url.scheme().compare(m_allowedOrigin.scheme(), Qt::CaseInsensitive) == 0
        && url.host().compare(m_allowedOrigin.host(), Qt::CaseInsensitive) == 0
        && defaultPort(url) == defaultPort(m_allowedOrigin);
}

void WebView2Host::postJson(const QJsonObject &message)
{
    if (!m_webView) return;
    const std::wstring json = QString::fromUtf8(QJsonDocument(message).toJson(QJsonDocument::Compact)).toStdWString();
    m_webView->PostWebMessageAsJson(json.c_str());
}

void WebView2Host::setCookies(const QUrl &url, const QList<QNetworkCookie> &cookies)
{
    if (!m_webView || !url.isValid() || url.host().isEmpty()) return;

    ICoreWebView2_2 *extended = nullptr;
    if (FAILED(m_webView->QueryInterface(IID_ICoreWebView2_2, reinterpret_cast<void **>(&extended))) || !extended) return;
    ICoreWebView2CookieManager *manager = nullptr;
    if (FAILED(extended->get_CookieManager(&manager)) || !manager) {
        extended->Release();
        return;
    }

    const QString domain = url.host();
    const QString pathFallback = QStringLiteral("/");
    for (const QNetworkCookie &cookie : cookies) {
        const QString name = QString::fromUtf8(cookie.name());
        const QString value = QString::fromUtf8(cookie.value());
        if (name.isEmpty()) continue;

        const QString cookieDomain = cookie.domain().isEmpty() ? domain : cookie.domain();
        const QString cookiePath = cookie.path().isEmpty() ? pathFallback : cookie.path();
        const std::wstring nameWide = name.toStdWString();
        const std::wstring valueWide = value.toStdWString();
        const std::wstring domainWide = cookieDomain.toStdWString();
        const std::wstring pathWide = cookiePath.toStdWString();

        ICoreWebView2Cookie *webCookie = nullptr;
        if (FAILED(manager->CreateCookie(nameWide.c_str(), valueWide.c_str(), domainWide.c_str(), pathWide.c_str(), &webCookie)) || !webCookie) {
            continue;
        }
        webCookie->put_IsHttpOnly(cookie.isHttpOnly() ? TRUE : FALSE);
        webCookie->put_IsSecure(url.scheme().compare(QStringLiteral("https"), Qt::CaseInsensitive) == 0 ? TRUE : FALSE);
        if (cookie.expirationDate().isValid()) {
            const QDateTime expiration = cookie.expirationDate().toUTC();
            webCookie->put_Expires(expiration.toMSecsSinceEpoch() / 1000.0);
        }
        manager->AddOrUpdateCookie(webCookie);
        webCookie->Release();
    }
    manager->Release();
    extended->Release();
}

void WebView2Host::emitFailure(const QString &message)
{
    QMetaObject::invokeMethod(this, [this, message]() { emit failed(message); }, Qt::QueuedConnection);
}
