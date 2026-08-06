#pragma once

#include <QJsonObject>
#include <QNetworkCookie>
#include <QUrl>
#include <QWidget>

#include <WebView2.h>

#include <atomic>

class QLibrary;

class WebView2Host final : public QWidget
{
    Q_OBJECT

public:
    explicit WebView2Host(QWidget *parent = nullptr);
    ~WebView2Host() override;

    void navigate(const QUrl &url);
    void setAllowedOrigin(const QUrl &url);
    void postJson(const QJsonObject &message);
    void setCookies(const QUrl &url, const QList<QNetworkCookie> &cookies);
    bool isReady() const { return m_webView != nullptr; }

signals:
    void ready();
    void failed(const QString &message);
    void navigationStarting(const QUrl &url);
    void webMessageReceived(const QJsonObject &message);

protected:
    void showEvent(QShowEvent *event) override;
    void resizeEvent(QResizeEvent *event) override;

private:
    void initialize();
    void createController(ICoreWebView2Environment *environment);
    void updateBounds();
    bool isAllowedNavigation(const QUrl &url) const;
    void releaseWebView();
    void emitFailure(const QString &message);

    QLibrary *m_loader = nullptr;
    ICoreWebView2Environment *m_environment = nullptr;
    ICoreWebView2Controller *m_controller = nullptr;
    ICoreWebView2 *m_webView = nullptr;
    EventRegistrationToken m_navigationToken{};
    EventRegistrationToken m_messageToken{};
    bool m_initialized = false;
    bool m_initializationStarted = false;
    QUrl m_pendingUrl;
    QUrl m_allowedOrigin;
};
