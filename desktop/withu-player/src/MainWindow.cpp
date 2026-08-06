#include "MainWindow.h"

#include "WebView2Host.h"
#include <QAudioOutput>
#include <QApplication>
#include <QButtonGroup>
#include <QColor>
#include <QDoubleSpinBox>
#include <QFileDialog>
#include <QFrame>
#include <QGridLayout>
#include <QGraphicsDropShadowEffect>
#include <QHBoxLayout>
#include <QIcon>
#include <QLabel>
#include <QLibrary>
#include <QLineEdit>
#include <QListWidget>
#include <QListWidgetItem>
#include <QListView>
#include <QLinearGradient>
#include <QDateTime>
#include <QDesktopServices>
#include <QDir>
#include <QFileInfo>
#include <QRegularExpression>
#include <QScrollArea>
#include <QJsonArray>
#include <QJsonDocument>
#include <QJsonObject>
#include <QMediaPlayer>
#include <QNetworkCookie>
#include <QNetworkCookieJar>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QPalette>
#include <QPainter>
#include <QPainterPath>
#include <QPolygon>
#include <QPaintEvent>
#include <QPair>
#include <QList>
#include <QPixmap>
#include <QSettings>
#include <QPushButton>
#include <QResizeEvent>
#include <QSlider>
#include <QStackedWidget>
#include <QStatusBar>
#include <QUrl>
#include <QUrlQuery>
#include <QTimer>
#include <QTimerEvent>
#include <QTextBrowser>
#include <QVBoxLayout>
#include <QVideoWidget>
#include <QIODevice>
#include <QSharedPointer>

#include <mpv/client.h>

#include <cmath>
#include <functional>
#include <limits>

namespace {

using MpvCreate = mpv_handle *(*)();
using MpvInitialize = int (*)(mpv_handle *);
using MpvSetOptionString = int (*)(mpv_handle *, const char *, const char *);
using MpvCommand = int (*)(mpv_handle *, const char *const *);
using MpvSetProperty = int (*)(mpv_handle *, const char *, mpv_format, void *);
using MpvGetProperty = int (*)(mpv_handle *, const char *, mpv_format, void *);
using MpvWaitEvent = mpv_event *(*)(mpv_handle *, double);
using MpvTerminateDestroy = void (*)(mpv_handle *);

struct EmbeddedMpvApi {
    MpvCreate create = nullptr;
    MpvInitialize initialize = nullptr;
    MpvSetOptionString setOptionString = nullptr;
    MpvCommand command = nullptr;
    MpvSetProperty setProperty = nullptr;
    MpvGetProperty getProperty = nullptr;
    MpvWaitEvent waitEvent = nullptr;
    MpvTerminateDestroy terminateDestroy = nullptr;
};

EmbeddedMpvApi g_embeddedMpvApi;

template <typename T>
T resolveMpv(QLibrary *library, const char *name)
{
    return reinterpret_cast<T>(library ? library->resolve(name) : nullptr);
}

QString sourceDisplayName(const QUrl &source)
{
    if (source.isLocalFile()) {
        return source.toLocalFile();
    }
    return source.toString(QUrl::FullyEncoded);
}

QLabel *mutedLabel(const QString &text, QWidget *parent)
{
    auto *label = new QLabel(text, parent);
    label->setWordWrap(true);
    label->setObjectName(QStringLiteral("mutedLabel"));
    return label;
}

// A small native Qt version of LikeGirl's heartbeat mark. Drawing it instead
// of using an emoji keeps the shape, color and animation consistent across
// Windows font installations.
class HeartPulseWidget final : public QWidget
{
public:
    explicit HeartPulseWidget(QWidget *parent = nullptr)
        : QWidget(parent)
    {
        setAttribute(Qt::WA_TransparentForMouseEvents);
        startTimer(30);
    }

protected:
    void timerEvent(QTimerEvent *event) override
    {
        QWidget::timerEvent(event);
        m_phase += 0.105;
        if (m_phase > 6.283185307) m_phase -= 6.283185307;
        update();
    }

    void paintEvent(QPaintEvent *) override
    {
        QPainter painter(this);
        painter.setRenderHint(QPainter::Antialiasing, true);
        const qreal unit = qMin(width(), height()) / 100.0;
        if (unit <= 0) return;
        painter.translate(width() / 2.0, height() / 2.0);
        const qreal beat = (std::sin(m_phase) + 1.0) / 2.0;
        const qreal scale = unit * (0.86 + beat * 0.14);
        painter.scale(scale, scale);

        QPen rayPen(QColor(105, 224, 232, 190), 3.2, Qt::SolidLine, Qt::RoundCap);
        painter.setPen(rayPen);
        const qreal rayRadius = 40.0 + beat * 2.0;
        for (int i = 0; i < 8; ++i) {
            const qreal angle = i * 0.7853981634;
            const QPointF start(std::cos(angle) * rayRadius, std::sin(angle) * rayRadius);
            const QPointF end(std::cos(angle) * (rayRadius + 8.0 + beat * 3.0), std::sin(angle) * (rayRadius + 8.0 + beat * 3.0));
            painter.drawLine(start, end);
        }

        QPainterPath heart;
        heart.moveTo(0, 30);
        heart.cubicTo(-9, 20, -31, 4, -31, -13);
        heart.cubicTo(-31, -27, -13, -35, 0, -22);
        heart.cubicTo(13, -35, 31, -27, 31, -13);
        heart.cubicTo(31, 4, 9, 20, 0, 30);
        heart.closeSubpath();
        painter.setPen(Qt::NoPen);
        painter.setBrush(QColor(247, 91, 116));
        painter.drawPath(heart);
    }

private:
    qreal m_phase = 0;
};

class LoveCounterPanel final : public QFrame
{
public:
    explicit LoveCounterPanel(QWidget *parent = nullptr)
        : QFrame(parent)
    {
        setAttribute(Qt::WA_StyledBackground, false);
    }

protected:
    void paintEvent(QPaintEvent *event) override
    {
        Q_UNUSED(event);
        QPainter painter(this);
        painter.setRenderHint(QPainter::Antialiasing, true);
        const QRectF panel = rect().adjusted(0.5, 0.5, -0.5, -0.5);
        painter.setPen(QPen(QColor(255, 255, 255, 210), 1));
        painter.setBrush(QColor(255, 255, 255, 224));
        painter.drawRoundedRect(panel, 32, 32);

    }
};

class GradientTextLabel final : public QLabel
{
public:
    explicit GradientTextLabel(QWidget *parent = nullptr)
        : QLabel(parent)
    {
        setAttribute(Qt::WA_TranslucentBackground);
        startTimer(30);
    }

protected:
    void timerEvent(QTimerEvent *event) override
    {
        QLabel::timerEvent(event);
        m_offset -= 1.2;
        if (m_offset < -2400.0) m_offset += 2400.0;
        update();
    }

    void paintEvent(QPaintEvent *) override
    {
        QPainter painter(this);
        painter.setRenderHint(QPainter::TextAntialiasing, true);
        // LikeGirl's jianbian animation continuously moves a long rainbow
        // gradient underneath the glyphs instead of swapping colors abruptly.
        QLinearGradient gradient(m_offset, 0, m_offset + 2400.0, 0);
        const QList<QPair<qreal, QColor>> stops = {
            {0.00, QColor("#ff4500")}, {0.12, QColor("#ffa500")},
            {0.24, QColor("#ffd700")}, {0.38, QColor("#90ee90")},
            {0.50, QColor("#00cfd8")}, {0.64, QColor("#1e90ff")},
            {0.76, QColor("#9370db")}, {0.88, QColor("#ff69b4")},
            {1.00, QColor("#ff4500")}
        };
        for (const auto &stop : stops) gradient.setColorAt(stop.first, stop.second);
        painter.setPen(QPen(QBrush(gradient), 0));
        painter.setFont(font());
        painter.drawText(rect(), alignment(), text());
    }

private:
    qreal m_offset = 0.0;
};

QFrame *glassCard(QWidget *parent)
{
    auto *card = new QFrame(parent);
    card->setObjectName(QStringLiteral("glassCard"));
    auto *layout = new QVBoxLayout(card);
    layout->setContentsMargins(18, 18, 18, 18);
    layout->setSpacing(10);
    return card;
}

QString qssRgb(const QColor &color)
{
    return color.name(QColor::HexRgb);
}

QString qssRgba(const QColor &color, int alpha)
{
    return QStringLiteral("rgba(%1,%2,%3,%4)")
        .arg(color.red())
        .arg(color.green())
        .arg(color.blue())
        .arg(qBound(0, alpha, 255));
}

void addMetric(QGridLayout *grid, int row, int column, const QString &value, const QString &label, QLabel **valueOut = nullptr)
{
    auto *wrap = new QFrame;
    wrap->setObjectName(QStringLiteral("metricCard"));
    auto *layout = new QVBoxLayout(wrap);
    layout->setContentsMargins(14, 12, 14, 12);
    auto *valueLabel = new QLabel(value, wrap);
    valueLabel->setObjectName(QStringLiteral("metricValue"));
    auto *textLabel = mutedLabel(label, wrap);
    layout->addWidget(valueLabel);
    layout->addWidget(textLabel);
    grid->addWidget(wrap, row, column);
    if (valueOut) {
        *valueOut = valueLabel;
    }
}

class PersistentCookieJar final : public QNetworkCookieJar
{
public:
    explicit PersistentCookieJar(QObject *parent = nullptr)
        : QNetworkCookieJar(parent)
    {
    }

    void load()
    {
        QSettings settings(QStringLiteral("withU"), QStringLiteral("withU Desktop"));
        QList<QNetworkCookie> cookies;
        const int size = settings.beginReadArray(QStringLiteral("cookies"));
        for (int i = 0; i < size; ++i) {
            settings.setArrayIndex(i);
            const QByteArray raw = settings.value(QStringLiteral("raw")).toByteArray();
            const QList<QNetworkCookie> parsed = QNetworkCookie::parseCookies(raw);
            if (parsed.isEmpty()) {
                continue;
            }
            const QNetworkCookie cookie = parsed.first();
            if (!cookie.expirationDate().isValid() || cookie.expirationDate() > QDateTime::currentDateTimeUtc()) {
                cookies.append(cookie);
            }
        }
        settings.endArray();
        setAllCookies(cookies);
    }

    void save() const
    {
        QSettings settings(QStringLiteral("withU"), QStringLiteral("withU Desktop"));
        const QList<QNetworkCookie> cookies = allCookies();
        settings.beginWriteArray(QStringLiteral("cookies"));
        for (int i = 0; i < cookies.size(); ++i) {
            settings.setArrayIndex(i);
            settings.setValue(QStringLiteral("raw"), cookies.at(i).toRawForm(QNetworkCookie::Full));
        }
        settings.endArray();
        settings.sync();
    }

    void clear()
    {
        setAllCookies({});
        save();
    }

    QList<QNetworkCookie> cookies() const { return allCookies(); }
};

class WithuNetworkAccessManager final : public QNetworkAccessManager
{
public:
    explicit WithuNetworkAccessManager(QObject *parent = nullptr)
        : QNetworkAccessManager(parent)
    {
    }

protected:
    QNetworkReply *createRequest(Operation operation, const QNetworkRequest &request, QIODevice *outgoingData = nullptr) override
    {
        QNetworkReply *reply = QNetworkAccessManager::createRequest(operation, request, outgoingData);
        auto *timeout = new QTimer(reply);
        timeout->setSingleShot(true);
        connect(timeout, &QTimer::timeout, reply, &QNetworkReply::abort);
        connect(reply, &QNetworkReply::finished, timeout, &QTimer::deleteLater);
        timeout->start(15000);
        return reply;
    }
};

void savePersistentCookies(QNetworkAccessManager *network)
{
    if (!network || !network->cookieJar()) {
        return;
    }
    if (auto *jar = dynamic_cast<PersistentCookieJar *>(network->cookieJar())) {
        jar->save();
    }
}

} // namespace

MainWindow::MainWindow(QWidget *parent)
    : QMainWindow(parent),
      m_player(new QMediaPlayer(this)),
      m_audioOutput(new QAudioOutput(this)),
      m_network(new WithuNetworkAccessManager(this)),
      m_mpvPollTimer(new QTimer(this))
{
    auto *cookieJar = new PersistentCookieJar(this);
    cookieJar->load();
    m_network->setCookieJar(cookieJar);

    m_player->setAudioOutput(m_audioOutput);
    m_audioOutput->setVolume(0.8);
    m_loveCounterTimer = new QTimer(this);
    m_loveCounterTimer->setInterval(1000);
    connect(m_loveCounterTimer, &QTimer::timeout, this, &MainWindow::updateLoveCounter);
    m_loveCounterTimer->start();
    m_mpvPollTimer->setInterval(250);
    connect(m_mpvPollTimer, &QTimer::timeout, this, &MainWindow::pollMpvPlayback);
    buildUi();
    m_watchPollTimer = new QTimer(this);
    m_watchPollTimer->setInterval(500);
    connect(m_watchPollTimer, &QTimer::timeout, this, &MainWindow::pollTogether);
    m_watchHeartbeatTimer = new QTimer(this);
    m_watchHeartbeatTimer->setInterval(2500);
    connect(m_watchHeartbeatTimer, &QTimer::timeout, this, &MainWindow::heartbeatTogether);

    connect(m_player, &QMediaPlayer::positionChanged, this, &MainWindow::positionChanged);
    connect(m_player, &QMediaPlayer::durationChanged, this, &MainWindow::durationChanged);
    connect(m_player, &QMediaPlayer::playbackStateChanged, this, &MainWindow::mediaStateChanged);
    connect(m_player, &QMediaPlayer::mediaStatusChanged, this, &MainWindow::mediaStatusChanged);
    connect(m_player, &QMediaPlayer::errorOccurred, this, &MainWindow::mediaErrorOccurred);

    setWindowTitle(QStringLiteral("withU Desktop"));
    resize(1280, 800);
    if (statusBar()) {
        statusBar()->hide();
    }
    showStatus(QStringLiteral("withU 桌面客户端框架已启动"));
    QTimer::singleShot(0, this, [this]() {
        // The desktop shell opens the same web home as the browser. If WebView2
        // is unavailable, the existing Qt home remains the safe fallback.
        showMaximized();
        switchSection(9);
        if (auto *homeButton = m_navGroup ? m_navGroup->button(0) : nullptr) {
            homeButton->setChecked(true);
        }
        connectToServer();
    });
}

MainWindow::~MainWindow()
{
    stopMpvPlayback();
}

QUrl MainWindow::apiUrl(const QString &pathAndQuery) const
{
    const QString base = m_serverEdit ? m_serverEdit->text().trimmed() : QStringLiteral("http://127.0.0.1:8080");
    QUrl url = QUrl::fromUserInput(base);
    if (!url.isValid()) {
        url = QUrl(QStringLiteral("http://127.0.0.1:8080"));
    }
    QString path = pathAndQuery;
    if (!path.startsWith('/')) {
        path.prepend('/');
    }
    url.setPath(path.left(path.indexOf('?') >= 0 ? path.indexOf('?') : path.length()));
    const int queryIndex = path.indexOf('?');
    if (queryIndex >= 0) {
        url.setQuery(path.mid(queryIndex + 1));
    } else {
        url.setQuery(QString());
    }
    return url;
}

bool MainWindow::startEmbeddedMpvPlayback(const QUrl &source, bool autoplay)
{
    QWidget *host = activeMpvHostWidget();
    if (!m_videoWidget || !host || source.isEmpty()) {
        return false;
    }

    stopEmbeddedMpvPlayback();
    if (!m_embeddedMpvLibrary) {
        m_embeddedMpvLibrary = new QLibrary(this);
    }
    const QString appDir = QCoreApplication::applicationDirPath();
    const QStringList candidates = {
        QDir(appDir).filePath(QStringLiteral("libmpv-2.dll")),
        QDir(appDir).filePath(QStringLiteral("libmpv/libmpv-2.dll")),
        QDir(appDir).filePath(QStringLiteral("../third_party/libmpv/libmpv-2.dll")),
        QStringLiteral("C:/WithU/withU/desktop/withu-player/third_party/libmpv/libmpv-2.dll")
    };
    QString libraryPath;
    for (const QString &candidate : candidates) {
        if (QFileInfo::exists(candidate)) {
            libraryPath = QFileInfo(candidate).absoluteFilePath();
            break;
        }
    }
    if (libraryPath.isEmpty()) {
        return false;
    }
    m_embeddedMpvLibrary->setFileName(libraryPath);
    if (!m_embeddedMpvLibrary->load()) {
        return false;
    }

    g_embeddedMpvApi.create = resolveMpv<MpvCreate>(m_embeddedMpvLibrary, "mpv_create");
    g_embeddedMpvApi.initialize = resolveMpv<MpvInitialize>(m_embeddedMpvLibrary, "mpv_initialize");
    g_embeddedMpvApi.setOptionString = resolveMpv<MpvSetOptionString>(m_embeddedMpvLibrary, "mpv_set_option_string");
    g_embeddedMpvApi.command = resolveMpv<MpvCommand>(m_embeddedMpvLibrary, "mpv_command");
    g_embeddedMpvApi.setProperty = resolveMpv<MpvSetProperty>(m_embeddedMpvLibrary, "mpv_set_property");
    g_embeddedMpvApi.getProperty = resolveMpv<MpvGetProperty>(m_embeddedMpvLibrary, "mpv_get_property");
    g_embeddedMpvApi.waitEvent = resolveMpv<MpvWaitEvent>(m_embeddedMpvLibrary, "mpv_wait_event");
    g_embeddedMpvApi.terminateDestroy = resolveMpv<MpvTerminateDestroy>(m_embeddedMpvLibrary, "mpv_terminate_destroy");
    if (!g_embeddedMpvApi.create || !g_embeddedMpvApi.initialize || !g_embeddedMpvApi.setOptionString
        || !g_embeddedMpvApi.command || !g_embeddedMpvApi.setProperty || !g_embeddedMpvApi.getProperty
        || !g_embeddedMpvApi.waitEvent || !g_embeddedMpvApi.terminateDestroy) {
        stopEmbeddedMpvPlayback();
        return false;
    }

    mpv_handle *handle = g_embeddedMpvApi.create();
    if (!handle) {
        stopEmbeddedMpvPlayback();
        return false;
    }
    m_embeddedMpv = handle;
    // QVideoWidget owns its own multimedia surface.  Give libmpv a separate
    // native QWidget instead of binding to that surface directly; this avoids
    // a black/covered video area on Windows while keeping the visible layout
    // and controls unchanged.
    const QByteArray windowId = QByteArray::number(static_cast<qulonglong>(host->winId()));
    const auto setOption = [this](const char *name, const QByteArray &value) {
        return g_embeddedMpvApi.setOptionString(m_embeddedMpv, name, value.constData()) >= 0;
    };
    if (!setOption("terminal", "no")
        || !setOption("idle", "yes")
        || !setOption("keep-open", "yes")
        || !setOption("vo", "gpu")
        || !setOption("gpu-api", "d3d11")
        || !setOption("hwdec", "auto-safe")
        // Prefer the physical D3D11 adapter. WARP is a software renderer and
        // is retained only as libmpv's automatic fallback, not the default.
        || !setOption("d3d11-warp", "no")
        || !setOption("osd-level", "0")
        || !setOption("wid", windowId)
        || !setOption("pause", autoplay ? "no" : "yes")) {
        stopEmbeddedMpvPlayback();
        return false;
    }
    if (g_embeddedMpvApi.initialize(m_embeddedMpv) < 0) {
        stopEmbeddedMpvPlayback();
        return false;
    }
    if (m_videoSurfaceStack) {
        if (host == m_mpvHostWidget) m_videoSurfaceStack->setCurrentWidget(m_mpvHostWidget);
    }
    host->show();
    host->raise();
    if (m_player) {
        m_player->stop();
        m_player->setVideoOutput(nullptr);
    }
    const QByteArray media = source.isLocalFile()
        ? QFile::encodeName(source.toLocalFile())
        : source.toString(QUrl::FullyEncoded).toUtf8();
    const char *command[] = {"loadfile", media.constData(), "replace", nullptr};
    if (g_embeddedMpvApi.command(m_embeddedMpv, command) < 0) {
        stopEmbeddedMpvPlayback();
        if (m_player) m_player->setVideoOutput(m_videoWidget);
        return false;
    }
    m_usingEmbeddedMpv = true;
    m_mpvMediaReady = false;
    m_usingMpv = true;
    m_mpvPlaying = autoplay;
    m_mpvRate = 1.0;
    m_mpvPosition = 0;
    m_mpvDuration = 0;
    if (host == m_webMpvHostWidget) {
        m_webMpvOverlayRequested = true;
        updateWebMpvOverlay();
    }
    if (m_mpvPollTimer) m_mpvPollTimer->start();
    showStatus(QStringLiteral("libmpv 解码中 · 自动硬件/软件回退"), 5000);
    return true;
}

void MainWindow::stopEmbeddedMpvPlayback()
{
    if (m_embeddedMpv && g_embeddedMpvApi.terminateDestroy) {
        g_embeddedMpvApi.terminateDestroy(m_embeddedMpv);
    }
    m_embeddedMpv = nullptr;
    m_usingEmbeddedMpv = false;
    m_mpvMediaReady = false;
    g_embeddedMpvApi = {};
    if (m_embeddedMpvLibrary) {
        if (m_embeddedMpvLibrary->isLoaded()) m_embeddedMpvLibrary->unload();
        delete m_embeddedMpvLibrary;
        m_embeddedMpvLibrary = nullptr;
    }
    if (m_videoSurfaceStack && m_videoWidget) {
        m_videoSurfaceStack->setCurrentWidget(m_videoWidget);
    }
    if (m_webMpvHostWidget) {
        m_webMpvHostWidget->hide();
    }
    if (m_player && m_videoWidget) {
        m_player->setVideoOutput(m_videoWidget);
    }
}

void MainWindow::pollEmbeddedMpvPlayback()
{
    if (!m_embeddedMpv || !g_embeddedMpvApi.getProperty) return;
    while (g_embeddedMpvApi.waitEvent) {
        mpv_event *event = g_embeddedMpvApi.waitEvent(m_embeddedMpv, 0.0);
        if (!event || event->event_id == MPV_EVENT_NONE) break;
        if (event->event_id == MPV_EVENT_FILE_LOADED) {
            m_mpvMediaReady = true;
            updateWebMpvOverlay();
        }
        if (event->event_id == MPV_EVENT_END_FILE) {
            m_mpvMediaReady = false;
            updateWebMpvOverlay();
        }
        if (event->event_id == MPV_EVENT_END_FILE && !m_mpvIntentionalStop) {
            if (m_webShell && m_webShell->isReady() && m_webMpvOverlayRequested) {
                m_webShell->postJson(QJsonObject{{QStringLiteral("type"), QStringLiteral("desktop-mpv-ended")}});
            } else if (m_autoplayEnabled && m_currentEpisodeIndex >= 0 && m_currentEpisodeIndex + 1 < m_episodePlayUrls.size()) {
                playNextEpisode();
            }
        }
    }
    double position = 0.0;
    double duration = 0.0;
    double speed = 1.0;
    int paused = 1;
    if (g_embeddedMpvApi.getProperty(m_embeddedMpv, "time-pos", MPV_FORMAT_DOUBLE, &position) >= 0) {
        m_mpvPosition = qMax<qint64>(0, qRound64(position * 1000.0));
    }
    if (g_embeddedMpvApi.getProperty(m_embeddedMpv, "duration", MPV_FORMAT_DOUBLE, &duration) >= 0) {
        m_mpvDuration = qMax<qint64>(0, qRound64(duration * 1000.0));
    }
    if (g_embeddedMpvApi.getProperty(m_embeddedMpv, "speed", MPV_FORMAT_DOUBLE, &speed) >= 0 && speed > 0) {
        m_mpvRate = speed;
    }
    if (g_embeddedMpvApi.getProperty(m_embeddedMpv, "pause", MPV_FORMAT_FLAG, &paused) >= 0) {
        m_mpvPlaying = paused == 0;
    }
    if (m_mpvDuration > 0 && m_seekSlider) {
        m_seekSlider->setRange(0, static_cast<int>(qMin<qint64>(m_mpvDuration, std::numeric_limits<int>::max())));
    }
    if (m_seekSlider && !m_userSeeking && m_mpvDuration > 0) {
        m_seekSlider->setValue(static_cast<int>(qMin<qint64>(m_mpvPosition, std::numeric_limits<int>::max())));
    }
    if (m_timeLabel) updateTimeLabel(m_mpvPosition, m_mpvDuration);
    if (m_playButton) m_playButton->setText(m_mpvPlaying ? QStringLiteral("暂停") : QStringLiteral("播放"));
    if (m_webShell && m_webShell->isReady()) {
        QJsonObject state;
        state.insert(QStringLiteral("type"), QStringLiteral("desktop-mpv-state"));
        state.insert(QStringLiteral("active"), m_mpvMediaReady);
        state.insert(QStringLiteral("position"), m_mpvPosition / 1000.0);
        state.insert(QStringLiteral("duration"), m_mpvDuration / 1000.0);
        state.insert(QStringLiteral("paused"), !m_mpvPlaying);
        state.insert(QStringLiteral("speed"), m_mpvRate);
        state.insert(QStringLiteral("volume"), m_volumeSlider ? m_volumeSlider->value() / 100.0 : 0.8);
        m_webShell->postJson(state);
    }
}

QWidget *MainWindow::activeMpvHostWidget() const
{
    if (m_webMpvOverlayRequested && m_webMpvHostWidget && m_webMpvHostWidget->parentWidget()) {
        return m_webMpvHostWidget;
    }
    return m_mpvHostWidget;
}

void MainWindow::updateWebMpvOverlay()
{
    if (!m_webMpvHostWidget) return;
    if (!m_webMpvOverlayRequested || !m_usingEmbeddedMpv || !m_mpvMediaReady
        || m_webMpvRect.width() <= 0 || m_webMpvRect.height() <= 0) {
        m_webMpvHostWidget->hide();
        return;
    }
    m_webMpvHostWidget->setGeometry(m_webMpvRect);
    m_webMpvHostWidget->show();
    m_webMpvHostWidget->raise();
}

void MainWindow::syncWebViewCookies()
{
    if (!m_webShell || !m_webShell->isReady() || !m_network || !m_network->cookieJar()) return;
    const QUrl base = apiUrl(QStringLiteral("/"));
    if (auto *jar = dynamic_cast<PersistentCookieJar *>(m_network->cookieJar())) {
        m_webShell->setCookies(base, jar->cookies());
    }
}

void MainWindow::handleWebMessage(const QJsonObject &message)
{
    const QString type = message.value(QStringLiteral("type")).toString();
    if (type == QStringLiteral("desktop-player-route")) {
        const QString route = message.value(QStringLiteral("route")).toString();
        const bool isPlayer = route.contains(QStringLiteral("watch_play.php"), Qt::CaseInsensitive);
        m_webMpvOverlayRequested = isPlayer;
        if (!isPlayer) {
            stopMpvPlayback();
            m_webMpvRect = {};
            updateWebMpvOverlay();
        }
        return;
    }
    if (type == QStringLiteral("desktop-player-rect")) {
        const int x = qRound(message.value(QStringLiteral("x")).toDouble());
        const int y = qRound(message.value(QStringLiteral("y")).toDouble());
        const int width = qRound(message.value(QStringLiteral("width")).toDouble());
        const int height = qRound(message.value(QStringLiteral("height")).toDouble());
        const int controlsHeight = qBound(0, qRound(message.value(QStringLiteral("controlsHeight")).toDouble(58)), height);
        m_webMpvRect = QRect(x, y, width, qMax(1, height - controlsHeight));
        updateWebMpvOverlay();
        return;
    }
    if (type == QStringLiteral("desktop-player-source")) {
        const QString rawUrl = message.value(QStringLiteral("url")).toString().trimmed();
        if (rawUrl.isEmpty()) return;
        const QUrl source = rawUrl.startsWith('/') ? apiUrl(rawUrl) : QUrl::fromUserInput(rawUrl);
        const bool autoplay = message.value(QStringLiteral("autoplay")).toBool(true);
        const qint64 position = qMax<qint64>(0, qRound64(message.value(QStringLiteral("position")).toDouble() * 1000.0));
        m_webMpvOverlayRequested = true;
        m_pendingLocalPosition = position;
        if (!startEmbeddedMpvPlayback(source, autoplay)) {
            if (m_webShell) {
                QJsonObject response;
                response.insert(QStringLiteral("type"), QStringLiteral("desktop-mpv-error"));
                response.insert(QStringLiteral("message"), QStringLiteral("libmpv 初始化失败，已回退网页解码"));
                m_webShell->postJson(response);
            }
            showStatus(QStringLiteral("libmpv 初始化失败，已回退网页解码"), 5000);
        } else if (position > 0) {
            sendMpvCommand(QByteArray("seek ") + QByteArray::number(position / 1000.0, 'f', 3));
        }
        return;
    }
    if (type == QStringLiteral("desktop-player-command")) {
        sendMpvCommand(message.value(QStringLiteral("command")).toString().toUtf8());
        return;
    }
    if (type == QStringLiteral("desktop-player-state-request")) {
        if (m_usingEmbeddedMpv && m_webShell) {
            pollEmbeddedMpvPlayback();
        }
    }
}

void MainWindow::sendMpvCommand(const QByteArray &command)
{
    if (!m_usingEmbeddedMpv || !m_embeddedMpv || !g_embeddedMpvApi.command) {
        return;
    }

    const QString raw = QString::fromUtf8(command).trimmed();
    if (raw == QStringLiteral("play") || raw == QStringLiteral("pause")) {
        int paused = raw == QStringLiteral("pause") ? 1 : 0;
        g_embeddedMpvApi.setProperty(m_embeddedMpv, "pause", MPV_FORMAT_FLAG, &paused);
    } else if (raw == QStringLiteral("stop") || raw == QStringLiteral("quit")) {
        const char *cmd[] = {raw == QStringLiteral("stop") ? "stop" : "quit", nullptr};
        g_embeddedMpvApi.command(m_embeddedMpv, cmd);
    } else if (raw.startsWith(QStringLiteral("seek "))) {
        bool ok = false;
        const QByteArray seconds = QByteArray::number(raw.mid(5).trimmed().toDouble(&ok), 'f', 3);
        if (ok) { const char *cmd[] = {"seek", seconds.constData(), "absolute", "exact", nullptr}; g_embeddedMpvApi.command(m_embeddedMpv, cmd); }
    } else if (raw.startsWith(QStringLiteral("rate "))) {
        bool ok = false; const double value = raw.mid(5).trimmed().toDouble(&ok);
        if (ok) { double rate = value; g_embeddedMpvApi.setProperty(m_embeddedMpv, "speed", MPV_FORMAT_DOUBLE, &rate); }
    } else if (raw.startsWith(QStringLiteral("volume "))) {
        bool ok = false; const double value = raw.mid(7).trimmed().toDouble(&ok);
        if (ok) { double volume = qBound(0.0, value > 100.0 ? value * 100.0 / 256.0 : value, 100.0); g_embeddedMpvApi.setProperty(m_embeddedMpv, "volume", MPV_FORMAT_DOUBLE, &volume); }
    }
}

bool MainWindow::startMpvPlayback(const QUrl &source, bool autoplay)
{
    stopMpvPlayback();
    return startEmbeddedMpvPlayback(source, autoplay);
}

void MainWindow::stopMpvPlayback()
{
    if (m_mpvPollTimer) m_mpvPollTimer->stop();
    m_mpvIntentionalStop = true;
    stopEmbeddedMpvPlayback();
    m_mpvIntentionalStop = false;
    m_usingMpv = false;
    m_mpvPlaying = false;
}

void MainWindow::pollMpvPlayback()
{
    if (m_usingEmbeddedMpv) pollEmbeddedMpvPlayback();
}

void MainWindow::buildUi()
{
    auto *central = new QWidget(this);
    auto *root = new QVBoxLayout(central);
    root->setContentsMargins(18, 14, 18, 18);
    root->setSpacing(12);

    // Desktop shell follows the web home page: top brand bar, couple hero,
    // wave-like separator and horizontal navigation. The pages below keep
    // their native Qt controls so only decoding remains desktop-specific.
    auto *topNav = new QFrame(central);
    topNav->setObjectName(QStringLiteral("topNav"));
    auto *topLayout = new QHBoxLayout(topNav);
    topLayout->setContentsMargins(16, 10, 16, 10);
    auto *brand = new QLabel(QStringLiteral("withU"), topNav);
    brand->setObjectName(QStringLiteral("brandTitle"));
    const QString assetRoot = QDir(QCoreApplication::applicationDirPath()).filePath(QStringLiteral("assets/images"));
    const QString logoPath = QDir(assetRoot).filePath(QStringLiteral("withu-logo.png"));
    if (QFileInfo::exists(logoPath)) {
        const QPixmap logo(logoPath);
        brand->setPixmap(logo);
        brand->setScaledContents(true);
        brand->setFixedSize(112, 42);
        brand->setToolTip(QStringLiteral("withU"));
    }
    auto *brandSub = mutedLabel(QStringLiteral("记录我们的小小点滴"), topNav);
    topLayout->addWidget(brand);
    topLayout->addWidget(brandSub, 1, Qt::AlignVCenter);
    m_connectionLabel = mutedLabel(QStringLiteral("未连接网页端"), topNav);
    m_connectionLabel->setObjectName(QStringLiteral("connectionLabel"));
    m_connectionLabel->setAlignment(Qt::AlignRight | Qt::AlignVCenter);
    topLayout->addWidget(m_connectionLabel, 0, Qt::AlignVCenter);
    root->addWidget(topNav);

    auto *hero = new QFrame(central);
    m_globalHero = hero;
    hero->setObjectName(QStringLiteral("heroHeader"));
    hero->setMinimumHeight(300);
    const QString heroPath = QDir(assetRoot).filePath(QStringLiteral("default_hero.jpg"));
    if (QFileInfo::exists(heroPath)) {
        QString heroUrl = heroPath;
        heroUrl.replace('\\', '/');
        hero->setStyleSheet(QStringLiteral("QFrame#heroHeader{background-image:url('%1');background-position:center;background-repeat:no-repeat;background-color:#e8eef5;}").arg(heroUrl));
    }
    auto *heroLayout = new QVBoxLayout(hero);
    heroLayout->setContentsMargins(18, 16, 18, 16);
    heroLayout->setSpacing(6);
    auto *pairRow = new QHBoxLayout;
    pairRow->setSpacing(12);
    pairRow->setAlignment(Qt::AlignCenter);
    auto makeAvatar = [hero, assetRoot](const QString &letter, const QString &objectName) {
        auto *avatar = new QLabel(letter, hero);
        avatar->setObjectName(objectName);
        avatar->setAlignment(Qt::AlignCenter);
        avatar->setFixedSize(94, 94);
        const QString avatarPath = QDir(assetRoot).filePath(QStringLiteral("default-avatar.svg"));
        if (QFileInfo::exists(avatarPath)) {
            const QPixmap image(avatarPath);
            if (!image.isNull()) {
                avatar->setPixmap(image);
                avatar->setScaledContents(true);
            }
        }
        return avatar;
    };
    m_heroAvatarPrimary = makeAvatar(QStringLiteral("顾"), QStringLiteral("heroAvatarPrimary"));
    pairRow->addWidget(m_heroAvatarPrimary);
    auto *heart = new QLabel(QStringLiteral("♥"), hero);
    heart->setObjectName(QStringLiteral("heroHeart"));
    heart->setAlignment(Qt::AlignCenter);
    pairRow->addWidget(heart);
    m_heroAvatarPartner = makeAvatar(QStringLiteral("肖"), QStringLiteral("heroAvatarPartner"));
    pairRow->addWidget(m_heroAvatarPartner);
    heroLayout->addLayout(pairRow);
    m_heroTitle = new QLabel(QStringLiteral("withU"), hero);
    m_heroTitle->setObjectName(QStringLiteral("heroTitle"));
    m_heroTitle->setAlignment(Qt::AlignCenter);
    heroLayout->addWidget(m_heroTitle);
    auto *heroSubtitle = mutedLabel(QStringLiteral("我们的故事，从这里继续"), hero);
    heroSubtitle->setObjectName(QStringLiteral("heroSubtitle"));
    heroSubtitle->setAlignment(Qt::AlignCenter);
    heroLayout->addWidget(heroSubtitle);
    root->addWidget(hero);

    auto *mainNav = new QFrame(central);
    m_globalNav = mainNav;
    mainNav->setObjectName(QStringLiteral("mainNav"));
    auto *navLayout = new QHBoxLayout(mainNav);
    navLayout->setContentsMargins(8, 6, 8, 6);
    navLayout->setSpacing(8);
    m_navGroup = new QButtonGroup(this);
    m_navGroup->setExclusive(true);
    const QList<QPair<QString, int>> navItems = {
        {QStringLiteral("情侣空间"), 0}, {QStringLiteral("一起看"), 1},
        {QStringLiteral("影视库"), 2}, {QStringLiteral("播放器"), 3},
        {QStringLiteral("设置"), 4}, {QStringLiteral("点点滴滴"), 5},
        {QStringLiteral("天气旅行"), 6}, {QStringLiteral("留言墙"), 7},
        {QStringLiteral("观影历史"), 8}
    };
    for (const auto &item : navItems) {
        auto *button = makeNavButton(item.first, item.second);
        button->setMinimumWidth(92);
        navLayout->addWidget(button, 1);
    }
    root->addWidget(mainNav);

    m_pages = new QStackedWidget(central);
    auto *homeScroll = new QScrollArea(central);
    homeScroll->setObjectName(QStringLiteral("homeScroll"));
    homeScroll->setFrameShape(QFrame::NoFrame);
    homeScroll->setWidgetResizable(true);
    homeScroll->setHorizontalScrollBarPolicy(Qt::ScrollBarAlwaysOff);
    homeScroll->setVerticalScrollBarPolicy(Qt::ScrollBarAsNeeded);
    homeScroll->setWidget(buildHomePage());
    m_pages->addWidget(homeScroll);
    m_pages->addWidget(buildTogetherPage());
    m_pages->addWidget(buildLibraryPage());
    m_pages->addWidget(buildPlayerPage());
    m_pages->addWidget(buildSettingsPage());
    m_pages->addWidget(buildContentPage());
    m_pages->addWidget(buildTravelPage());
    m_pages->addWidget(buildMessagesPage());
    m_pages->addWidget(buildHistoryPage());
    m_pages->addWidget(buildWebShellPage());

    root->addWidget(m_pages, 1);
    setCentralWidget(central);

    connect(m_navGroup, &QButtonGroup::idClicked, this, &MainWindow::switchSection);
    if (auto *button = m_navGroup->button(0)) {
        button->setChecked(true);
    }

    setStyleSheet(QStringLiteral(
        "QMainWindow{background:#f8fbfa;color:#263238;}"
        "QWidget{font-family:'Microsoft YaHei','Segoe UI',sans-serif;font-size:14px;}"
        "QScrollArea{background:transparent;border:0;} QScrollArea > QWidget > QWidget{background:transparent;}"
        "#topNav,#heroHeader,#mainNav,#glassCard{background:rgba(255,255,255,0.76);border:1px solid rgba(255,255,255,0.78);border-radius:22px;}"
        "#topNav{background:rgba(255,255,255,0.9);border-radius:16px;}"
        "#heroHeader{background:qlineargradient(x1:0,y1:0,x2:1,y2:1,stop:0 rgba(255,225,236,235),stop:0.52 rgba(255,246,250,235),stop:1 rgba(215,240,252,230));border-radius:26px;}"
        "#mainNav{background:rgba(255,255,255,0.84);border-radius:17px;}"
        "#brandTitle{font-size:27px;font-weight:800;color:#d96c91;}"
        "#pageTitle{font-size:26px;font-weight:760;color:#263238;}"
        "#mutedLabel{color:#718087;line-height:1.45;}"
        "QPushButton{background:rgba(255,255,255,0.74);border:1px solid #dfe9e5;border-radius:13px;padding:9px 13px;color:#263238;}"
        "QPushButton:hover{background:#fff3f7;border-color:#f5b6c8;}"
        "QPushButton:pressed{background:#f8dce6;}"
        "QPushButton:checked{background:qlineargradient(x1:0,y1:0,x2:1,y2:0,stop:0 #f5b6c8,stop:1 #b8ddf2);border-color:#f0b5c7;color:#263238;font-weight:700;}"
        "QLineEdit,QDoubleSpinBox{background:rgba(255,255,255,0.86);border:1px solid #dbe8e2;border-radius:12px;padding:9px;color:#263238;}"
        "QListWidget{background:rgba(255,255,255,0.72);border:1px solid #dbe8e2;border-radius:14px;padding:8px;color:#263238;}"
        "QListWidget::item{padding:10px;border-radius:10px;}"
        "QListWidget::item:selected{background:#f5b6c8;color:#263238;}"
        "#metricCard{background:rgba(255,255,255,0.72);border:1px solid #e2ebe7;border-radius:16px;}"
        "#metricValue{font-size:24px;font-weight:760;color:#d96c91;}"
        "#loveCounter{background:rgba(255,248,251,0.84);border:1px solid #f2d9e2;border-radius:18px;}"
        "#loveCounterTitle{font-size:15px;color:#d96c91;font-weight:700;}"
        "#loveCounterValue{font-size:22px;color:#263238;font-weight:800;}"
        "#libraryFilterPanel,#librarySidebar{background:rgba(255,255,255,0.72);border:1px solid #dbe8e2;border-radius:16px;}"
        "#libraryFilterPanel QPushButton{text-align:left;padding:8px 10px;border-radius:10px;}"
        "#libraryFilterPanel QPushButton:checked{background:#f5b6c8;border-color:#e486a4;font-weight:700;}"
        "#librarySidebar QPushButton{text-align:left;padding:9px 10px;border-radius:10px;}"
        "#librarySidebar QPushButton:checked{background:#f5b6c8;border-color:#e486a4;font-weight:700;}"
        "#librarySidebarToggle{text-align:center;font-size:18px;font-weight:800;padding:6px;}"
        "#homeWatchAction{background:rgba(255,245,249,0.82);border:1px solid #f5d2dd;border-radius:18px;}"
        "#homeWatchActionTitle{font-size:17px;font-weight:700;color:#263238;}"
        "#primaryButton{background:#e486a4;border:1px solid #d96c91;color:#fff;font-weight:700;}"
        "#primaryButton:hover{background:#d96c91;border-color:#c45f80;}"
        "#heroTitle{font-size:28px;font-weight:800;color:#263238;}"
        "#heroSubtitle{font-size:14px;}"
        "#heroHeart{font-size:24px;color:#e486a4;}"
        "#heroAvatarPrimary,#heroAvatarPartner{font-size:22px;font-weight:800;color:#263238;border:3px solid rgba(255,255,255,0.92);border-radius:31px;background:qlineargradient(x1:0,y1:0,x2:1,y2:1,stop:0 #f5b6c8,stop:1 #b8ddf2);}"
        "#heroAvatarPartner{background:qlineargradient(x1:0,y1:0,x2:1,y2:1,stop:0 #b9e3d0,stop:1 #b8ddf2);}"
        "#connectionLabel{font-size:12px;}"
        "QVideoWidget{background:#10131a;border-radius:18px;}"
        "QSlider::groove:horizontal{height:6px;background:#dbe8e2;border-radius:3px;}"
        "QSlider::handle:horizontal{width:16px;margin:-5px 0;border-radius:8px;background:#e486a4;}"
        "QStatusBar{background:transparent;color:#718087;}"
    ));
}

QPushButton *MainWindow::makeNavButton(const QString &text, int index)
{
    auto *button = new QPushButton(text, this);
    button->setCheckable(true);
    button->setMinimumHeight(42);
    button->setProperty("navIndex", index);
    m_navGroup->addButton(button, index);
    return button;
}

QWidget *MainWindow::buildHomePage()
{
    auto *page = new QFrame(this);
    page->setObjectName(QStringLiteral("homePage"));
    const QString assetRoot = QDir(QCoreApplication::applicationDirPath()).filePath(QStringLiteral("assets/images"));
    const QString heroPath = QDir(assetRoot).filePath(QStringLiteral("default_hero.jpg"));
    QString heroUrl = heroPath;
    heroUrl.replace('\\', '/');
    page->setStyleSheet(QStringLiteral(
        "QFrame#homePage{background-image:url('%1');background-position:center;background-repeat:no-repeat;}"
        "QFrame#homeTopBar,QFrame#homeControls{background:rgba(255,255,255,0.66);border:1px solid rgba(255,255,255,0.84);}"
        "QFrame#homeTopBar{border-radius:0;border-left:0;border-right:0;}"
        "QFrame#homeCoupleGlass{background:rgba(255,255,255,0.34);border:1px solid rgba(255,255,255,0.78);border-radius:42px;}"
        "QFrame#homeWave{background:rgba(255,255,255,0.28);border:1px solid rgba(255,255,255,0.58);border-radius:30px;}"
        "QFrame#homeControls{border-radius:36px 36px 0 0;margin:0;}"
        "QPushButton#homeSettingsButton{background:rgba(255,255,255,0.52);border:1px solid rgba(255,255,255,0.88);border-radius:19px;padding:8px 16px;color:#263238;font-weight:700;}"
        "QPushButton#homeSettingsButton:hover{background:#fff3f7;border-color:#f5b6c8;}"
        "QLabel#homeBrand{font-weight:800;color:#263238;}"
        "QLabel#homeVersion{background:qlineargradient(x1:0,y1:0,x2:1,y2:1,stop:0 #f7a5bb,stop:0.52 #f28ba9,stop:1 #e8799e);border:1px solid rgba(255,255,255,0.82);border-radius:10px;padding:8px 12px;color:#ffffff;font-weight:800;}"
        "QLabel#homeHeaderLine{color:#56656c;}"
        "QLabel#homeName{font-size:16px;font-weight:700;color:#ffffff;}"
        "QLabel#homeLove{font-size:30px;color:#f25f7a;}"
        "QLabel#loveCounterTitle{font-weight:700;color:#263238;}"
        "QLabel#homeTimerNumber{font-weight:800;color:#101010;font-family:'Noto Serif SC','Times New Roman',serif;}"
        "QLabel#homeTimerUnit{font-weight:700;color:#263238;padding-left:2px;}"
        "QLabel#loveCounterValue{font-size:26px;font-weight:800;color:#e486a4;}"
        "QLabel#homeSummary{color:#718087;}"
        "QPushButton#homeCardPink,QPushButton#homeCardBlue,QPushButton#homeCardGreen,QPushButton#homeCardMint{border:1px solid rgba(255,255,255,0.78);border-radius:22px;padding:18px;text-align:left;font-weight:800;color:#263238;}"
        "QPushButton#homeCardPink{background:rgba(248,121,165,0.88);}"
        "QPushButton#homeCardBlue{background:rgba(69,170,240,0.88);}"
        "QPushButton#homeCardGreen{background:rgba(55,204,129,0.86);}"
        "QPushButton#homeCardMint{background:rgba(113,214,205,0.88);}"
        "QPushButton#homeCardPink:hover,QPushButton#homeCardBlue:hover,QPushButton#homeCardGreen:hover,QPushButton#homeCardMint:hover{border-color:#ffffff;}").arg(heroUrl));

    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(0, 0, 0, 0);
    layout->setSpacing(0);

    auto *topBar = new QFrame(page);
    topBar->setObjectName(QStringLiteral("homeTopBar"));
    topBar->setFixedHeight(72);
    auto *topLayout = new QHBoxLayout(topBar);
    topLayout->setContentsMargins(18, 8, 18, 8);
    auto *brand = new QLabel(topBar);
    brand->setObjectName(QStringLiteral("homeBrand"));
    const QString logoPath = QDir(assetRoot).filePath(QStringLiteral("withu-logo.png"));
    if (QFileInfo::exists(logoPath)) {
        QPixmap logo(logoPath);
        brand->setPixmap(logo.scaled(118, 48, Qt::KeepAspectRatio, Qt::SmoothTransformation));
    } else {
        brand->setText(QStringLiteral("withU"));
    }
    topLayout->addWidget(brand, 0, Qt::AlignVCenter);
    auto *version = new QLabel(QStringLiteral("V5.2.0"), topBar);
    version->setObjectName(QStringLiteral("homeVersion"));
    version->setAlignment(Qt::AlignCenter);
    topLayout->addWidget(version, 0, Qt::AlignVCenter);
    topLayout->addStretch(1);
    auto *headerLine = new QLabel(QStringLiteral("树是梧桐树，城是南京城。一句梧桐美，种满南京城。"), topBar);
    headerLine->setObjectName(QStringLiteral("homeHeaderLine"));
    headerLine->setAlignment(Qt::AlignRight | Qt::AlignVCenter);
    headerLine->setSizePolicy(QSizePolicy::Expanding, QSizePolicy::Preferred);
    topLayout->addWidget(headerLine, 1, Qt::AlignVCenter);
    auto *homeStatus = new QLabel(QStringLiteral("♡"), topBar);
    homeStatus->setStyleSheet(QStringLiteral("font-size:28px;color:#f25f7a;padding:0 12px;"));
    topLayout->addWidget(homeStatus, 0, Qt::AlignVCenter);
    auto *settings = new QPushButton(QStringLiteral("⚙  设置"), topBar);
    settings->setObjectName(QStringLiteral("homeSettingsButton"));
    settings->setCursor(Qt::PointingHandCursor);
    topLayout->addWidget(settings, 0, Qt::AlignVCenter);
    layout->addWidget(topBar);
    connect(settings, &QPushButton::clicked, this, [this]() { switchSection(4); });

    auto *pairArea = new QFrame(page);
    pairArea->setStyleSheet(QStringLiteral("background:transparent;"));
    auto *pairLayout = new QVBoxLayout(pairArea);
    pairLayout->setContentsMargins(0, 14, 0, 28);
    // Keep the couple portraits slightly above the visual midpoint so they
    // do not crowd the love counter panel on shorter windows.
    pairLayout->addSpacing(62);
    auto *pairRow = new QHBoxLayout;
    pairRow->setSpacing(18);
    pairRow->setAlignment(Qt::AlignCenter);
    auto makeAvatar = [page, assetRoot](const QString &name, const QString &objectName) {
        auto *avatar = new QLabel(page);
        avatar->setObjectName(objectName);
        avatar->setAlignment(Qt::AlignCenter);
        avatar->setFixedSize(114, 114);
        avatar->setStyleSheet(QStringLiteral("background:rgba(255,255,255,0.88);border:4px solid rgba(255,255,255,0.92);border-radius:57px;color:#718087;font-size:22px;font-weight:800;"));
        const QString avatarPath = QDir(assetRoot).filePath(QStringLiteral("default-avatar.svg"));
        if (QFileInfo::exists(avatarPath)) {
            QPixmap image(avatarPath);
            avatar->setPixmap(image.scaled(105, 105, Qt::KeepAspectRatio, Qt::SmoothTransformation));
        } else {
            avatar->setText(name.left(1));
        }
        return avatar;
    };
    m_heroAvatarPrimary = makeAvatar(QStringLiteral("我"), QStringLiteral("heroAvatarPrimary"));
    m_heroAvatarPartner = makeAvatar(QStringLiteral("宝宝"), QStringLiteral("heroAvatarPartner"));
    pairRow->addWidget(m_heroAvatarPrimary);
    auto *heart = new HeartPulseWidget(page);
    heart->setObjectName(QStringLiteral("homeLove"));
    heart->setFixedSize(100, 100);
    pairRow->addWidget(heart);
    pairRow->addWidget(m_heroAvatarPartner);
    pairLayout->addLayout(pairRow);
    auto *names = new QHBoxLayout;
    names->setSpacing(96);
    names->setAlignment(Qt::AlignCenter);
    auto *primaryName = new QLabel(QStringLiteral("我"), page);
    primaryName->setObjectName(QStringLiteral("homePrimaryName"));
    primaryName->setProperty("homeName", true);
    primaryName->setAlignment(Qt::AlignCenter);
    auto *partnerName = new QLabel(QStringLiteral("宝宝"), page);
    partnerName->setObjectName(QStringLiteral("homePartnerName"));
    partnerName->setProperty("homeName", true);
    partnerName->setAlignment(Qt::AlignCenter);
    names->addWidget(primaryName);
    names->addWidget(partnerName);
    pairLayout->addLayout(names);
    pairLayout->addStretch(1);

    auto *wave = new LoveCounterPanel(page);
    wave->setObjectName(QStringLiteral("homeWave"));
    wave->setMinimumHeight(224);
    wave->setMinimumWidth(480);
    wave->setMaximumWidth(1280);
    auto *waveLayout = new QVBoxLayout(wave);
    waveLayout->setContentsMargins(24, 18, 24, 16);
    waveLayout->setSpacing(4);
    auto *waveTitle = new GradientTextLabel(wave);
    waveTitle->setText(QStringLiteral("这是我们一起走过的"));
    waveTitle->setObjectName(QStringLiteral("loveCounterTitle"));
    waveTitle->setAlignment(Qt::AlignCenter);
    waveLayout->addWidget(waveTitle);

    auto *timerRow = new QHBoxLayout;
    timerRow->setSpacing(4);
    timerRow->setAlignment(Qt::AlignCenter);
    auto addTimerPart = [wave, timerRow](QLabel **number, const QString &value, const QString &unit) {
        *number = new QLabel(value, wave);
        (*number)->setObjectName(QStringLiteral("homeTimerNumber"));
        (*number)->setAlignment(Qt::AlignCenter);
        auto *unitLabel = new QLabel(unit, wave);
        unitLabel->setObjectName(QStringLiteral("homeTimerUnit"));
        unitLabel->setAlignment(Qt::AlignBottom | Qt::AlignLeft);
        timerRow->addWidget(*number, 0, Qt::AlignVCenter);
        timerRow->addWidget(unitLabel, 0, Qt::AlignBottom);
    };
    addTimerPart(&m_loveDaysLabel, QStringLiteral("0"), QStringLiteral("天"));
    addTimerPart(&m_loveHoursLabel, QStringLiteral("00"), QStringLiteral("时"));
    addTimerPart(&m_loveMinutesLabel, QStringLiteral("00"), QStringLiteral("分"));
    addTimerPart(&m_loveSecondsLabel, QStringLiteral("00"), QStringLiteral("秒"));
    waveLayout->addLayout(timerRow);
    m_loveCounterLabel = new QLabel(wave);
    m_loveCounterLabel->setVisible(false);
    m_homeSummaryLabel = new QLabel(QStringLiteral("连接网页端后加载情侣空间数据"), wave);
    m_homeSummaryLabel->setObjectName(QStringLiteral("homeSummary"));
    m_homeSummaryLabel->setAlignment(Qt::AlignCenter);
    m_homeSummaryLabel->setVisible(false);
    auto *coupleGlass = new QFrame(page);
    coupleGlass->setObjectName(QStringLiteral("homeCoupleGlass"));
    auto *glassEffect = new QGraphicsDropShadowEffect(coupleGlass);
    glassEffect->setBlurRadius(30);
    glassEffect->setOffset(0, 12);
    glassEffect->setColor(QColor(24, 35, 43, 82));
    coupleGlass->setGraphicsEffect(glassEffect);
    auto *glassLayout = new QVBoxLayout(coupleGlass);
    glassLayout->setContentsMargins(28, 20, 28, 24);
    glassLayout->setSpacing(2);
    glassLayout->addWidget(pairArea, 1);
    glassLayout->addWidget(wave, 0, Qt::AlignHCenter);
    layout->addWidget(coupleGlass, 1, Qt::AlignHCenter);
    layout->addSpacing(30);

    auto *controls = new QFrame(page);
    controls->setObjectName(QStringLiteral("homeControls"));
    auto *controlsLayout = new QVBoxLayout(controls);
    controlsLayout->setContentsMargins(24, 18, 24, 18);
    auto *cardRow = new QGridLayout;
    cardRow->setObjectName(QStringLiteral("homeCardLayout"));
    cardRow->setHorizontalSpacing(12);
    cardRow->setVerticalSpacing(10);
    const QList<QPair<QString, int>> cards = {
        {QStringLiteral("一起看\n同步播放 · 聊天"), 2},
        {QStringLiteral("影视库\n继续观看 · 选集"), 2},
        {QStringLiteral("点点滴滴\n文章 · 相册 · 留言"), 5},
        {QStringLiteral("天气旅行\n下一站，一起去"), 6}
    };
    const QStringList cardNames = {QStringLiteral("homeCardPink"), QStringLiteral("homeCardBlue"), QStringLiteral("homeCardGreen"), QStringLiteral("homeCardMint")};
    for (int i = 0; i < cards.size(); ++i) {
        auto *card = new QPushButton(cards[i].first, controls);
        card->setObjectName(cardNames.value(i));
        card->setMinimumHeight(86);
        card->setCursor(Qt::PointingHandCursor);
        cardRow->addWidget(card, 0, i);
        connect(card, &QPushButton::clicked, this, [this, index = cards[i].second]() { switchSection(index); });
    }
    controlsLayout->addLayout(cardRow);
    layout->addWidget(controls);
    layout->addSpacing(0);

    // Keep the feed model alive for existing API updates; the visual home now
    // presents the focused couple dashboard instead of a text-heavy feed.
    m_homeFeedList = new QListWidget(page);
    m_homeFeedList->setObjectName(QStringLiteral("homeFeedList"));
    m_homeFeedList->setVisible(false);
    m_homeFeedList->addItem(QStringLiteral("等待连接网页端"));
    connect(m_homeFeedList, &QListWidget::itemActivated, this, &MainWindow::openCoupleItem);
    connect(m_homeFeedList, &QListWidget::itemDoubleClicked, this, &MainWindow::openCoupleItem);
    return page;
}

QWidget *MainWindow::buildTogetherPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(14);
    auto *title = new QLabel(QStringLiteral("一起看"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    layout->addWidget(title);

    auto *card = glassCard(page);
    auto *cardLayout = qobject_cast<QVBoxLayout *>(card->layout());
    auto *heading = new QLabel(QStringLiteral("房间状态"), card);
    heading->setObjectName(QStringLiteral("cardHeading"));
    cardLayout->addWidget(heading);
    m_togetherStateLabel = mutedLabel(QStringLiteral("尚未加入一起看房间"), card);
    m_togetherStateLabel->setMinimumHeight(48);
    cardLayout->addWidget(m_togetherStateLabel);

    auto *actions = new QHBoxLayout;
    m_joinTogetherButton = new QPushButton(QStringLiteral("加入一起看"), card);
    auto *refreshButton = new QPushButton(QStringLiteral("刷新状态"), card);
    m_leaveTogetherButton = new QPushButton(QStringLiteral("结束一起看"), card);
    m_leaveTogetherButton->setEnabled(false);
    auto *openPlayer = new QPushButton(QStringLiteral("进入播放器"), card);
    actions->addWidget(m_joinTogetherButton);
    actions->addWidget(refreshButton);
    actions->addWidget(m_leaveTogetherButton);
    actions->addStretch(1);
    actions->addWidget(openPlayer);
    cardLayout->addLayout(actions);
    connect(m_joinTogetherButton, &QPushButton::clicked, this, &MainWindow::joinTogether);
    connect(refreshButton, &QPushButton::clicked, this, &MainWindow::pollTogether);
    connect(m_leaveTogetherButton, &QPushButton::clicked, this, &MainWindow::leaveTogether);
    connect(openPlayer, &QPushButton::clicked, this, [this]() { switchSection(3); });

    cardLayout->addWidget(mutedLabel(QStringLiteral("桌面端复用网页端同步协议：500ms 读取状态、2.5s 心跳；播放、暂停、拖动和倍速会写回同一个房间。"), card));
    layout->addWidget(card);
    layout->addStretch(1);
    return page;
}

QWidget *MainWindow::buildLibraryPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(14);
    auto *titleRow = new QHBoxLayout;
    auto *title = new QLabel(QStringLiteral("媒体库"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    titleRow->addWidget(title);
    titleRow->addStretch(1);
    auto *backHome = new QPushButton(QStringLiteral("WithU 首页"), page);
    backHome->setObjectName(QStringLiteral("secondaryButton"));
    backHome->setToolTip(QStringLiteral("返回情侣空间和四个空间入口"));
    titleRow->addWidget(backHome);
    connect(backHome, &QPushButton::clicked, this, [this]() { switchSection(0); });
    layout->addLayout(titleRow);

    auto *libraryLayout = new QHBoxLayout;
    libraryLayout->setSpacing(12);

    auto *sidebar = new QFrame(page);
    sidebar->setObjectName(QStringLiteral("librarySidebar"));
    sidebar->setMinimumWidth(56);
    sidebar->setMaximumWidth(176);
    m_librarySidebar = sidebar;
    auto *sidebarLayout = new QVBoxLayout(sidebar);
    sidebarLayout->setContentsMargins(8, 10, 8, 10);
    sidebarLayout->setSpacing(8);
    auto *sidebarToggle = new QPushButton(QStringLiteral("☰"), sidebar);
    sidebarToggle->setObjectName(QStringLiteral("librarySidebarToggle"));
    sidebarToggle->setToolTip(QStringLiteral("折叠/展开资源库侧栏"));
    sidebarToggle->setFixedHeight(38);
    m_librarySidebarToggle = sidebarToggle;
    sidebarLayout->addWidget(sidebarToggle);
    auto *filterTitle = new QLabel(QStringLiteral("资源分类"), sidebar);
    filterTitle->setObjectName(QStringLiteral("cardHeading"));
    sidebarLayout->addWidget(filterTitle);
    auto *typeGroup = new QButtonGroup(sidebar);
    typeGroup->setExclusive(true);
    const QList<QPair<QString, int>> typeItems = {
        {QStringLiteral("全部影片"), 0}, {QStringLiteral("电影"), 1},
        {QStringLiteral("电视剧"), 2}, {QStringLiteral("综艺"), 4},
        {QStringLiteral("动漫"), 3}
    };
    for (const auto &type : typeItems) {
        auto *button = new QPushButton(type.first, sidebar);
        button->setCheckable(true);
        button->setMinimumHeight(36);
        button->setProperty("libraryTypeId", type.second);
        typeGroup->addButton(button, type.second);
        sidebarLayout->addWidget(button);
    }
    if (auto *allButton = typeGroup->button(0)) allButton->setChecked(true);
    sidebarLayout->addSpacing(10);
    auto *playerButton = new QPushButton(QStringLiteral("播放器"), sidebar);
    auto *settingsButton = new QPushButton(QStringLiteral("设置"), sidebar);
    sidebarLayout->addWidget(playerButton);
    sidebarLayout->addWidget(settingsButton);
    sidebarLayout->addStretch(1);
    connect(playerButton, &QPushButton::clicked, this, [this]() { switchSection(3); });
    connect(settingsButton, &QPushButton::clicked, this, [this]() { switchSection(4); });
    connect(sidebarToggle, &QPushButton::clicked, this, [this, sidebar, filterTitle, playerButton, settingsButton]() {
        m_librarySidebarExpanded = !m_librarySidebarExpanded;
        sidebar->setFixedWidth(m_librarySidebarExpanded ? 176 : 58);
        filterTitle->setVisible(m_librarySidebarExpanded);
        playerButton->setText(m_librarySidebarExpanded ? QStringLiteral("播放器") : QStringLiteral("▶"));
        settingsButton->setText(m_librarySidebarExpanded ? QStringLiteral("设置") : QStringLiteral("⚙"));
        for (auto *button : sidebar->findChildren<QPushButton *>()) {
            if (button == m_librarySidebarToggle || button == playerButton || button == settingsButton) continue;
            const int typeId = button->property("libraryTypeId").toInt();
            if (typeId >= 0 && typeId <= 4) {
                const QString fullText = button->property("libraryTypeText").toString();
                if (fullText.isEmpty()) button->setProperty("libraryTypeText", button->text());
                button->setText(m_librarySidebarExpanded ? button->property("libraryTypeText").toString() : QStringLiteral("•"));
                button->setToolTip(m_librarySidebarExpanded ? QString() : button->property("libraryTypeText").toString());
            }
        }
    });
    connect(typeGroup, &QButtonGroup::idClicked, this, [this](int typeId) {
        m_libraryTypeId = qBound(0, typeId, 4);
        requestLibrary(m_librarySearch ? m_librarySearch->text().trimmed() : QString());
    });

    m_mediaList = new QListWidget(page);
    m_mediaList->setViewMode(QListView::IconMode);
    m_mediaList->setFlow(QListView::LeftToRight);
    m_mediaList->setWrapping(true);
    m_mediaList->setResizeMode(QListView::Adjust);
    m_mediaList->setMovement(QListView::Static);
    m_mediaList->setWordWrap(true);
    m_mediaList->setMouseTracking(true);
    m_mediaList->setIconSize(QSize(132, 176));
    m_mediaList->setGridSize(QSize(164, 232));
    m_mediaList->setSpacing(8);
    m_mediaList->setUniformItemSizes(false);
    m_mediaList->addItem(QStringLiteral("等待连接网页端媒体库 API"));
    m_mediaList->addItem(QStringLiteral("OpenList 路径播放时实时请求直链"));
    m_mediaList->addItem(QStringLiteral("后续按主演、类型、分辨率推荐"));
    m_mediaList->setMinimumWidth(360);
    connect(m_mediaList, &QListWidget::itemEntered, this, [this](QListWidgetItem *item) {
        auto restoreItem = [](QListWidgetItem *target) {
            if (!target) return;
            const QString originalText = target->data(Qt::UserRole + 3).toString();
            if (!originalText.isEmpty()) target->setText(originalText);
            const QPixmap original = target->data(Qt::UserRole + 4).value<QPixmap>();
            if (!original.isNull()) target->setIcon(QIcon(original));
        };
        restoreItem(m_hoveredMediaItem);
        m_hoveredMediaItem = nullptr;
        if (!item || item->data(Qt::UserRole + 2).toString().isEmpty()) return;
        const QString originalText = item->data(Qt::UserRole + 3).toString();
        if (originalText.isEmpty()) item->setData(Qt::UserRole + 3, item->text());
        const QPixmap original = item->data(Qt::UserRole + 4).value<QPixmap>();
        if (original.isNull()) return;
        QPixmap enlarged = original.scaled(148, 198, Qt::KeepAspectRatioByExpanding, Qt::SmoothTransformation);
        QPainter painter(&enlarged);
        painter.fillRect(enlarged.rect(), QColor(10, 15, 20, 34));
        const QPoint center = enlarged.rect().center();
        QPolygon triangle({QPoint(center.x() - 3, center.y() - 18), QPoint(center.x() - 3, center.y() + 18), QPoint(center.x() + 25, center.y())});
        painter.setPen(Qt::NoPen);
        painter.setBrush(QColor(255, 255, 255, 235));
        painter.drawPolygon(triangle);
        item->setIcon(QIcon(enlarged));
        item->setText(QStringLiteral("▶ ") + item->data(Qt::UserRole + 3).toString());
        m_hoveredMediaItem = item;
    });
    connect(m_mediaList, &QListWidget::viewportEntered, this, [this]() {
        if (!m_hoveredMediaItem) return;
        const QString originalText = m_hoveredMediaItem->data(Qt::UserRole + 3).toString();
        const QPixmap original = m_hoveredMediaItem->data(Qt::UserRole + 4).value<QPixmap>();
        if (!originalText.isEmpty()) m_hoveredMediaItem->setText(originalText);
        if (!original.isNull()) m_hoveredMediaItem->setIcon(QIcon(original));
        m_hoveredMediaItem = nullptr;
    });

    auto *searchRow = new QHBoxLayout;
    m_librarySearch = new QLineEdit(page);
    m_librarySearch->setPlaceholderText(QStringLiteral("搜索片名、文件名或集标题"));
    auto *searchButton = new QPushButton(QStringLiteral("搜索"), page);
    auto *refreshButton = new QPushButton(QStringLiteral("刷新"), page);
    searchRow->addWidget(m_librarySearch, 1);
    searchRow->addWidget(searchButton);
    searchRow->addWidget(refreshButton);

    auto *detailCard = glassCard(page);
    auto *detailLayout = qobject_cast<QVBoxLayout *>(detailCard->layout());
    detailLayout->addWidget(new QLabel(QStringLiteral("媒体详情"), detailCard));
    m_libraryDetailLabel = mutedLabel(QStringLiteral("选择左侧媒体后显示简介、状态和选集。"), detailCard);
    detailLayout->addWidget(m_libraryDetailLabel);
    m_episodeList = new QListWidget(detailCard);
    m_episodeList->addItem(QStringLiteral("暂无选集"));
    detailLayout->addWidget(m_episodeList, 1);

    auto loadLibrary = [this]() {
        if (m_csrfToken.isEmpty()) {
            connectToServer();
        } else {
            requestLibrary(m_librarySearch ? m_librarySearch->text().trimmed() : QString());
        }
    };
    connect(searchButton, &QPushButton::clicked, this, loadLibrary);
    connect(refreshButton, &QPushButton::clicked, this, loadLibrary);
    connect(m_librarySearch, &QLineEdit::returnPressed, this, loadLibrary);
    auto openLibraryItem = [this](QListWidgetItem *item) {
        if (!item) {
            return;
        }
        const QJsonDocument doc = QJsonDocument::fromJson(item->data(Qt::UserRole + 2).toString().toUtf8());
        const int mediaId = doc.isObject() ? doc.object().value(QStringLiteral("id")).toInt() : 0;
        openLibraryMedia(item->data(Qt::UserRole).toString(), item->data(Qt::UserRole + 1).toString(), mediaId);
    };
    auto showLibraryItem = [this](QListWidgetItem *item) {
        if (!item) {
            return;
        }
        const QJsonDocument doc = QJsonDocument::fromJson(item->data(Qt::UserRole + 2).toString().toUtf8());
        if (doc.isObject()) {
            renderLibraryDetail(doc.object());
        }
    };
    connect(m_mediaList, &QListWidget::itemActivated, this, openLibraryItem);
    connect(m_mediaList, &QListWidget::itemDoubleClicked, this, openLibraryItem);
    // The library is a launch surface, not a detail page: a single click
    // goes directly to the player while the hidden detail model still
    // prepares the episode arrays used by the playback page.
    connect(m_mediaList, &QListWidget::itemClicked, this, openLibraryItem);
    connect(m_mediaList, &QListWidget::currentItemChanged, this, [showLibraryItem](QListWidgetItem *current, QListWidgetItem *) {
        showLibraryItem(current);
    });
    connect(m_episodeList, &QListWidget::itemActivated, this, [this](QListWidgetItem *item) {
        if (!item) return;
        openLibraryMedia(item->data(Qt::UserRole).toString(), item->data(Qt::UserRole + 1).toString(), item->data(Qt::UserRole + 2).toInt());
    });
    connect(m_episodeList, &QListWidget::itemDoubleClicked, this, [this](QListWidgetItem *item) {
        if (!item) return;
        openLibraryMedia(item->data(Qt::UserRole).toString(), item->data(Qt::UserRole + 1).toString(), item->data(Qt::UserRole + 2).toInt());
    });

    auto *libraryResults = new QVBoxLayout;
    libraryResults->setSpacing(10);
    detailCard->setVisible(false);
    libraryResults->addWidget(m_mediaList, 1);
    libraryLayout->addWidget(sidebar, 0);
    libraryLayout->addLayout(libraryResults, 1);
    layout->addLayout(searchRow);
    layout->addLayout(libraryLayout, 1);
    return page;
}

QWidget *MainWindow::buildPlayerPage()
{
    auto *page = new QWidget(this);
    auto *root = new QVBoxLayout(page);
    root->setContentsMargins(4, 4, 4, 4);
    root->setSpacing(12);

    auto *title = new QLabel(QStringLiteral("播放器"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    root->addWidget(title);

    auto *sourceRow = new QHBoxLayout;
    sourceRow->setSpacing(8);
    m_sourceEdit = new QLineEdit(page);
    m_sourceEdit->setPlaceholderText(QStringLiteral("本地路径或 OpenList / HTTP 视频直链"));
    m_sourceEdit->setClearButtonEnabled(true);
    m_openButton = new QPushButton(QStringLiteral("打开文件"), page);
    auto *loadButton = new QPushButton(QStringLiteral("加载"), page);
    sourceRow->addWidget(m_sourceEdit, 1);
    sourceRow->addWidget(m_openButton);
    sourceRow->addWidget(loadButton);
    root->addLayout(sourceRow);

    m_videoSurfaceStack = new QStackedWidget(page);
    m_videoSurfaceStack->setObjectName(QStringLiteral("videoSurfaceStack"));
    m_videoWidget = new QVideoWidget(m_videoSurfaceStack);
    m_videoWidget->setAttribute(Qt::WA_NativeWindow);
    m_videoWidget->setMinimumSize(640, 360);
    m_videoWidget->setAspectRatioMode(Qt::KeepAspectRatio);
    m_mpvHostWidget = new QWidget(m_videoSurfaceStack);
    m_mpvHostWidget->setObjectName(QStringLiteral("mpvHostWidget"));
    m_mpvHostWidget->setAttribute(Qt::WA_NativeWindow);
    m_mpvHostWidget->setAttribute(Qt::WA_OpaquePaintEvent);
    m_mpvHostWidget->setAutoFillBackground(true);
    m_mpvHostWidget->setMinimumSize(640, 360);
    QPalette mpvPalette = m_mpvHostWidget->palette();
    mpvPalette.setColor(QPalette::Window, QColor(QStringLiteral("#10131a")));
    m_mpvHostWidget->setPalette(mpvPalette);
    m_videoSurfaceStack->addWidget(m_videoWidget);
    m_videoSurfaceStack->addWidget(m_mpvHostWidget);
    m_videoSurfaceStack->setCurrentWidget(m_videoWidget);
    m_player->setVideoOutput(m_videoWidget);
    root->addWidget(m_videoSurfaceStack, 1);

    auto *progressRow = new QHBoxLayout;
    m_seekSlider = new QSlider(Qt::Horizontal, page);
    m_seekSlider->setRange(0, 0);
    m_timeLabel = new QLabel(QStringLiteral("00:00 / 00:00"), page);
    m_timeLabel->setMinimumWidth(112);
    m_timeLabel->setAlignment(Qt::AlignRight | Qt::AlignVCenter);
    progressRow->addWidget(m_seekSlider, 1);
    progressRow->addWidget(m_timeLabel);
    root->addLayout(progressRow);

    auto *controls = new QHBoxLayout;
    controls->setSpacing(8);
    m_previousEpisodeButton = new QPushButton(QStringLiteral("上一集"), page);
    m_backButton = new QPushButton(QStringLiteral("后退 10 秒"), page);
    m_playButton = new QPushButton(QStringLiteral("播放"), page);
    m_stopButton = new QPushButton(QStringLiteral("停止"), page);
    m_forwardButton = new QPushButton(QStringLiteral("前进 10 秒"), page);
    m_nextEpisodeButton = new QPushButton(QStringLiteral("下一集"), page);
    m_fullscreenButton = new QPushButton(QStringLiteral("全屏"), page);
    m_speedBox = new QDoubleSpinBox(page);
    m_speedBox->setRange(0.25, 4.0);
    m_speedBox->setSingleStep(0.25);
    m_speedBox->setValue(1.0);
    m_speedBox->setSuffix(QStringLiteral("x"));
    m_volumeSlider = new QSlider(Qt::Horizontal, page);
    m_volumeSlider->setRange(0, 100);
    m_volumeSlider->setValue(80);
    m_volumeSlider->setMaximumWidth(140);

    controls->addWidget(m_previousEpisodeButton);
    controls->addWidget(m_backButton);
    controls->addWidget(m_playButton);
    controls->addWidget(m_stopButton);
    controls->addWidget(m_forwardButton);
    controls->addWidget(m_nextEpisodeButton);
    controls->addWidget(m_speedBox);
    controls->addStretch(1);
    controls->addWidget(new QLabel(QStringLiteral("音量"), page));
    controls->addWidget(m_volumeSlider);
    controls->addWidget(m_fullscreenButton);
    root->addLayout(controls);

    m_chatPanel = glassCard(page);
    m_chatPanel->setObjectName(QStringLiteral("chatPanel"));
    auto *chatLayout = qobject_cast<QVBoxLayout *>(m_chatPanel->layout());
    auto *chatTitle = new QLabel(QStringLiteral("一起看聊天"), m_chatPanel);
    chatTitle->setObjectName(QStringLiteral("cardHeading"));
    chatLayout->addWidget(chatTitle);
    m_chatList = new QListWidget(m_chatPanel);
    m_chatList->setObjectName(QStringLiteral("chatList"));
    m_chatList->setMaximumHeight(132);
    m_chatList->setFocusPolicy(Qt::NoFocus);
    chatLayout->addWidget(m_chatList);
    auto *chatRow = new QHBoxLayout;
    m_chatEdit = new QLineEdit(m_chatPanel);
    m_chatEdit->setPlaceholderText(QStringLiteral("发消息，回车发送"));
    auto *chatSend = new QPushButton(QStringLiteral("发送"), m_chatPanel);
    chatRow->addWidget(m_chatEdit, 1);
    chatRow->addWidget(chatSend);
    chatLayout->addLayout(chatRow);
    m_chatPanel->setVisible(false);
    root->addWidget(m_chatPanel);

    m_statusLabel = mutedLabel(QStringLiteral("选择本地视频，或粘贴 OpenList / HTTP 直链"), page);
    root->addWidget(m_statusLabel);

    connect(m_openButton, &QPushButton::clicked, this, &MainWindow::chooseLocalFile);
    connect(loadButton, &QPushButton::clicked, this, &MainWindow::openSource);
    connect(m_sourceEdit, &QLineEdit::returnPressed, this, &MainWindow::openSource);
    connect(m_playButton, &QPushButton::clicked, this, &MainWindow::togglePlayback);
    connect(m_stopButton, &QPushButton::clicked, this, &MainWindow::stopPlayback);
    connect(m_fullscreenButton, &QPushButton::clicked, this, &MainWindow::toggleVideoFullscreen);
    connect(m_previousEpisodeButton, &QPushButton::clicked, this, &MainWindow::playPreviousEpisode);
    connect(m_nextEpisodeButton, &QPushButton::clicked, this, &MainWindow::playNextEpisode);
    connect(m_backButton, &QPushButton::clicked, this, &MainWindow::seekBackward);
    connect(m_forwardButton, &QPushButton::clicked, this, &MainWindow::seekForward);
    connect(m_seekSlider, &QSlider::sliderPressed, this, &MainWindow::seekSliderPressed);
    connect(m_seekSlider, &QSlider::sliderReleased, this, &MainWindow::seekSliderReleased);
    connect(m_volumeSlider, &QSlider::valueChanged, this, &MainWindow::volumeChanged);
    connect(m_speedBox, &QDoubleSpinBox::valueChanged, this, &MainWindow::playbackRateChanged);
    connect(chatSend, &QPushButton::clicked, this, &MainWindow::sendChatMessage);
    connect(m_chatEdit, &QLineEdit::returnPressed, this, &MainWindow::sendChatMessage);
    m_previousEpisodeButton->setEnabled(false);
    m_nextEpisodeButton->setEnabled(false);

    return page;
}

QWidget *MainWindow::buildSettingsPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(14);

    auto *title = new QLabel(QStringLiteral("设置"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    layout->addWidget(title);

    auto *card = glassCard(page);
    auto *cardLayout = qobject_cast<QVBoxLayout *>(card->layout());
    cardLayout->addWidget(new QLabel(QStringLiteral("网页端连接"), card));
    cardLayout->addWidget(mutedLabel(QStringLiteral("密码仅用于本次登录，不会保存在本地；登录成功后仅保存信任设备 Cookie。"), card));
    QSettings settings(QStringLiteral("withU"), QStringLiteral("withU Desktop"));
    m_serverEdit = new QLineEdit(settings.value(QStringLiteral("server_url"), QStringLiteral("http://127.0.0.1:8080")).toString(), card);
    m_usernameEdit = new QLineEdit(card);
    m_passwordEdit = new QLineEdit(card);
    m_usernameEdit->setText(settings.value(QStringLiteral("username"), QString()).toString());
    settings.remove(QStringLiteral("password"));
    m_usernameEdit->setPlaceholderText(QStringLiteral("用户名"));
    m_passwordEdit->setPlaceholderText(QStringLiteral("密码"));
    m_passwordEdit->setEchoMode(QLineEdit::Password);
    auto *connectButton = new QPushButton(QStringLiteral("连接服务器"), card);
    auto *adminButton = new QPushButton(QStringLiteral("打开管理后台"), card);
    auto *webShellButton = new QPushButton(QStringLiteral("打开网页界面"), card);
    auto *logoutButton = new QPushButton(QStringLiteral("退出登录"), card);
    logoutButton->setObjectName(QStringLiteral("secondaryButton"));
    cardLayout->addWidget(m_serverEdit);
    cardLayout->addWidget(m_usernameEdit);
    cardLayout->addWidget(m_passwordEdit);
    cardLayout->addWidget(connectButton);
    cardLayout->addWidget(adminButton);
    cardLayout->addWidget(webShellButton);
    cardLayout->addWidget(logoutButton);
    connect(connectButton, &QPushButton::clicked, this, [this]() {
        connectToServer();
    });
    connect(adminButton, &QPushButton::clicked, this, [this]() {
        QUrl url = apiUrl(QStringLiteral("/admin/index.php"));
        QDesktopServices::openUrl(url);
    });
    connect(webShellButton, &QPushButton::clicked, this, [this]() {
        switchSection(9);
        if (m_webShell) {
            const QUrl webHome = apiUrl(QStringLiteral("/"));
            m_webShell->setAllowedOrigin(webHome);
            m_webShell->navigate(webHome);
        }
    });
    connect(logoutButton, &QPushButton::clicked, this, &MainWindow::logoutFromServer);
    layout->addWidget(card);
    layout->addStretch(1);
    return page;
}

QWidget *MainWindow::buildWebShellPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    // This is the user-facing desktop surface. Keep it edge-to-edge so the
    // DOM/CSS from WithU is the actual visual identity, not a Qt imitation
    // wrapped in an extra title or status bar.
    layout->setContentsMargins(0, 0, 0, 0);
    layout->setSpacing(0);

    m_webShell = new WebView2Host(page);
    m_webShell->setObjectName(QStringLiteral("webShell"));
    // Set the pending address before WebView2 creates its controller. The
    // desktop starts on this page, so relying on a later settings-button
    // navigation could otherwise leave the initial shell blank.
    const QUrl webHome = apiUrl(QStringLiteral("/"));
    m_webShell->setAllowedOrigin(webHome);
    m_webShell->navigate(webHome);
    layout->addWidget(m_webShell, 1);
    m_webMpvHostWidget = new QWidget(m_webShell);
    m_webMpvHostWidget->setObjectName(QStringLiteral("webMpvHostWidget"));
    m_webMpvHostWidget->setAttribute(Qt::WA_NativeWindow);
    m_webMpvHostWidget->setAttribute(Qt::WA_OpaquePaintEvent);
    m_webMpvHostWidget->setAutoFillBackground(true);
    m_webMpvHostWidget->hide();
    QPalette mpvPalette = m_webMpvHostWidget->palette();
    mpvPalette.setColor(QPalette::Window, QColor(Qt::black));
    m_webMpvHostWidget->setPalette(mpvPalette);
    connect(m_webShell, &WebView2Host::ready, this, [this]() {
        syncWebViewCookies();
        if (m_webShell) m_webShell->postJson(QJsonObject{{QStringLiteral("type"), QStringLiteral("desktop-shell-ready")}});
    });
    connect(m_webShell, &WebView2Host::failed, this, [this](const QString &message) {
        m_webMpvOverlayRequested = false;
        if (m_webMpvHostWidget) m_webMpvHostWidget->hide();
        showStatus(QStringLiteral("网页界面不可用：%1；已回退桌面首页。").arg(message), 0);
        switchSection(0);
    });
    connect(m_webShell, &WebView2Host::navigationStarting, this, [this](const QUrl &url) {
        const bool isPlayer = url.path().contains(QStringLiteral("watch_play.php"), Qt::CaseInsensitive);
        m_webMpvOverlayRequested = isPlayer;
        if (!isPlayer) {
            stopMpvPlayback();
            m_webMpvRect = {};
            updateWebMpvOverlay();
        }
    });
    connect(m_webShell, &WebView2Host::webMessageReceived, this, &MainWindow::handleWebMessage);
    return page;
}

QWidget *MainWindow::buildContentPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(12);

    m_contentTitle = new QLabel(QStringLiteral("内容详情"), page);
    m_contentTitle->setObjectName(QStringLiteral("pageTitle"));
    layout->addWidget(m_contentTitle);
    auto *backHome = new QPushButton(QStringLiteral("返回情侣空间"), page);
    connect(backHome, &QPushButton::clicked, this, [this]() { switchSection(0); });
    layout->addWidget(backHome, 0, Qt::AlignLeft);

    auto *card = glassCard(page);
    m_contentView = new QTextBrowser(card);
    m_contentView->setOpenExternalLinks(true);
    m_contentView->setOpenLinks(false);
    m_contentView->setPlaceholderText(QStringLiteral("从情侣空间选择文章或相册后，这里显示详情。"));
    qobject_cast<QVBoxLayout *>(card->layout())->addWidget(m_contentView, 1);
    layout->addWidget(card, 1);
    return page;
}

QWidget *MainWindow::buildTravelPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(12);
    auto *title = new QLabel(QStringLiteral("旅行"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    layout->addWidget(title);

    auto *formCard = glassCard(page);
    auto *formLayout = qobject_cast<QVBoxLayout *>(formCard->layout());
    formLayout->addWidget(new QLabel(QStringLiteral("AI 旅行规划"), formCard));
    auto *row = new QHBoxLayout;
    m_travelDestination = new QLineEdit(formCard);
    m_travelDestination->setPlaceholderText(QStringLiteral("目的地"));
    m_travelStart = new QLineEdit(formCard);
    m_travelStart->setPlaceholderText(QStringLiteral("开始日期 YYYY-MM-DD"));
    m_travelEnd = new QLineEdit(formCard);
    m_travelEnd->setPlaceholderText(QStringLiteral("结束日期 YYYY-MM-DD"));
    row->addWidget(m_travelDestination, 2);
    row->addWidget(m_travelStart, 1);
    row->addWidget(m_travelEnd, 1);
    formLayout->addLayout(row);
    m_travelPrompt = new QLineEdit(formCard);
    m_travelPrompt->setPlaceholderText(QStringLiteral("偏好：轻松、适合拍照、预算有限…"));
    formLayout->addWidget(m_travelPrompt);
    auto *planButton = new QPushButton(QStringLiteral("生成旅行计划"), formCard);
    formLayout->addWidget(planButton, 0, Qt::AlignLeft);
    connect(planButton, &QPushButton::clicked, this, &MainWindow::generateTravelPlan);
    connect(m_travelDestination, &QLineEdit::returnPressed, this, &MainWindow::generateTravelPlan);
    layout->addWidget(formCard);

    auto *weatherCard = glassCard(page);
    auto *weatherLayout = qobject_cast<QVBoxLayout *>(weatherCard->layout());
    weatherLayout->addWidget(new QLabel(QStringLiteral("天气查询"), weatherCard));
    auto *weatherRow = new QHBoxLayout;
    m_travelLat = new QLineEdit(weatherCard);
    m_travelLat->setPlaceholderText(QStringLiteral("纬度"));
    m_travelLng = new QLineEdit(weatherCard);
    m_travelLng->setPlaceholderText(QStringLiteral("经度"));
    auto *weatherButton = new QPushButton(QStringLiteral("查询天气"), weatherCard);
    weatherRow->addWidget(m_travelLat, 1);
    weatherRow->addWidget(m_travelLng, 1);
    weatherRow->addWidget(weatherButton);
    weatherLayout->addLayout(weatherRow);
    m_travelWeatherView = new QTextBrowser(weatherCard);
    m_travelWeatherView->setObjectName(QStringLiteral("travelWeatherView"));
    m_travelWeatherView->setMaximumHeight(92);
    m_travelWeatherView->setPlaceholderText(QStringLiteral("输入经纬度后查询天气。"));
    weatherLayout->addWidget(m_travelWeatherView);
    connect(weatherButton, &QPushButton::clicked, this, &MainWindow::loadTravelWeather);
    layout->addWidget(weatherCard);

    auto *resultCard = glassCard(page);
    m_travelView = new QTextBrowser(resultCard);
    m_travelView->setPlaceholderText(QStringLiteral("生成计划后，结果会显示在这里。"));
    qobject_cast<QVBoxLayout *>(resultCard->layout())->addWidget(m_travelView, 1);
    layout->addWidget(resultCard, 1);

    auto *historyCard = glassCard(page);
    auto *historyLayout = qobject_cast<QVBoxLayout *>(historyCard->layout());
    auto *historyHeader = new QHBoxLayout;
    historyHeader->addWidget(new QLabel(QStringLiteral("历史计划"), historyCard));
    auto *historyRefresh = new QPushButton(QStringLiteral("刷新"), historyCard);
    historyHeader->addWidget(historyRefresh, 0, Qt::AlignRight);
    historyLayout->addLayout(historyHeader);
    m_travelHistory = new QTextBrowser(historyCard);
    m_travelHistory->setMaximumHeight(130);
    m_travelHistory->setPlaceholderText(QStringLiteral("登录后显示历史计划。"));
    historyLayout->addWidget(m_travelHistory);
    connect(historyRefresh, &QPushButton::clicked, this, &MainWindow::loadTravelPlans);
    layout->addWidget(historyCard);
    return page;
}

QWidget *MainWindow::buildMessagesPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(12);
    auto *title = new QLabel(QStringLiteral("留言"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    layout->addWidget(title);
    auto *card = glassCard(page);
    auto *cardLayout = qobject_cast<QVBoxLayout *>(card->layout());
    auto *header = new QHBoxLayout;
    header->addWidget(new QLabel(QStringLiteral("留言墙"), card));
    auto *refresh = new QPushButton(QStringLiteral("刷新"), card);
    header->addWidget(refresh, 0, Qt::AlignRight);
    cardLayout->addLayout(header);
    m_messagesView = new QTextBrowser(card);
    m_messagesView->setPlaceholderText(QStringLiteral("连接网页端后读取留言。"));
    cardLayout->addWidget(m_messagesView, 1);
    auto *composeRow = new QHBoxLayout;
    m_messageEdit = new QLineEdit(card);
    m_messageEdit->setPlaceholderText(QStringLiteral("发消息"));
    m_messageEdit->setMaxLength(100);
    auto *sendButton = new QPushButton(QStringLiteral("发送"), card);
    sendButton->setObjectName(QStringLiteral("primaryButton"));
    composeRow->addWidget(m_messageEdit, 1);
    composeRow->addWidget(sendButton);
    cardLayout->addLayout(composeRow);
    connect(refresh, &QPushButton::clicked, this, &MainWindow::loadMessages);
    connect(sendButton, &QPushButton::clicked, this, &MainWindow::sendMessage);
    connect(m_messageEdit, &QLineEdit::returnPressed, this, &MainWindow::sendMessage);
    layout->addWidget(card, 1);
    return page;
}

QWidget *MainWindow::buildHistoryPage()
{
    auto *page = new QWidget(this);
    auto *layout = new QVBoxLayout(page);
    layout->setContentsMargins(4, 4, 4, 4);
    layout->setSpacing(12);
    auto *title = new QLabel(QStringLiteral("观影历史"), page);
    title->setObjectName(QStringLiteral("pageTitle"));
    layout->addWidget(title);

    auto *card = glassCard(page);
    auto *cardLayout = qobject_cast<QVBoxLayout *>(card->layout());
    auto *header = new QHBoxLayout;
    header->addWidget(new QLabel(QStringLiteral("最近观看"), card));
    auto *refresh = new QPushButton(QStringLiteral("刷新"), card);
    header->addWidget(refresh, 0, Qt::AlignRight);
    cardLayout->addLayout(header);
    cardLayout->addWidget(mutedLabel(QStringLiteral("点击记录可从上次位置继续播放；一起看和自己看的时长会分别显示。"), card));
    m_historyList = new QListWidget(card);
    m_historyList->setObjectName(QStringLiteral("historyList"));
    cardLayout->addWidget(m_historyList, 1);

    connect(refresh, &QPushButton::clicked, this, &MainWindow::loadHistory);
    auto openHistoryItem = [this](QListWidgetItem *item) {
        if (!item) return;
        const QJsonDocument doc = QJsonDocument::fromJson(item->data(Qt::UserRole).toString().toUtf8());
        if (!doc.isObject()) return;
        const QJsonObject history = doc.object();
        openLibraryMedia(history.value(QStringLiteral("play_url")).toString(), history.value(QStringLiteral("file_name")).toString(), history.value(QStringLiteral("media_id")).toInt(), history.value(QStringLiteral("last_position_ms")).toVariant().toLongLong());
    };
    connect(m_historyList, &QListWidget::itemActivated, this, openHistoryItem);
    connect(m_historyList, &QListWidget::itemDoubleClicked, this, openHistoryItem);
    layout->addWidget(card, 1);
    return page;
}

void MainWindow::generateTravelPlan()
{
    if (m_csrfToken.isEmpty()) {
        showStatus(QStringLiteral("请先连接并登录网页端"), 0);
        connectToServer();
        return;
    }
    const QString destination = m_travelDestination ? m_travelDestination->text().trimmed() : QString();
    if (destination.isEmpty()) {
        showStatus(QStringLiteral("请填写旅行目的地"), 2500);
        return;
    }
    QNetworkRequest request(apiUrl(QStringLiteral("/api/travel.php?action=plan")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    body.insert(QStringLiteral("destination"), destination);
    body.insert(QStringLiteral("start_date"), m_travelStart ? m_travelStart->text().trimmed() : QString());
    body.insert(QStringLiteral("end_date"), m_travelEnd ? m_travelEnd->text().trimmed() : QString());
    body.insert(QStringLiteral("prompt"), m_travelPrompt ? m_travelPrompt->text().trimmed() : QString());
    if (m_travelView) m_travelView->setHtml(QStringLiteral("<p>正在生成旅行计划，请稍候…</p>"));
    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    connect(reply, &QNetworkReply::finished, this, [this, reply, destination]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            if (m_travelView) m_travelView->setHtml(QStringLiteral("<p>请求失败：%1</p>").arg(reply->errorString().toHtmlEscaped()));
            return;
        }
        const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            if (m_travelView) m_travelView->setHtml(QStringLiteral("<p>%1</p>").arg(root.value(QStringLiteral("message")).toString(QStringLiteral("旅行计划生成失败")).toHtmlEscaped()));
            return;
        }
        const QString planText = QString::fromUtf8(QJsonDocument(root.value(QStringLiteral("plan")).toObject()).toJson(QJsonDocument::Indented));
        if (m_travelView) {
            m_travelView->setHtml(QStringLiteral("<h2>%1 旅行计划</h2><pre>%2</pre>")
                .arg(destination.toHtmlEscaped(), planText.toHtmlEscaped()));
        }
        showStatus(QStringLiteral("旅行计划已生成"), 4000);
    });
}

void MainWindow::loadTravelWeather()
{
    if (m_csrfToken.isEmpty()) {
        showStatus(QStringLiteral("请先连接并登录网页端"), 0);
        connectToServer();
        return;
    }
    bool latOk = false;
    bool lngOk = false;
    const double lat = m_travelLat ? m_travelLat->text().trimmed().toDouble(&latOk) : 0;
    const double lng = m_travelLng ? m_travelLng->text().trimmed().toDouble(&lngOk) : 0;
    if (!latOk || !lngOk || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        showStatus(QStringLiteral("请输入有效的经纬度"), 2500);
        return;
    }
    const QString query = QStringLiteral("/api/travel.php?action=weather&lat=%1&lng=%2")
        .arg(QString::number(lat, 'f', 6), QString::number(lng, 'f', 6));
    QNetworkReply *reply = m_network->get(QNetworkRequest(apiUrl(query)));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            if (m_travelWeatherView) m_travelWeatherView->setHtml(QStringLiteral("<p>天气请求失败：%1</p>").arg(reply->errorString().toHtmlEscaped()));
            return;
        }
        const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            if (m_travelWeatherView) m_travelWeatherView->setHtml(QStringLiteral("<p>%1</p>").arg(root.value(QStringLiteral("message")).toString(QStringLiteral("天气查询失败")).toHtmlEscaped()));
            return;
        }
        const QString output = QString::fromUtf8(QJsonDocument(root.value(QStringLiteral("data")).toObject()).toJson(QJsonDocument::Indented));
        if (m_travelWeatherView) m_travelWeatherView->setHtml(QStringLiteral("<pre>%1</pre>").arg(output.toHtmlEscaped()));
    });
}

void MainWindow::loadTravelPlans()
{
    if (m_csrfToken.isEmpty()) return;
    QNetworkReply *reply = m_network->get(QNetworkRequest(apiUrl(QStringLiteral("/api/travel.php?action=plans"))));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) return;
        const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
        if (!root.value(QStringLiteral("success")).toBool(false) || !m_travelHistory) return;
        QString html;
        for (const QJsonValue &value : root.value(QStringLiteral("items")).toArray()) {
            const QJsonObject item = value.toObject();
            html += QStringLiteral("<p><b>%1</b> · %2 · %3</p>")
                .arg(item.value(QStringLiteral("destination")).toString().toHtmlEscaped(),
                     item.value(QStringLiteral("start_date")).toString().toHtmlEscaped(),
                     item.value(QStringLiteral("created_at")).toString().toHtmlEscaped());
        }
        if (html.isEmpty()) html = QStringLiteral("<p>还没有历史旅行计划。</p>");
        m_travelHistory->setHtml(html);
    });
}

void MainWindow::loadMessages()
{
    if (m_messagesView) m_messagesView->setHtml(QStringLiteral("<p>正在读取留言…</p>"));
    QNetworkReply *reply = m_network->get(QNetworkRequest(apiUrl(QStringLiteral("/api/messages.php?page=1&per_page=50"))));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            if (m_messagesView) m_messagesView->setHtml(QStringLiteral("<p>留言读取失败：%1</p>").arg(reply->errorString().toHtmlEscaped()));
            return;
        }
        const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            if (m_messagesView) m_messagesView->setHtml(QStringLiteral("<p>%1</p>").arg(root.value(QStringLiteral("message")).toString(QStringLiteral("留言读取失败")).toHtmlEscaped()));
            return;
        }
        QString html;
        for (const QJsonValue &value : root.value(QStringLiteral("items")).toArray()) {
            const QJsonObject item = value.toObject();
            html += QStringLiteral("<p><b>%1</b> <span style='color:#718087'>%2</span><br>%3</p><hr>")
                .arg(item.value(QStringLiteral("nickname")).toString(QStringLiteral("匿名用户")).toHtmlEscaped(),
                     item.value(QStringLiteral("time_ago")).toString().toHtmlEscaped(),
                     item.value(QStringLiteral("content_html")).toString());
        }
        if (html.isEmpty()) html = QStringLiteral("<p>还没有公开留言。</p>");
        if (m_messagesView) m_messagesView->setHtml(html);
    });
}

void MainWindow::loadHistory()
{
    if (!m_historyList) return;
    if (m_csrfToken.isEmpty()) {
        m_historyList->clear();
        m_historyList->addItem(QStringLiteral("请先在设置中连接并登录网页端"));
        return;
    }
    m_historyList->clear();
    m_historyList->addItem(QStringLiteral("正在读取观影历史…"));
    QNetworkReply *reply = m_network->get(QNetworkRequest(apiUrl(QStringLiteral("/api/desktop.php?action=history"))));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (!m_historyList) return;
        if (reply->error() != QNetworkReply::NoError) {
            m_historyList->clear();
            m_historyList->addItem(QStringLiteral("观影历史读取失败：%1").arg(reply->errorString()));
            return;
        }
        const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            m_historyList->clear();
            m_historyList->addItem(root.value(QStringLiteral("message")).toString(QStringLiteral("观影历史读取失败")));
            return;
        }
        m_historyList->clear();
        for (const QJsonValue &value : root.value(QStringLiteral("items")).toArray()) {
            const QJsonObject history = value.toObject();
            const QString title = history.value(QStringLiteral("series_name")).toString(history.value(QStringLiteral("file_name")).toString(QStringLiteral("未命名影片")));
            const int episode = history.value(QStringLiteral("episode_number")).toInt();
            const QString episodeText = episode > 0 ? QStringLiteral(" · 第 %1 集").arg(episode) : QString();
            const qint64 watchMinutes = qMax<qint64>(0, history.value(QStringLiteral("watch_duration_ms")).toVariant().toLongLong() / 60000);
            const qint64 togetherMinutes = qMax<qint64>(0, history.value(QStringLiteral("together_duration_ms")).toVariant().toLongLong() / 60000);
            const qint64 positionMinutes = qMax<qint64>(0, history.value(QStringLiteral("last_position_ms")).toVariant().toLongLong() / 60000);
            const QString mode = togetherMinutes > 0 || history.value(QStringLiteral("participants_count")).toInt() > 1
                ? QStringLiteral("一起看") : QStringLiteral("自己看");
            const QString line = QStringLiteral("%1%2\n%3 · 已观看 %4 分钟 · 进度 %5 分钟 · %6")
                .arg(title, episodeText, mode)
                .arg(watchMinutes)
                .arg(positionMinutes)
                .arg(history.value(QStringLiteral("updated_at")).toString());
            auto *item = new QListWidgetItem(line, m_historyList);
            item->setData(Qt::UserRole, QString::fromUtf8(QJsonDocument(history).toJson(QJsonDocument::Compact)));
            item->setToolTip(QStringLiteral("点击继续播放\n自己看 %1 分钟 · 一起看 %2 分钟")
                .arg(history.value(QStringLiteral("solo_duration_ms")).toVariant().toLongLong() / 60000)
                .arg(togetherMinutes));
        }
        if (m_historyList->count() == 0) m_historyList->addItem(QStringLiteral("还没有观影历史"));
    });
}

void MainWindow::sendMessage()
{
    if (m_csrfToken.isEmpty()) {
        showStatus(QStringLiteral("请先连接并登录网页端"), 0);
        connectToServer();
        return;
    }
    const QString content = m_messageEdit ? m_messageEdit->text().trimmed() : QString();
    if (content.isEmpty()) {
        showStatus(QStringLiteral("请输入留言内容"), 2500);
        return;
    }

    QNetworkRequest request(apiUrl(QStringLiteral("/api/desktop.php?action=message")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    body.insert(QStringLiteral("content"), content);
    body.insert(QStringLiteral("is_public"), true);
    if (m_messageEdit) m_messageEdit->setEnabled(false);
    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (m_messageEdit) m_messageEdit->setEnabled(true);
        if (reply->error() != QNetworkReply::NoError) {
            showStatus(QStringLiteral("留言发送失败：%1").arg(reply->errorString()), 5000);
            return;
        }
        const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            showStatus(root.value(QStringLiteral("message")).toString(QStringLiteral("留言发送失败")), 5000);
            return;
        }
        if (m_messageEdit) m_messageEdit->clear();
        showStatus(QStringLiteral("留言已发送"), 2500);
        loadMessages();
    });
}

void MainWindow::connectToServer()
{
    const QString server = m_serverEdit ? m_serverEdit->text().trimmed() : QString();
    if (server.isEmpty()) {
        showStatus(QStringLiteral("请先填写网页端地址"), 0);
        return;
    }

    QSettings settings(QStringLiteral("withU"), QStringLiteral("withU Desktop"));
    settings.setValue(QStringLiteral("server_url"), server);
    if (m_usernameEdit) settings.setValue(QStringLiteral("username"), m_usernameEdit->text());
    settings.remove(QStringLiteral("password"));
    settings.sync();

    const QString username = m_usernameEdit ? m_usernameEdit->text().trimmed() : QString();
    const QString password = m_passwordEdit ? m_passwordEdit->text() : QString();
    // 登录成功后密码框会主动清空；只要没有新密码，就应复用已有 Cookie
    // 请求 bootstrap，而不是用空密码再次提交登录。
    const bool needLogin = !password.isEmpty();

    auto handleReply = [this](QNetworkReply *reply) {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            const int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
            if (statusCode == 401 || statusCode == 419) {
                clearDesktopSession();
                showStatus(QStringLiteral("登录状态已过期，请重新登录"), 5000);
                return;
            }
            showStatus(QStringLiteral("连接失败：%1").arg(reply->errorString()), 0);
            if (m_connectionLabel) {
                m_connectionLabel->setText(QStringLiteral("连接失败"));
            }
            return;
        }
        const QByteArray bytes = reply->readAll();
        const QJsonDocument doc = QJsonDocument::fromJson(bytes);
        if (!doc.isObject()) {
            showStatus(QStringLiteral("服务器返回不是有效 JSON"), 0);
            return;
        }
        const QJsonObject root = doc.object();
        if (!root.value(QStringLiteral("success")).toBool(true)) {
            const QString message = root.value(QStringLiteral("message")).toString(QStringLiteral("连接失败"));
            showStatus(message, 0);
            if (m_connectionLabel) {
                m_connectionLabel->setText(message);
            }
            return;
        }
        savePersistentCookies(m_network);
        applyBootstrapData(root);
        syncWebViewCookies();
        if (root.value(QStringLiteral("logged_in")).toBool(false) && m_passwordEdit) {
            m_passwordEdit->clear();
        }
        const QJsonObject user = root.value(QStringLiteral("user")).toObject();
        const bool loggedIn = root.value(QStringLiteral("logged_in")).toBool(false);
        // Cookie propagation alone does not change an already-rendered
        // WebView2 document. Reload the web home after bootstrap/login so the
        // first visible page reflects the desktop session immediately.
        if (m_webShell && m_webShell->isReady()) {
            const QUrl webHome = apiUrl(QStringLiteral("/"));
            m_webShell->setAllowedOrigin(webHome);
            m_webShell->navigate(webHome);
        }
        const QString status = loggedIn
            ? QStringLiteral("已连接：%1").arg(user.value(QStringLiteral("nickname")).toString(user.value(QStringLiteral("username")).toString()))
            : QStringLiteral("已连接网页端，当前未登录");
        showStatus(status, 6000);
        if (m_connectionLabel) {
            m_connectionLabel->setText(status);
        }
    };

    if (needLogin) {
        QNetworkRequest request(apiUrl(QStringLiteral("/api/desktop.php?action=login")));
        request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
        QJsonObject body;
        body.insert(QStringLiteral("username"), username);
        body.insert(QStringLiteral("password"), password);
        QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
        connect(reply, &QNetworkReply::finished, this, [reply, handleReply]() mutable {
            handleReply(reply);
        });
        return;
    }

    QNetworkRequest request(apiUrl(QStringLiteral("/api/desktop.php?action=bootstrap")));
    QNetworkReply *reply = m_network->get(request);
    connect(reply, &QNetworkReply::finished, this, [reply, handleReply]() mutable {
        handleReply(reply);
    });
}

void MainWindow::logoutFromServer()
{
    if (m_csrfToken.isEmpty()) {
        clearDesktopSession();
        showStatus(QStringLiteral("桌面端已退出登录"), 2500);
        return;
    }
    QNetworkRequest request(apiUrl(QStringLiteral("/api/desktop.php?action=logout")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    showStatus(QStringLiteral("正在退出登录…"), 0);
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            showStatus(QStringLiteral("退出登录请求失败：%1").arg(reply->errorString()), 5000);
            return;
        }
        clearDesktopSession();
        showStatus(QStringLiteral("已退出登录"), 2500);
    });
}

void MainWindow::clearDesktopSession()
{
    m_csrfToken.clear();
    m_loveStartDate.clear();
    m_userId = 0;
    m_togetherJoined = false;
    m_watchPollInFlight = false;
    m_lastWatchEventId = 0;
    m_currentWatchMediaId = 0;
    m_pendingRemoteState = false;
    m_pendingLocalPosition = -1;
    m_currentEpisodeIndex = -1;
    m_episodePlayUrls.clear();
    m_episodeNames.clear();
    m_episodeMediaIds.clear();
    if (m_watchPollTimer) m_watchPollTimer->stop();
    if (m_watchHeartbeatTimer) m_watchHeartbeatTimer->stop();
    stopMpvPlayback();
    if (m_player) m_player->stop();
    if (m_chatPanel) m_chatPanel->setVisible(false);
    if (m_chatList) m_chatList->clear();
    if (m_joinTogetherButton) m_joinTogetherButton->setEnabled(true);
    if (m_leaveTogetherButton) m_leaveTogetherButton->setEnabled(false);
    if (m_connectionLabel) m_connectionLabel->setText(QStringLiteral("未登录"));
    if (m_homeSummaryLabel) m_homeSummaryLabel->setText(QStringLiteral("请连接并登录网页端后查看情侣空间"));
    updateLoveCounter();
    if (m_togetherStateLabel) m_togetherStateLabel->setText(QStringLiteral("尚未加入一起看房间"));
    if (m_libraryDetailLabel) m_libraryDetailLabel->setText(QStringLiteral("登录后选择媒体查看简介和选集。"));
    if (m_statusLabel) m_statusLabel->setText(QStringLiteral("请连接网页端，或打开本地视频"));
    m_hoveredMediaItem = nullptr;
    if (m_mediaList) m_mediaList->clear();
    if (m_episodeList) m_episodeList->clear();
    if (m_historyList) m_historyList->clear();
    if (m_previousEpisodeButton) m_previousEpisodeButton->setEnabled(false);
    if (m_nextEpisodeButton) m_nextEpisodeButton->setEnabled(false);
    if (m_passwordEdit) m_passwordEdit->clear();
    if (m_usernameEdit) m_usernameEdit->clear();
    QSettings settings(QStringLiteral("withU"), QStringLiteral("withU Desktop"));
    settings.remove(QStringLiteral("username"));
    settings.remove(QStringLiteral("password"));
    settings.sync();
    if (m_network && m_network->cookieJar()) {
        if (auto *jar = dynamic_cast<PersistentCookieJar *>(m_network->cookieJar())) {
            jar->clear();
        }
    }
}

void MainWindow::joinTogether()
{
    if (m_csrfToken.isEmpty()) {
        showStatus(QStringLiteral("请先连接并登录网页端"), 0);
        connectToServer();
        return;
    }

    QNetworkRequest request(apiUrl(QStringLiteral("/api/watch.php?action=default")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    if (m_currentWatchMediaId > 0) {
        body.insert(QStringLiteral("media_id"), m_currentWatchMediaId);
    }

    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            showStatus(QStringLiteral("加入一起看失败：%1").arg(reply->errorString()), 0);
            return;
        }
        const QJsonDocument doc = QJsonDocument::fromJson(reply->readAll());
        if (!doc.isObject()) {
            showStatus(QStringLiteral("一起看接口返回不是有效 JSON"), 0);
            return;
        }
        const QJsonObject root = doc.object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            if (root.value(QStringLiteral("choice_required")).toBool(false)) {
                showStatus(QStringLiteral("网页端另一方正在观看其他影片，请先在网页端确认切换"), 0);
            } else {
                showStatus(root.value(QStringLiteral("message")).toString(QStringLiteral("加入一起看失败")), 0);
            }
            return;
        }
        m_togetherJoined = true;
        m_lastWatchEventId = root.value(QStringLiteral("last_event_id")).toInt(m_lastWatchEventId);
        if (m_chatPanel) m_chatPanel->setVisible(true);
        if (m_joinTogetherButton) m_joinTogetherButton->setEnabled(false);
        if (m_leaveTogetherButton) m_leaveTogetherButton->setEnabled(true);
        if (m_watchPollTimer) m_watchPollTimer->start();
        if (m_watchHeartbeatTimer) m_watchHeartbeatTimer->start();
        showStatus(QStringLiteral("已加入一起看，正在同步网页端状态"), 4000);
        pollTogether();
    });
}

void MainWindow::pollTogether()
{
    if (!m_togetherJoined || m_watchPollInFlight) {
        if (m_togetherStateLabel) {
            m_togetherStateLabel->setText(QStringLiteral("尚未加入一起看房间"));
        }
        return;
    }

    m_watchPollInFlight = true;

    const QString query = QStringLiteral("/api/watch.php?action=poll&room=%1&since=%2")
        .arg(QString::fromLatin1(QUrl::toPercentEncoding(m_watchRoomCode)))
        .arg(m_lastWatchEventId);
    QNetworkRequest request(apiUrl(query));
    QNetworkReply *reply = m_network->get(request);
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        m_watchPollInFlight = false;
        if (reply->error() != QNetworkReply::NoError) {
            return;
        }
        const QJsonDocument doc = QJsonDocument::fromJson(reply->readAll());
        if (!doc.isObject()) {
            return;
        }
        const QJsonObject root = doc.object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            if (root.value(QStringLiteral("message")).toString().contains(QStringLiteral("房间"))) {
                leaveTogether();
            }
            return;
        }
        handleTogetherEvents(root.value(QStringLiteral("events")).toArray());
        applyTogetherState(root);
    });
}

void MainWindow::heartbeatTogether()
{
    if (!m_togetherJoined || m_csrfToken.isEmpty()) {
        return;
    }
    QNetworkRequest request(apiUrl(QStringLiteral("/api/watch.php?action=heartbeat")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    body.insert(QStringLiteral("room_code"), m_watchRoomCode);
    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    connect(reply, &QNetworkReply::finished, reply, &QNetworkReply::deleteLater);
}

void MainWindow::leaveTogether()
{
    if (!m_togetherJoined) {
        return;
    }
    QNetworkRequest request(apiUrl(QStringLiteral("/api/watch.php?action=end_together")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    body.insert(QStringLiteral("room_code"), m_watchRoomCode);
    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        m_togetherJoined = false;
        m_watchPollInFlight = false;
        m_lastWatchEventId = 0;
        if (m_chatPanel) m_chatPanel->setVisible(false);
        if (m_chatList) m_chatList->clear();
        if (m_watchPollTimer) m_watchPollTimer->stop();
        if (m_watchHeartbeatTimer) m_watchHeartbeatTimer->stop();
        if (m_joinTogetherButton) m_joinTogetherButton->setEnabled(true);
        if (m_leaveTogetherButton) m_leaveTogetherButton->setEnabled(false);
        if (m_togetherStateLabel) m_togetherStateLabel->setText(QStringLiteral("已结束一起看，当前仅自己观看"));
        showStatus(QStringLiteral("已结束一起看"), 3000);
    });
}

void MainWindow::sendTogetherEvent(const QString &eventType)
{
    sendTogetherEvent(eventType, QJsonObject());
}

void MainWindow::sendTogetherEvent(const QString &eventType, const QJsonObject &payload)
{
    if (!m_togetherJoined || m_applyingRemote || m_csrfToken.isEmpty() || (!m_player && !m_usingMpv)) {
        return;
    }
    QNetworkRequest request(apiUrl(QStringLiteral("/api/watch.php?action=event")));
    request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
    QJsonObject body;
    body.insert(QStringLiteral("_token"), m_csrfToken);
    body.insert(QStringLiteral("room_code"), m_watchRoomCode);
    body.insert(QStringLiteral("event_type"), eventType);
    body.insert(QStringLiteral("position_ms"), static_cast<qint64>(m_usingMpv ? m_mpvPosition : m_player->position()));
    body.insert(QStringLiteral("speed"), m_usingMpv ? m_mpvRate : m_player->playbackRate());
    body.insert(QStringLiteral("client_timestamp_ms"), QDateTime::currentMSecsSinceEpoch());
    if (!payload.isEmpty()) {
        body.insert(QStringLiteral("payload"), QString::fromUtf8(QJsonDocument(payload).toJson(QJsonDocument::Compact)));
    }
    QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
    connect(reply, &QNetworkReply::finished, reply, &QNetworkReply::deleteLater);
}

void MainWindow::sendChatMessage()
{
    if (!m_togetherJoined || !m_chatEdit) {
        showStatus(QStringLiteral("请先加入一起看"), 2500);
        return;
    }
    const QString text = m_chatEdit->text().trimmed();
    if (text.isEmpty()) return;

    QJsonObject payload;
    payload.insert(QStringLiteral("text"), text.left(200));
    payload.insert(QStringLiteral("kind"), QStringLiteral("side_chat"));
    payload.insert(QStringLiteral("nonce"), QString::number(QDateTime::currentMSecsSinceEpoch()));
    appendChatMessage(payload.value(QStringLiteral("text")).toString(), true);
    m_chatEdit->clear();
    sendTogetherEvent(QStringLiteral("chat_message"), payload);
}

void MainWindow::appendChatMessage(const QString &text, bool mine)
{
    if (!m_chatList || text.trimmed().isEmpty()) return;
    auto *item = new QListWidgetItem(QStringLiteral("%1：%2")
        .arg(mine ? QStringLiteral("我") : QStringLiteral("宝宝"), text.trimmed()), m_chatList);
    item->setTextAlignment(mine ? Qt::AlignRight : Qt::AlignLeft);
    m_chatList->scrollToBottom();
}

void MainWindow::handleTogetherEvents(const QJsonArray &events)
{
    for (const QJsonValue &value : events) {
        const QJsonObject event = value.toObject();
        if (event.value(QStringLiteral("event_type")).toString() != QStringLiteral("chat_message")) continue;
        if (m_userId > 0 && event.value(QStringLiteral("user_id")).toInt() == m_userId) continue;

        QJsonObject outer;
        const QJsonValue rawPayload = event.value(QStringLiteral("payload"));
        if (rawPayload.isString()) {
            const QJsonDocument document = QJsonDocument::fromJson(rawPayload.toString().toUtf8());
            if (document.isObject()) outer = document.object();
        } else if (rawPayload.isObject()) {
            outer = rawPayload.toObject();
        }
        QJsonObject message = outer;
        if (outer.value(QStringLiteral("payload")).isString()) {
            const QJsonDocument nested = QJsonDocument::fromJson(outer.value(QStringLiteral("payload")).toString().toUtf8());
            if (nested.isObject()) message = nested.object();
        }
        const QString text = message.value(QStringLiteral("text")).toString().trimmed();
        if (!text.isEmpty()) appendChatMessage(text, false);
    }
}

void MainWindow::applyTogetherState(const QJsonObject &root)
{
    const QJsonObject room = root.value(QStringLiteral("room")).toObject();
    if (room.isEmpty()) {
        return;
    }

    m_watchRoomCode = room.value(QStringLiteral("code")).toString(m_watchRoomCode);
    m_lastWatchEventId = qMax(m_lastWatchEventId, root.value(QStringLiteral("last_event_id")).toInt());
    const int mediaId = room.value(QStringLiteral("media_id")).toInt();
    const bool playing = room.value(QStringLiteral("playback_state")).toString(QStringLiteral("paused")) == QStringLiteral("playing");
    const double speed = qBound(0.5, room.value(QStringLiteral("speed")).toDouble(1.0), 3.0);
    qint64 remotePosition = room.value(QStringLiteral("position_ms")).toVariant().toLongLong();
    const qint64 serverNow = root.value(QStringLiteral("server_now_ms")).toVariant().toLongLong();
    const qint64 lastSync = room.value(QStringLiteral("last_sync_unix_ms")).toVariant().toLongLong();
    if (playing && serverNow > lastSync && lastSync > 0) {
        remotePosition += static_cast<qint64>(
            qBound<qint64>(qint64(0), serverNow - lastSync, qint64(1500)) * speed
        );
    }
    remotePosition = qMax<qint64>(0, remotePosition);

    if (m_togetherStateLabel) {
        const QString title = room.value(QStringLiteral("series_name")).toString(room.value(QStringLiteral("file_name")).toString(QStringLiteral("当前影片")));
        m_togetherStateLabel->setText(QStringLiteral("一起看 · %1\n%2 · %3")
            .arg(title)
            .arg(playing ? QStringLiteral("播放中") : QStringLiteral("已暂停"))
            .arg(formatTime(remotePosition)));
    }

    const QString urlText = room.value(QStringLiteral("url")).toString();
    if (mediaId > 0 && mediaId != m_currentWatchMediaId && !urlText.isEmpty()) {
        m_currentWatchMediaId = mediaId;
        m_pendingRemoteState = true;
        m_pendingRemotePlaying = playing;
        m_pendingRemotePosition = remotePosition;
        m_pendingRemoteSpeed = speed;
        m_applyingRemote = true;
        const QUrl source = urlText.startsWith('/')
            ? apiUrl(urlText)
            : QUrl::fromUserInput(urlText);
        applySource(source, false);
        return;
    }

    if ((!m_player && !m_usingMpv) || m_pendingRemoteState) {
        return;
    }

    const qint64 localPosition = m_usingMpv ? m_mpvPosition : m_player->position();
    const qint64 drift = qAbs(localPosition - remotePosition);
    const bool localPlaying = m_usingMpv ? m_mpvPlaying : m_player->playbackState() == QMediaPlayer::PlayingState;
    m_applyingRemote = true;
    const double localSpeed = m_usingMpv ? m_mpvRate : m_player->playbackRate();
    if (qAbs(localSpeed - speed) > 0.05) {
        if (m_usingMpv) {
            m_mpvRate = speed;
            sendMpvCommand(QByteArray("rate ") + QByteArray::number(speed, 'f', 2));
        } else {
            m_player->setPlaybackRate(speed);
        }
    }
    if (drift > m_syncThresholdMs) {
        if (m_usingMpv) {
            m_mpvPosition = remotePosition;
            sendMpvCommand(QByteArray("seek ") + QByteArray::number(remotePosition / 1000));
        } else {
            m_player->setPosition(remotePosition);
        }
    }
    if (playing && !localPlaying) {
        if (m_usingMpv) sendMpvCommand("play"); else m_player->play();
        m_mpvPlaying = true;
    } else if (!playing && localPlaying) {
        if (m_usingMpv) sendMpvCommand("pause"); else m_player->pause();
        m_mpvPlaying = false;
    }
    QTimer::singleShot(180, this, [this]() { m_applyingRemote = false; });
}

void MainWindow::applyBootstrapData(const QJsonObject &root)
{
    const QJsonObject summary = root.value(QStringLiteral("summary")).toObject();
    const QJsonObject theme = root.value(QStringLiteral("theme")).toObject();
    const QJsonObject user = root.value(QStringLiteral("user")).toObject();
    const QJsonObject partner = root.value(QStringLiteral("partner")).toObject();
    const QJsonObject watchConfig = root.value(QStringLiteral("watch_config")).toObject();
    m_csrfToken = root.value(QStringLiteral("csrf_token")).toString();
    m_userId = user.value(QStringLiteral("id")).toInt();
    if (auto *primaryName = findChild<QLabel *>(QStringLiteral("homePrimaryName"))) {
        primaryName->setText(user.value(QStringLiteral("nickname")).toString(user.value(QStringLiteral("username")).toString(QStringLiteral("我"))));
    }
    if (auto *partnerName = findChild<QLabel *>(QStringLiteral("homePartnerName"))) {
        partnerName->setText(partner.value(QStringLiteral("nickname")).toString(QStringLiteral("宝宝")));
    }
    auto loadHomeAvatar = [this](QLabel *target, const QString &rawUrl) {
        if (!target || rawUrl.trimmed().isEmpty() || !m_network) return;
        const QString value = rawUrl.trimmed();
        const QUrl url = value.startsWith('/') ? apiUrl(value) : QUrl::fromUserInput(value);
        if (!url.isValid() || url.isEmpty()) return;
        QNetworkReply *reply = m_network->get(QNetworkRequest(url));
        connect(reply, &QNetworkReply::finished, this, [reply, target]() {
            reply->deleteLater();
            if (reply->error() != QNetworkReply::NoError) return;
            QPixmap avatar;
            if (!avatar.loadFromData(reply->readAll()) || avatar.isNull()) return;
            target->setProperty("avatarPixmap", QVariant::fromValue(avatar));
            target->setPixmap(avatar.scaled(target->size(), Qt::KeepAspectRatioByExpanding, Qt::SmoothTransformation));
        });
    };
    loadHomeAvatar(m_heroAvatarPrimary, user.value(QStringLiteral("avatar")).toString());
    loadHomeAvatar(m_heroAvatarPartner, partner.value(QStringLiteral("avatar")).toString());
    applyTheme(theme);
    if (m_watchPollTimer) {
        m_watchPollTimer->setInterval(qBound(300, watchConfig.value(QStringLiteral("poll_interval_ms")).toInt(500), 3000));
    }
    if (m_watchHeartbeatTimer) {
        m_watchHeartbeatTimer->setInterval(qBound(1000, watchConfig.value(QStringLiteral("heartbeat_interval_ms")).toInt(2500), 10000));
    }
    m_syncThresholdMs = qBound<qint64>(qint64(500), qint64(watchConfig.value(QStringLiteral("sync_threshold_ms")).toInt(1000)), qint64(5000));
    m_autoplayEnabled = watchConfig.value(QStringLiteral("autoplay_enabled")).toBool(true);

    if (m_connectionLabel) {
        const QString userText = user.isEmpty()
            ? QStringLiteral("未登录")
            : QStringLiteral("%1（%2）").arg(user.value(QStringLiteral("nickname")).toString(user.value(QStringLiteral("username")).toString()), user.value(QStringLiteral("role")).toString());
        m_connectionLabel->setText(QStringLiteral("账号：%1\n媒体：%2 条 / 已识别 %3 条\n房间：%4")
            .arg(userText)
            .arg(summary.value(QStringLiteral("media_count")).toInt())
            .arg(summary.value(QStringLiteral("recognized_media_count")).toInt())
            .arg(summary.value(QStringLiteral("watch_room_count")).toInt()));
    }

    if (root.value(QStringLiteral("logged_in")).toBool(false)) {
        requestCoupleSpace();
        loadTravelPlans();
        loadMessages();
        loadHistory();
        requestLibrary();
    } else if (m_homeSummaryLabel) {
        m_homeSummaryLabel->setText(QStringLiteral("请在设置中连接并登录网页端后查看情侣空间"));
    }
}

void MainWindow::requestCoupleSpace()
{
    QNetworkRequest request(apiUrl(QStringLiteral("/api/home.php")));
    QNetworkReply *reply = m_network->get(request);
    connect(reply, &QNetworkReply::finished, this, [this, reply]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            if (m_homeSummaryLabel) m_homeSummaryLabel->setText(QStringLiteral("情侣空间暂时无法连接"));
            return;
        }
        const QJsonDocument document = QJsonDocument::fromJson(reply->readAll());
        if (!document.isObject()) {
            if (m_homeSummaryLabel) m_homeSummaryLabel->setText(QStringLiteral("情侣空间返回格式无效"));
            return;
        }
        const QJsonObject root = document.object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            if (m_homeSummaryLabel) m_homeSummaryLabel->setText(root.value(QStringLiteral("message")).toString(QStringLiteral("情侣空间读取失败")));
            return;
        }
        applyCoupleSpace(root);
    });
}

void MainWindow::applyCoupleSpace(const QJsonObject &root)
{
    const QJsonObject stats = root.value(QStringLiteral("stats")).toObject();
    const int articleCount = stats.value(QStringLiteral("articles")).toInt();
    const int albumCount = stats.value(QStringLiteral("albums")).toInt();
    const int eventCount = stats.value(QStringLiteral("events")).toInt();
    const int messageCount = stats.value(QStringLiteral("messages")).toInt();
    const QString loveDate = root.value(QStringLiteral("love_start_date")).toString();
    m_loveStartDate = loveDate;
    if (m_statArticles) m_statArticles->setText(QString::number(articleCount));
    if (m_statAlbums) m_statAlbums->setText(QString::number(albumCount));
    if (m_statEvents) m_statEvents->setText(QString::number(eventCount));
    if (m_statMessages) m_statMessages->setText(QString::number(messageCount));
    updateLoveCounter();
    if (m_homeSummaryLabel) {
        const QString loveText = loveDate.isEmpty()
            ? QStringLiteral("恋爱开始日期尚未设置")
            : QStringLiteral("恋爱开始于 %1").arg(loveDate);
        m_homeSummaryLabel->setText(QStringLiteral("%1 · 文章 %2 · 相册 %3 · 纪念日 %4 · 留言 %5")
            .arg(loveText).arg(articleCount).arg(albumCount).arg(eventCount).arg(messageCount));
    }
    if (!m_homeFeedList) return;
    m_homeFeedList->clear();

    const QJsonArray articles = root.value(QStringLiteral("articles")).toArray();
    for (const QJsonValue &value : articles) {
        const QJsonObject article = value.toObject();
        const QString title = article.value(QStringLiteral("title")).toString(QStringLiteral("未命名文章"));
        const QString time = article.value(QStringLiteral("created_at_text")).toString();
        auto *feedItem = new QListWidgetItem(QStringLiteral("文章 · %1%2")
            .arg(title, time.isEmpty() ? QString() : QStringLiteral(" · ") + time), m_homeFeedList);
        feedItem->setData(Qt::UserRole, QStringLiteral("article"));
        feedItem->setData(Qt::UserRole + 1, article.value(QStringLiteral("id")).toInt());
    }
    const QJsonArray albums = root.value(QStringLiteral("albums")).toArray();
    for (const QJsonValue &value : albums) {
        const QJsonObject album = value.toObject();
        const QString name = album.value(QStringLiteral("display_name")).toString(album.value(QStringLiteral("name")).toString(QStringLiteral("未命名相册")));
        auto *feedItem = new QListWidgetItem(QStringLiteral("相册 · %1 · %2 张照片")
            .arg(name).arg(album.value(QStringLiteral("image_count")).toInt()), m_homeFeedList);
        feedItem->setData(Qt::UserRole, QStringLiteral("album"));
        feedItem->setData(Qt::UserRole + 1, album.value(QStringLiteral("id")).toInt());
    }
    const QJsonArray messages = root.value(QStringLiteral("latest_messages")).toArray();
    for (const QJsonValue &value : messages) {
        const QJsonObject message = value.toObject();
        m_homeFeedList->addItem(QStringLiteral("留言 · %1：%2")
            .arg(message.value(QStringLiteral("nickname")).toString(QStringLiteral("匿名用户")),
                 message.value(QStringLiteral("content")).toString()));
    }
    if (m_homeFeedList->count() == 0) {
        m_homeFeedList->addItem(QStringLiteral("还没有新的情侣空间动态"));
    }
}

void MainWindow::openCoupleItem(QListWidgetItem *item)
{
    if (!item) return;
    const QString type = item->data(Qt::UserRole).toString();
    const int id = item->data(Qt::UserRole + 1).toInt();
    if ((type != QStringLiteral("article") && type != QStringLiteral("album")) || id <= 0) {
        return;
    }
    switchSection(5);
    requestCoupleContent(type, id);
}

void MainWindow::requestCoupleContent(const QString &type, int id)
{
    if (!m_contentView || id <= 0) return;
    m_contentTitle->setText(QStringLiteral("正在加载内容…"));
    m_contentView->setHtml(QStringLiteral("<p>正在从网页端读取，请稍候…</p>"));
    const QString query = QStringLiteral("/api/desktop.php?action=%1&id=%2").arg(type).arg(id);
    QNetworkRequest request(apiUrl(query));
    QNetworkReply *reply = m_network->get(request);
    connect(reply, &QNetworkReply::finished, this, [this, reply, type]() {
        reply->deleteLater();
        if (reply->error() != QNetworkReply::NoError) {
            m_contentTitle->setText(QStringLiteral("内容详情"));
            m_contentView->setHtml(QStringLiteral("<p>内容读取失败：%1</p>").arg(reply->errorString().toHtmlEscaped()));
            return;
        }
        const QJsonDocument document = QJsonDocument::fromJson(reply->readAll());
        const QJsonObject root = document.object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            m_contentTitle->setText(QStringLiteral("内容详情"));
            m_contentView->setHtml(QStringLiteral("<p>%1</p>").arg(root.value(QStringLiteral("message")).toString(QStringLiteral("内容读取失败")).toHtmlEscaped()));
            return;
        }
        const QJsonObject item = root.value(QStringLiteral("item")).toObject();
        if (type == QStringLiteral("article")) {
            const QString title = item.value(QStringLiteral("title")).toString(QStringLiteral("文章详情"));
            const QString meta = item.value(QStringLiteral("nickname")).toString();
            m_contentTitle->setText(title);
            const QString html = QStringLiteral("<h2>%1</h2><p style='color:#718087'>%2 · %3</p><hr>%4")
                .arg(title.toHtmlEscaped(), meta.toHtmlEscaped(), item.value(QStringLiteral("created_at")).toString().toHtmlEscaped(), item.value(QStringLiteral("content")).toString());
            m_contentView->setHtml(html);
            return;
        }
        const QString name = item.value(QStringLiteral("name")).toString(QStringLiteral("相册详情"));
        m_contentTitle->setText(name);
        QString html = QStringLiteral("<h2>%1</h2><p>%2</p><hr>")
            .arg(name.toHtmlEscaped(), item.value(QStringLiteral("description")).toString().toHtmlEscaped());
        for (const QJsonValue &image : item.value(QStringLiteral("images")).toArray()) {
            const QJsonObject imageObject = image.toObject();
            const QString url = imageObject.value(QStringLiteral("url")).toString();
            if (url.isEmpty()) continue;
            html += QStringLiteral("<p><img src=\"%1\" style=\"max-width:100%%;border-radius:12px;\"><br><span>%2</span></p>")
                .arg(url.toHtmlEscaped(), imageObject.value(QStringLiteral("description")).toString().toHtmlEscaped());
        }
        m_contentView->setHtml(html);
    });
}

void MainWindow::applyTheme(const QJsonObject &theme)
{
    const QJsonObject colors = theme.value(QStringLiteral("colors")).toObject();
    auto themeColor = [&colors](const QString &name, const QColor &fallback) {
        const QColor color(colors.value(name).toString());
        return color.isValid() ? color : fallback;
    };

    const QString mode = theme.value(QStringLiteral("mode")).toString(QStringLiteral("auto"));
    const bool dark = mode == QStringLiteral("dark")
        || (mode == QStringLiteral("auto")
            && qApp->palette().color(QPalette::Window).lightness() < 128);

    const QColor primary = themeColor(QStringLiteral("primary"), QColor(QStringLiteral("#f5b6c8")));
    const QColor secondary = themeColor(QStringLiteral("secondary"), QColor(QStringLiteral("#b9e3d0")));
    const QColor accent = themeColor(QStringLiteral("accent"), QColor(QStringLiteral("#b8ddf2")));
    const QColor bg = dark ? QColor(QStringLiteral("#101716")) : QColor(QStringLiteral("#f8fbfa"));
    const QColor surface = dark ? QColor(QStringLiteral("#18211f")) : QColor(QStringLiteral("#ffffff"));
    const QColor text = dark ? QColor(QStringLiteral("#f4f8f6")) : QColor(QStringLiteral("#263238"));
    const QColor muted = dark ? QColor(QStringLiteral("#b4c2bc")) : QColor(QStringLiteral("#718087"));
    const QColor border = dark ? QColor(QStringLiteral("#2d3b36")) : QColor(QStringLiteral("#e2ebe7"));
    const QColor primaryStrong = dark ? primary.lighter(135) : primary.darker(110);
    const QColor selected = dark ? primary.darker(145) : primary.lighter(150);

    QPalette palette = qApp->palette();
    palette.setColor(QPalette::Window, bg);
    palette.setColor(QPalette::Base, surface);
    palette.setColor(QPalette::AlternateBase, dark ? surface.lighter(115) : surface.darker(103));
    palette.setColor(QPalette::Text, text);
    palette.setColor(QPalette::WindowText, text);
    palette.setColor(QPalette::Button, surface);
    palette.setColor(QPalette::ButtonText, text);
    palette.setColor(QPalette::Highlight, primaryStrong);
    palette.setColor(QPalette::HighlightedText, dark ? QColor(QStringLiteral("#101716")) : text);
    palette.setColor(QPalette::PlaceholderText, muted);
    qApp->setPalette(palette);

    const QString marker = QStringLiteral("/* withU dynamic theme */");
    QString stylesheet = styleSheet();
    const int markerIndex = stylesheet.indexOf(marker);
    if (markerIndex >= 0) {
        stylesheet = stylesheet.left(markerIndex);
    }
    stylesheet += marker + QStringLiteral("\n")
        + QStringLiteral("QMainWindow{background:%1;color:%2;} QWidget{color:%2;} ")
            .arg(qssRgb(bg), qssRgb(text))
        + QStringLiteral("#topNav,#mainNav{background:%1;border-color:%2;} #heroHeader{background:qlineargradient(x1:0,y1:0,x2:1,y2:1,stop:0 %3,stop:0.52 %4,stop:1 %5);border-color:%2;} ")
            .arg(qssRgba(surface, dark ? 220 : 230), qssRgb(border), qssRgba(primary, dark ? 100 : 185), qssRgba(surface, dark ? 225 : 238), qssRgba(accent, dark ? 100 : 175))
        + QStringLiteral("#sidebar,#librarySidebar{background:qlineargradient(x1:0,y1:0,x2:1,y2:1,stop:0 %1,stop:1 %2);border-color:%3;} ")
            .arg(qssRgba(primary, dark ? 90 : 185), qssRgba(accent, dark ? 82 : 155), qssRgb(border))
        + QStringLiteral("#glassCard,#metricCard{background:%1;border-color:%2;} ")
            .arg(qssRgba(surface, dark ? 220 : 198), qssRgb(border))
        + QStringLiteral("#libraryFilterPanel,#librarySidebar{background:%1;border-color:%2;} #libraryFilterPanel QPushButton:checked,#librarySidebar QPushButton:checked{background:%3;border-color:%4;color:%5;} ")
            .arg(qssRgba(surface, dark ? 225 : 195), qssRgb(border), qssRgba(primary, dark ? 155 : 120), qssRgb(primaryStrong), qssRgb(dark ? QColor(QStringLiteral("#101716")) : text))
        + QStringLiteral("#loveCounter{background:%1;border-color:%2;} #loveCounterTitle{color:%3;} #loveCounterValue{color:%4;} ")
            .arg(qssRgba(primary, dark ? 45 : 28), qssRgb(border), qssRgb(primaryStrong), qssRgb(text))
        + QStringLiteral("#brandTitle,#metricValue{color:%1;} #pageTitle{color:%2;} #mutedLabel{color:%3;} ")
            .arg(qssRgb(primaryStrong), qssRgb(text), qssRgb(muted))
        + QStringLiteral("#homeWatchAction{background:qlineargradient(x1:0,y1:0,x2:1,y2:0,stop:0 %1,stop:1 %2);border-color:%3;} #homeWatchActionTitle{color:%4;} #primaryButton{background:%5;border-color:%6;color:%7;} #primaryButton:hover{background:%8;border-color:%9;} ")
            .arg(qssRgba(primary, dark ? 65 : 42), qssRgba(accent, dark ? 58 : 38), qssRgb(border), qssRgb(text), qssRgb(primaryStrong), qssRgb(primaryStrong), qssRgb(dark ? QColor(QStringLiteral("#101716")) : QColor(QStringLiteral("#ffffff"))), qssRgb(primary), qssRgb(primaryStrong))
        + QStringLiteral("QPushButton{background:%1;border-color:%2;color:%3;} QPushButton:hover{background:%4;border-color:%5;} QPushButton:pressed{background:%6;} ")
            .arg(qssRgba(surface, dark ? 225 : 215), qssRgb(border), qssRgb(text), qssRgba(primary, dark ? 125 : 75), qssRgb(primaryStrong), qssRgba(primary, dark ? 175 : 105))
        + QStringLiteral("QPushButton:checked{background:qlineargradient(x1:0,y1:0,x2:1,y2:0,stop:0 %1,stop:1 %2);border-color:%3;color:%4;} ")
            .arg(qssRgb(primary), qssRgb(accent), qssRgb(primaryStrong), qssRgb(dark ? QColor(QStringLiteral("#101716")) : text))
        + QStringLiteral("QLineEdit,QDoubleSpinBox{background:%1;border-color:%2;color:%3;} QListWidget{background:%4;border-color:%2;color:%3;} QListWidget::item:selected{background:%5;color:%3;} ")
            .arg(qssRgba(surface, dark ? 238 : 220), qssRgb(border), qssRgb(text), qssRgba(surface, dark ? 225 : 195), qssRgba(selected, dark ? 180 : 120))
        + QStringLiteral("QSlider::groove:horizontal{background:%1;} QSlider::handle:horizontal{background:%2;} QStatusBar{color:%3;}")
            .arg(qssRgb(border), qssRgb(primaryStrong), qssRgb(muted));
    setStyleSheet(stylesheet);
}

void MainWindow::requestLibrary(const QString &query)
{
    const int requestSerial = ++m_libraryRequestSerial;
    QString path = QStringLiteral("/api/desktop.php?action=library&page=1&per_page=240&type_id=%1").arg(m_libraryTypeId);
    if (!query.trimmed().isEmpty()) {
        path += QStringLiteral("&q=") + QString::fromLatin1(QUrl::toPercentEncoding(query.trimmed()));
    }
    QNetworkRequest request(apiUrl(path));
    QNetworkReply *reply = m_network->get(request);
    connect(reply, &QNetworkReply::finished, this, [this, reply, requestSerial]() {
        reply->deleteLater();
        if (requestSerial != m_libraryRequestSerial) return;
        if (reply->error() != QNetworkReply::NoError) {
            showStatus(QStringLiteral("媒体库读取失败：%1").arg(reply->errorString()), 0);
            return;
        }
        const QJsonDocument doc = QJsonDocument::fromJson(reply->readAll());
        if (!doc.isObject()) {
            showStatus(QStringLiteral("媒体库返回不是有效 JSON"), 0);
            return;
        }
        const QJsonObject root = doc.object();
        if (!root.value(QStringLiteral("success")).toBool(false)) {
            showStatus(root.value(QStringLiteral("message")).toString(QStringLiteral("媒体库读取失败")), 0);
            return;
        }
        if (!m_mediaList) {
            return;
        }
        const QJsonArray items = root.value(QStringLiteral("items")).toArray();
        m_hoveredMediaItem = nullptr;
        m_mediaList->clear();
        if (m_episodeList) {
            m_episodeList->clear();
            m_episodeList->addItem(QStringLiteral("暂无选集"));
        }
        m_episodePlayUrls.clear();
        m_episodeNames.clear();
        m_episodeMediaIds.clear();
        m_currentEpisodeIndex = -1;
        if (m_previousEpisodeButton) m_previousEpisodeButton->setEnabled(false);
        if (m_nextEpisodeButton) m_nextEpisodeButton->setEnabled(false);
        if (m_libraryDetailLabel) {
            m_libraryDetailLabel->setText(QStringLiteral("选择左侧媒体后显示简介、状态和选集。"));
        }
        if (items.isEmpty()) {
            m_mediaList->addItem(QStringLiteral("媒体库暂无可播放资源"));
            return;
        }
        for (const QJsonValue &value : items) {
            const QJsonObject item = value.toObject();
            const QString name = item.value(QStringLiteral("name")).toString(QStringLiteral("未命名"));
            const int count = item.value(QStringLiteral("count")).toInt(1);
            const QString resolution = item.value(QStringLiteral("resolution")).toString();
            const QString status = item.value(QStringLiteral("recognition_status")).toString(QStringLiteral("pending"));
            QString line = count > 1 ? QStringLiteral("%1\n%2 集").arg(name).arg(count) : name;
            if (!resolution.isEmpty()) {
                line += QStringLiteral("\n%1").arg(resolution);
            }
            if (status != QStringLiteral("recognized")) {
                line += QStringLiteral("\n待识别");
            }
            auto *listItem = new QListWidgetItem(line, m_mediaList);
            listItem->setTextAlignment(Qt::AlignHCenter | Qt::AlignTop);
            listItem->setToolTip(name + (resolution.isEmpty() ? QString() : QStringLiteral(" · ") + resolution));
            listItem->setData(Qt::UserRole + 3, line);
            listItem->setData(Qt::UserRole, item.value(QStringLiteral("play_url")).toString());
            listItem->setData(Qt::UserRole + 1, name);
            listItem->setData(Qt::UserRole + 2, QString::fromUtf8(QJsonDocument(item).toJson(QJsonDocument::Compact)));

            QPixmap placeholder(132, 176);
            placeholder.fill(QColor(QStringLiteral("#dbe8e2")));
            {
                QPainter painter(&placeholder);
                painter.setPen(QColor(QStringLiteral("#718087")));
                painter.drawText(placeholder.rect(), Qt::AlignCenter, QStringLiteral("withU\n影视库"));
            }
            listItem->setIcon(QIcon(placeholder));
            listItem->setData(Qt::UserRole + 4, QVariant::fromValue(placeholder));
            QString coverUrl = item.value(QStringLiteral("cover_url")).toString().trimmed();
            if (coverUrl.isEmpty()) {
                coverUrl = QStringLiteral("/api/media_cover.php?id=%1").arg(item.value(QStringLiteral("id")).toInt());
            }
            const QUrl cover = coverUrl.startsWith('/') ? apiUrl(coverUrl) : QUrl::fromUserInput(coverUrl);
            if (cover.isValid() && !cover.isEmpty()) {
                QNetworkReply *coverReply = m_network->get(QNetworkRequest(cover));
                connect(coverReply, &QNetworkReply::finished, this, [this, coverReply, listItem, requestSerial]() {
                    coverReply->deleteLater();
                    if (requestSerial != m_libraryRequestSerial || !m_mediaList || coverReply->error() != QNetworkReply::NoError) return;
                    if (m_mediaList->row(listItem) < 0) return;
                    QPixmap coverPixmap;
                    if (!coverPixmap.loadFromData(coverReply->readAll()) || coverPixmap.isNull()) return;
                    const QPixmap scaled = coverPixmap.scaled(132, 176, Qt::KeepAspectRatioByExpanding, Qt::SmoothTransformation);
                    listItem->setData(Qt::UserRole + 4, QVariant::fromValue(scaled));
                    listItem->setIcon(QIcon(scaled));
                });
            }
        }
        if (m_mediaList->count() > 0) {
            m_mediaList->setCurrentRow(0);
        }
        showStatus(QStringLiteral("媒体库已同步 %1 个条目").arg(items.size()), 4000);
    });
}

void MainWindow::renderLibraryDetail(const QJsonObject &item)
{
    m_currentWatchMediaId = item.value(QStringLiteral("id")).toInt(m_currentWatchMediaId);
    const QString name = item.value(QStringLiteral("name")).toString(QStringLiteral("未命名"));
    const int count = item.value(QStringLiteral("count")).toInt(1);
    const QString status = item.value(QStringLiteral("recognition_status")).toString(QStringLiteral("pending"));
    const QString rating = item.value(QStringLiteral("rating")).toVariant().toString();
    const QString resolution = item.value(QStringLiteral("resolution")).toString();
    QString castText = item.value(QStringLiteral("cast_names")).toString();
    const QJsonDocument castDoc = QJsonDocument::fromJson(castText.toUtf8());
    if (castDoc.isArray()) {
        QStringList names;
        for (const QJsonValue &value : castDoc.array()) {
            const QString actor = value.toString().trimmed();
            if (!actor.isEmpty()) {
                names.append(actor);
            }
        }
        castText = names.join(QStringLiteral("、"));
    }
    const QString summary = item.value(QStringLiteral("summary")).toString();

    QStringList lines;
    lines << QStringLiteral("片名：%1").arg(name);
    lines << QStringLiteral("集数：%1").arg(count);
    lines << QStringLiteral("状态：%1").arg(status == QStringLiteral("recognized") ? QStringLiteral("已识别") : QStringLiteral("待识别"));
    if (!rating.isEmpty()) lines << QStringLiteral("评分：%1").arg(rating);
    if (!resolution.isEmpty()) lines << QStringLiteral("分辨率：%1").arg(resolution);
    if (!castText.isEmpty()) lines << QStringLiteral("主演：%1").arg(castText);
    if (!summary.isEmpty()) lines << QStringLiteral("简介：%1").arg(summary);
    if (m_libraryDetailLabel) {
        m_libraryDetailLabel->setText(lines.join(QStringLiteral("\n")));
    }

    if (!m_episodeList) {
        return;
    }
    m_episodeList->clear();
    m_episodePlayUrls.clear();
    m_episodeNames.clear();
    m_episodeMediaIds.clear();
    m_currentEpisodeIndex = -1;
    const QJsonArray episodes = item.value(QStringLiteral("episodes")).toArray();
    if (episodes.isEmpty()) {
        m_episodeList->addItem(QStringLiteral("暂无选集"));
        if (m_previousEpisodeButton) m_previousEpisodeButton->setEnabled(false);
        if (m_nextEpisodeButton) m_nextEpisodeButton->setEnabled(false);
        return;
    }
    for (const QJsonValue &value : episodes) {
        const QJsonObject episode = value.toObject();
        QString label = episode.value(QStringLiteral("label")).toString(episode.value(QStringLiteral("file_name")).toString(QStringLiteral("未命名")));
        const QString epResolution = episode.value(QStringLiteral("resolution")).toString();
        if (!epResolution.isEmpty()) {
            label += QStringLiteral(" · %1").arg(epResolution);
        }
        if (episode.value(QStringLiteral("recognition_status")).toString(QStringLiteral("pending")) != QStringLiteral("recognized")) {
            label += QStringLiteral(" · 待识别");
        }
        auto *episodeItem = new QListWidgetItem(label, m_episodeList);
        episodeItem->setData(Qt::UserRole, episode.value(QStringLiteral("play_url")).toString());
        episodeItem->setData(Qt::UserRole + 1, QStringLiteral("%1 · %2").arg(name, label));
        episodeItem->setData(Qt::UserRole + 2, episode.value(QStringLiteral("id")).toInt());
        m_episodePlayUrls.append(episode.value(QStringLiteral("play_url")).toString());
        m_episodeNames.append(QStringLiteral("%1 · %2").arg(name, label));
        m_episodeMediaIds.append(episode.value(QStringLiteral("id")).toInt());
        if (episode.value(QStringLiteral("id")).toInt() == m_currentWatchMediaId) {
            m_currentEpisodeIndex = m_episodePlayUrls.size() - 1;
        }
    }
    if (m_previousEpisodeButton) m_previousEpisodeButton->setEnabled(m_currentEpisodeIndex > 0);
    if (m_nextEpisodeButton) m_nextEpisodeButton->setEnabled(m_currentEpisodeIndex >= 0 && m_currentEpisodeIndex + 1 < m_episodePlayUrls.size());
}

void MainWindow::openLibraryMedia(const QString &playUrl, const QString &name, int mediaId, qint64 resumePositionMs)
{
    const QString trimmedUrl = playUrl.trimmed();
    if (trimmedUrl.isEmpty()) {
        return;
    }
    const QUrl source = trimmedUrl.startsWith('/')
        ? apiUrl(trimmedUrl)
        : QUrl::fromUserInput(trimmedUrl);
    if (!source.isValid() || source.isEmpty()) {
        showStatus(QStringLiteral("媒体播放地址无效"), 0);
        return;
    }
    auto startPlayback = [this, source, name, mediaId, resumePositionMs]() {
        m_currentWatchMediaId = mediaId > 0 ? mediaId : m_currentWatchMediaId;
        m_pendingLocalPosition = qMax<qint64>(0, resumePositionMs);
        m_currentEpisodeIndex = -1;
        for (int i = 0; i < m_episodeMediaIds.size(); ++i) {
            if (m_episodeMediaIds.at(i) == m_currentWatchMediaId) {
                m_currentEpisodeIndex = i;
                break;
            }
        }
        if (m_previousEpisodeButton) m_previousEpisodeButton->setEnabled(m_currentEpisodeIndex > 0);
        if (m_nextEpisodeButton) m_nextEpisodeButton->setEnabled(m_currentEpisodeIndex >= 0 && m_currentEpisodeIndex + 1 < m_episodePlayUrls.size());
        switchSection(3);
        applySource(source, m_autoplayEnabled);
        if (!name.trimmed().isEmpty()) {
            showStatus(QStringLiteral("正在播放：%1").arg(name.trimmed()), 5000);
        }
    };

    if (m_togetherJoined && mediaId > 0 && !m_csrfToken.isEmpty()) {
        QNetworkRequest request(apiUrl(QStringLiteral("/api/watch.php?action=default")));
        request.setHeader(QNetworkRequest::ContentTypeHeader, QStringLiteral("application/json; charset=UTF-8"));
        QJsonObject body;
        body.insert(QStringLiteral("_token"), m_csrfToken);
        body.insert(QStringLiteral("media_id"), mediaId);
        QNetworkReply *reply = m_network->post(request, QJsonDocument(body).toJson(QJsonDocument::Compact));
        showStatus(QStringLiteral("正在把选集切换到一起看房间…"), 0);
        connect(reply, &QNetworkReply::finished, this, [this, reply, startPlayback]() {
            reply->deleteLater();
            if (reply->error() != QNetworkReply::NoError) {
                showStatus(QStringLiteral("一起看切集失败：%1").arg(reply->errorString()), 0);
                return;
            }
            const QJsonObject root = QJsonDocument::fromJson(reply->readAll()).object();
            if (!root.value(QStringLiteral("success")).toBool(false)) {
                showStatus(root.value(QStringLiteral("message")).toString(QStringLiteral("一起看切集失败")), 0);
                return;
            }
            startPlayback();
        });
        return;
    }
    startPlayback();
}

void MainWindow::switchSection(int index)
{
    if (!m_pages || index < 0 || index >= m_pages->count()) {
        return;
    }
    m_pages->setCurrentIndex(index);
    // The couple dashboard is an immersive first screen. Keep the legacy
    // navigation chrome available on feature pages, but let the home design
    // occupy the full client surface like the web reference.
    const bool isHome = index == 0;
    const bool isLibrary = index == 2;
    const bool isWebShell = index == 9;
    if (auto *topNav = findChild<QWidget *>(QStringLiteral("topNav"))) topNav->setVisible(!isHome && !isWebShell);
    if (m_globalHero) m_globalHero->setVisible(!isHome && !isLibrary && !isWebShell);
    if (m_globalNav) m_globalNav->setVisible(!isHome && !isLibrary && !isWebShell);
    if (m_librarySidebar) m_librarySidebar->setVisible(isLibrary);
}

void MainWindow::resizeEvent(QResizeEvent *event)
{
    QMainWindow::resizeEvent(event);

    // The desktop shell uses a 16:10 design canvas. When the user restores
    // the window, adjust the opposite edge immediately so both axes stay in
    // lockstep instead of compressing the layout.
    if (!isMaximized() && !isFullScreen() && !m_aspectAdjusting) {
        const QSize current = size();
        if (current.width() > 0 && current.height() > 0) {
            QSize target = current;
            if (!m_lastWindowSize.isValid()
                || qAbs(current.width() - m_lastWindowSize.width()) >= qAbs(current.height() - m_lastWindowSize.height())) {
                target.setHeight(qRound(current.width() / 1.6));
            } else {
                target.setWidth(qRound(current.height() * 1.6));
            }
            if (target != current && target.width() >= minimumWidth() && target.height() >= minimumHeight()) {
                m_aspectAdjusting = true;
                resize(target);
                m_aspectAdjusting = false;
            }
            m_lastWindowSize = target;
        }
    }

    auto *home = findChild<QWidget *>(QStringLiteral("homePage"));
    if (!home || !home->isVisible()) return;

    const qreal scale = qBound<qreal>(0.48, qMin(width() / 1280.0, height() / 800.0), 1.35);
    const int availableWidth = home->width() > 0 ? home->width() : width();
    const int columns = 4;
    auto *cardLayout = home->findChild<QGridLayout *>(QStringLiteral("homeCardLayout"));
    if (cardLayout) {
        const QStringList cardNames = {
            QStringLiteral("homeCardPink"), QStringLiteral("homeCardBlue"),
            QStringLiteral("homeCardGreen"), QStringLiteral("homeCardMint")
        };
        for (int i = 0; i < cardNames.size(); ++i) {
            if (auto *card = home->findChild<QPushButton *>(cardNames[i])) {
                cardLayout->removeWidget(card);
                cardLayout->addWidget(card, i / columns, i % columns);
                card->setFixedHeight(qMax(52, qRound(86 * scale)));
                QFont cardFont = card->font();
                cardFont.setPointSizeF(qMax<qreal>(8.0, 17.0 * scale));
                card->setFont(cardFont);
            }
        }
    }

    if (auto *top = home->findChild<QWidget *>(QStringLiteral("homeTopBar"))) {
        top->setFixedHeight(qMax(42, qRound(72 * scale)));
    }
    if (auto *headerLine = home->findChild<QLabel *>(QStringLiteral("homeHeaderLine"))) {
        headerLine->setVisible(availableWidth >= 560);
        QFont headerFont = headerLine->font();
        headerFont.setPointSizeF(qMax<qreal>(8.0, 13.0 * scale));
        headerLine->setFont(headerFont);
    }
    if (auto *version = home->findChild<QLabel *>(QStringLiteral("homeVersion"))) {
        version->setVisible(availableWidth >= 520);
    }
    const int avatarSize = qBound(68, qRound(114 * scale), 128);
    for (const QString &name : {QStringLiteral("heroAvatarPrimary"), QStringLiteral("heroAvatarPartner")}) {
        if (auto *avatar = home->findChild<QLabel *>(name)) {
            avatar->setFixedSize(avatarSize, avatarSize);
            avatar->setStyleSheet(QStringLiteral(
                "background:rgba(255,255,255,0.88);border:4px solid rgba(255,255,255,0.92);border-radius:%1px;color:#718087;font-size:%2px;font-weight:800;")
                .arg(avatarSize / 2).arg(qBound(15, qRound(22 * scale), 24)));
            const QVariant avatarData = avatar->property("avatarPixmap");
            if (avatarData.canConvert<QPixmap>()) {
                const QPixmap image = avatarData.value<QPixmap>();
                avatar->setPixmap(image.scaled(avatarSize, avatarSize, Qt::KeepAspectRatioByExpanding, Qt::SmoothTransformation));
            }
        }
    }
    if (auto *heart = home->findChild<QWidget *>(QStringLiteral("homeLove"))) {
        const int heartSize = qBound(64, qRound(100 * scale), 112);
        heart->setFixedSize(heartSize, heartSize);
    }
    if (auto *wave = home->findChild<QWidget *>(QStringLiteral("homeWave"))) {
        const int waveWidth = qBound(480, qRound(900 * scale), qMax(480, availableWidth - 80));
        wave->setFixedSize(waveWidth, qMax(156, qRound(224 * scale)));
        if (auto *title = home->findChild<QWidget *>(QStringLiteral("loveCounterTitle"))) {
            QFont titleFont = title->font();
            titleFont.setPointSizeF(qMax<qreal>(12.0, 26.0 * scale));
            title->setFont(titleFont);
        }
        for (const QString &name : {QStringLiteral("homeTimerNumber"), QStringLiteral("homeTimerUnit")}) {
            const auto labels = home->findChildren<QLabel *>(name);
            for (auto *label : labels) {
                QFont timerFont = label->font();
                timerFont.setPointSizeF(qMax<qreal>(9.0, (name == QStringLiteral("homeTimerNumber") ? 50.0 : 20.0) * scale));
                label->setFont(timerFont);
            }
        }
    }
    if (auto *glass = home->findChild<QWidget *>(QStringLiteral("homeCoupleGlass"))) {
        glass->setFixedSize(qBound(560, qRound(980 * scale), qMax(560, availableWidth - 40)), qMax(420, qRound(560 * scale)));
    }
    if (auto *controls = home->findChild<QWidget *>(QStringLiteral("homeControls"))) {
        controls->setFixedHeight(qMax(112, qRound(148 * scale)));
    }
}

void MainWindow::chooseLocalFile()
{
    const QString file = QFileDialog::getOpenFileName(
        this,
        QStringLiteral("打开视频"),
        QString(),
        QStringLiteral("视频文件 (*.mkv *.mp4 *.mov *.avi *.webm *.ts *.m2ts);;所有文件 (*.*)"));
    if (file.isEmpty()) {
        return;
    }
    m_sourceEdit->setText(file);
    openSource();
}

void MainWindow::openSource()
{
    const QString text = m_sourceEdit->text().trimmed();
    if (text.isEmpty()) {
        showStatus(QStringLiteral("请输入文件路径或视频直链"));
        return;
    }

    const QUrl source = QUrl::fromUserInput(text);
    if (!source.isValid() || source.isEmpty()) {
        showStatus(QStringLiteral("视频地址无效"));
        return;
    }
    m_pendingLocalPosition = -1;
    m_episodePlayUrls.clear();
    m_episodeNames.clear();
    m_episodeMediaIds.clear();
    m_currentEpisodeIndex = -1;
    if (m_previousEpisodeButton) m_previousEpisodeButton->setEnabled(false);
    if (m_nextEpisodeButton) m_nextEpisodeButton->setEnabled(false);
    applySource(source);
}

void MainWindow::applySource(const QUrl &source, bool autoplay)
{
    if (!source.isValid() || source.isEmpty()) {
        showStatus(QStringLiteral("视频地址无效"), 0);
        return;
    }

    const QString path = source.path();
    const bool shouldResolve = path == QStringLiteral("/api/media_stream.php")
        || path.endsWith(QStringLiteral("/api/media_stream.php"))
        || path == QStringLiteral("/api/media_resolve.php")
        || path.endsWith(QStringLiteral("/api/media_resolve.php"));
    if (shouldResolve) {
        resolvePlayableSource(source, autoplay);
        return;
    }

    startPlaybackWithSource(source, autoplay);
}

void MainWindow::resolvePlayableSource(const QUrl &source, bool autoplay)
{
    const int resolveSerial = ++m_resolveSerial;
    QUrlQuery query(source);
    const int mediaId = query.queryItemValue(QStringLiteral("id")).toInt();
    if (mediaId <= 0) {
        startPlaybackWithSource(source, autoplay);
        return;
    }

    QUrl resolveUrl = source;
    if (resolveUrl.path().endsWith(QStringLiteral("/api/media_stream.php"))) {
        resolveUrl.setPath(QStringLiteral("/api/media_resolve.php"));
    }
    showStatus(QStringLiteral("正在获取真实播放直链..."), 0);
    auto requestResolve = QSharedPointer<std::function<void(int)>>::create();
    *requestResolve = [this, resolveUrl, autoplay, requestResolve, resolveSerial](int attempt) {
        QNetworkReply *reply = m_network->get(QNetworkRequest(resolveUrl));
        connect(reply, &QNetworkReply::finished, this, [this, reply, autoplay, attempt, requestResolve, resolveSerial]() {
            reply->deleteLater();
            if (resolveSerial != m_resolveSerial) return;
            QString failure;
            QUrl resolved;
            if (reply->error() != QNetworkReply::NoError) {
                failure = QStringLiteral("直链解析请求失败");
            } else {
                const QJsonDocument doc = QJsonDocument::fromJson(reply->readAll());
                if (!doc.isObject()) {
                    failure = QStringLiteral("直链解析返回不是有效 JSON");
                } else {
                    const QJsonObject root = doc.object();
                    const QString url = root.value(QStringLiteral("url")).toString().trimmed();
                    if (!root.value(QStringLiteral("success")).toBool(false) || url.isEmpty()) {
                        failure = root.value(QStringLiteral("message")).toString(QStringLiteral("直链解析失败"));
                    } else {
                        resolved = QUrl::fromUserInput(url);
                        if (!resolved.isValid() || resolved.isEmpty()) {
                            failure = QStringLiteral("后端返回的播放直链无效");
                        }
                    }
                }
            }
            if (!failure.isEmpty()) {
                if (attempt < 20) {
                    showStatus(QStringLiteral("%1，正在重试（%2/20）").arg(failure).arg(attempt + 1), 0);
                    QTimer::singleShot(qMin(5000, 250 * attempt), this, [this, requestResolve, attempt, resolveSerial]() {
                        if (resolveSerial != m_resolveSerial) return;
                        (*requestResolve)(attempt + 1);
                    });
                } else {
                    showStatus(QStringLiteral("%1（已重试20次）").arg(failure), 0);
                }
                return;
            }
            if (m_sourceEdit) {
                m_sourceEdit->setText(resolved.toString(QUrl::FullyEncoded));
            }
            showStatus(QStringLiteral("已获取真实播放直链"), 2500);
            startPlaybackWithSource(resolved, autoplay);
        });
    };
    (*requestResolve)(1);
}

void MainWindow::startPlaybackWithSource(const QUrl &source, bool autoplay)
{
    // Invalidate any older JSON resolve/retry chain before opening a new
    // direct source or the result of the current resolve operation.
    ++m_resolveSerial;
    const bool previousApplyingRemote = m_applyingRemote;
    if (m_togetherJoined) {
        // Stopping the previous item emits a pause signal. Do not write that
        // stale position into the newly selected room media.
        m_applyingRemote = true;
    }
    stopMpvPlayback();
    m_player->stop();
    m_applyingRemote = previousApplyingRemote;
    m_duration = 0;
    m_seekSlider->setRange(0, 0);
    m_timeLabel->setText(QStringLiteral("00:00 / 00:00"));
    if (m_sourceEdit) {
        m_sourceEdit->setText(source.isLocalFile() ? source.toLocalFile() : source.toString(QUrl::FullyEncoded));
    }
    setWindowTitle(QStringLiteral("withU Desktop - %1").arg(sourceDisplayName(source)));
    showStatus(QStringLiteral("正在打开视频..."));
    if (!startMpvPlayback(source, autoplay)) {
        m_pendingLocalPosition = -1;
        showStatus(QStringLiteral("libmpv 初始化失败，未启动外部播放器"), 0);
        return;
    }
    if (m_pendingLocalPosition >= 0) {
        const qint64 pending = m_pendingLocalPosition;
        m_pendingLocalPosition = -1;
        QTimer::singleShot(900, this, [this, pending]() {
            if (m_usingMpv) {
                sendMpvCommand(QByteArray("seek ") + QByteArray::number(pending / 1000));
                m_mpvPosition = pending;
            }
        });
    }
}

void MainWindow::togglePlayback()
{
    if (m_usingMpv) {
        sendMpvCommand(m_mpvPlaying ? "pause" : "play");
        m_mpvPlaying = !m_mpvPlaying;
        if (m_playButton) m_playButton->setText(m_mpvPlaying ? QStringLiteral("暂停") : QStringLiteral("播放"));
        if (!m_applyingRemote && m_togetherJoined) {
            sendTogetherEvent(m_mpvPlaying ? QStringLiteral("play") : QStringLiteral("pause"));
        }
        return;
    }
    if (m_player->playbackState() == QMediaPlayer::PlayingState) {
        m_player->pause();
    } else {
        m_player->play();
    }
}

void MainWindow::stopPlayback()
{
    if (m_usingMpv) {
        stopMpvPlayback();
        if (m_playButton) m_playButton->setText(QStringLiteral("播放"));
    }
    m_player->stop();
}

void MainWindow::toggleVideoFullscreen()
{
    if (!m_videoWidget) {
        return;
    }
    const bool nextFullscreen = !m_videoWidget->isFullScreen();
    m_videoWidget->setFullScreen(nextFullscreen);
    if (m_fullscreenButton) {
        m_fullscreenButton->setText(nextFullscreen ? QStringLiteral("退出全屏") : QStringLiteral("全屏"));
    }
}

void MainWindow::playPreviousEpisode()
{
    if (m_currentEpisodeIndex <= 0 || m_currentEpisodeIndex >= m_episodePlayUrls.size()) {
        showStatus(QStringLiteral("已经是第一集"), 2000);
        return;
    }
    const int target = m_currentEpisodeIndex - 1;
    openLibraryMedia(m_episodePlayUrls.at(target), m_episodeNames.value(target), m_episodeMediaIds.value(target));
}

void MainWindow::playNextEpisode()
{
    if (m_currentEpisodeIndex < 0 || m_currentEpisodeIndex + 1 >= m_episodePlayUrls.size()) {
        showStatus(QStringLiteral("已经是最后一集"), 2000);
        return;
    }
    const int target = m_currentEpisodeIndex + 1;
    openLibraryMedia(m_episodePlayUrls.at(target), m_episodeNames.value(target), m_episodeMediaIds.value(target));
}

void MainWindow::seekBackward()
{
    if (m_usingMpv) {
        const qint64 position = qMax<qint64>(0, m_mpvPosition - 10'000);
        sendMpvCommand(QByteArray("seek ") + QByteArray::number(position / 1000));
        m_mpvPosition = position;
        return;
    }
    m_player->setPosition(qMax<qint64>(0, m_player->position() - 10'000));
}

void MainWindow::seekForward()
{
    if (m_usingMpv) {
        const qint64 position = qMin(m_mpvDuration, m_mpvPosition + 10'000);
        sendMpvCommand(QByteArray("seek ") + QByteArray::number(position / 1000));
        m_mpvPosition = position;
        return;
    }
    m_player->setPosition(qMin(m_duration, m_player->position() + 10'000));
}

void MainWindow::positionChanged(qint64 position)
{
    if (!m_userSeeking) {
        m_seekSlider->setValue(static_cast<int>(position));
    }
    updateTimeLabel(position, m_duration);
}

void MainWindow::durationChanged(qint64 duration)
{
    m_duration = qMax<qint64>(0, duration);
    m_seekSlider->setRange(0, static_cast<int>(qMin<qint64>(m_duration, std::numeric_limits<int>::max())));
    updateTimeLabel(m_player->position(), m_duration);
}

void MainWindow::mediaStateChanged(QMediaPlayer::PlaybackState state)
{
    updatePlayButton(state);
    if (!m_applyingRemote && m_togetherJoined) {
        sendTogetherEvent(state == QMediaPlayer::PlayingState ? QStringLiteral("play") : QStringLiteral("pause"));
    }
}

void MainWindow::mediaStatusChanged(QMediaPlayer::MediaStatus status)
{
    switch (status) {
    case QMediaPlayer::LoadingMedia:
    case QMediaPlayer::BufferingMedia:
        showStatus(QStringLiteral("正在缓冲..."), 0);
        break;
    case QMediaPlayer::LoadedMedia:
    case QMediaPlayer::BufferedMedia:
        showStatus(QStringLiteral("已加载，可播放 H.265 / H.264 等系统支持的视频"));
        if (!m_pendingRemoteState && m_pendingLocalPosition >= 0) {
            m_player->setPosition(m_pendingLocalPosition);
            m_pendingLocalPosition = -1;
        }
        if (m_pendingRemoteState) {
            m_player->setPlaybackRate(m_pendingRemoteSpeed);
            m_player->setPosition(m_pendingRemotePosition);
            if (m_pendingRemotePlaying) {
                m_player->play();
            } else {
                m_player->pause();
            }
            m_pendingRemoteState = false;
            QTimer::singleShot(220, this, [this]() { m_applyingRemote = false; });
        }
        break;
    case QMediaPlayer::EndOfMedia:
        if (m_autoplayEnabled && m_currentEpisodeIndex >= 0 && m_currentEpisodeIndex + 1 < m_episodePlayUrls.size()) {
            showStatus(QStringLiteral("本集播放完成，正在进入下一集"), 3000);
            playNextEpisode();
        } else {
            showStatus(QStringLiteral("播放完成"));
        }
        break;
    case QMediaPlayer::InvalidMedia:
        showStatus(QStringLiteral("媒体格式或地址不可用，请检查 Windows HEVC 解码支持"), 0);
        m_pendingLocalPosition = -1;
        m_pendingRemoteState = false;
        m_applyingRemote = false;
        break;
    default:
        break;
    }
}

void MainWindow::mediaErrorOccurred(QMediaPlayer::Error error, const QString &errorString)
{
    showStatus(QStringLiteral("播放失败（%1）：%2").arg(static_cast<int>(error), 0, 10).arg(errorString), 0);
}

void MainWindow::seekSliderPressed()
{
    m_userSeeking = true;
}

void MainWindow::seekSliderReleased()
{
    m_userSeeking = false;
    if (m_usingMpv) {
        m_mpvPosition = m_seekSlider->value();
        sendMpvCommand(QByteArray("seek ") + QByteArray::number(m_mpvPosition / 1000));
    } else {
        m_player->setPosition(m_seekSlider->value());
    }
    sendTogetherEvent(QStringLiteral("seek"));
}

void MainWindow::volumeChanged(int value)
{
    m_audioOutput->setVolume(static_cast<float>(value) / 100.0F);
    if (m_usingMpv) {
        sendMpvCommand(QByteArray("volume ") + QByteArray::number(qBound(0, value * 256 / 100, 256)));
    }
}

void MainWindow::playbackRateChanged(double value)
{
    if (m_usingMpv) {
        m_mpvRate = value;
        sendMpvCommand(QByteArray("rate ") + QByteArray::number(value, 'f', 2));
    } else {
        m_player->setPlaybackRate(value);
    }
    if (!m_applyingRemote && m_togetherJoined) {
        sendTogetherEvent(QStringLiteral("speed"));
    }
}

void MainWindow::updatePlayButton(QMediaPlayer::PlaybackState state)
{
    m_playButton->setText(state == QMediaPlayer::PlayingState ? QStringLiteral("暂停") : QStringLiteral("播放"));
}

void MainWindow::updateTimeLabel(qint64 position, qint64 duration)
{
    m_timeLabel->setText(QStringLiteral("%1 / %2").arg(formatTime(position), formatTime(duration)));
}

void MainWindow::showStatus(const QString &message, int timeout)
{
    if (m_statusLabel) {
        m_statusLabel->setText(message);
    }
    if (timeout > 0) {
        statusBar()->showMessage(message, timeout);
    } else {
        statusBar()->showMessage(message);
    }
}

void MainWindow::updateLoveCounter()
{
    if (!m_loveCounterLabel && !m_loveDaysLabel) return;
    QDateTime start = QDateTime::fromString(m_loveStartDate, Qt::ISODate);
    if (!start.isValid()) start = QDateTime::fromString(m_loveStartDate, QStringLiteral("yyyy-MM-dd"));
    if (!start.isValid()) {
        m_loveCounterLabel->setText(QStringLiteral("还没有设置一起走过的开始日期"));
        if (m_loveDaysLabel) m_loveDaysLabel->setText(QStringLiteral("0"));
        if (m_loveHoursLabel) m_loveHoursLabel->setText(QStringLiteral("00"));
        if (m_loveMinutesLabel) m_loveMinutesLabel->setText(QStringLiteral("00"));
        if (m_loveSecondsLabel) m_loveSecondsLabel->setText(QStringLiteral("00"));
        return;
    }
    qint64 seconds = qMax<qint64>(0, start.secsTo(QDateTime::currentDateTime()));
    const qint64 days = seconds / 86400;
    seconds %= 86400;
    const qint64 hours = seconds / 3600;
    seconds %= 3600;
    const qint64 minutes = seconds / 60;
    const qint64 remainingSeconds = seconds % 60;
    if (m_loveDaysLabel) m_loveDaysLabel->setText(QString::number(days));
    if (m_loveHoursLabel) m_loveHoursLabel->setText(QStringLiteral("%1").arg(hours, 2, 10, QLatin1Char('0')));
    if (m_loveMinutesLabel) m_loveMinutesLabel->setText(QStringLiteral("%1").arg(minutes, 2, 10, QLatin1Char('0')));
    if (m_loveSecondsLabel) m_loveSecondsLabel->setText(QStringLiteral("%1").arg(remainingSeconds, 2, 10, QLatin1Char('0')));
    m_loveCounterLabel->setText(QStringLiteral("%1 天  %2 小时  %3 分钟  %4 秒")
        .arg(days).arg(hours, 2, 10, QLatin1Char('0'))
        .arg(minutes, 2, 10, QLatin1Char('0'))
        .arg(remainingSeconds, 2, 10, QLatin1Char('0')));
}

QString MainWindow::formatTime(qint64 milliseconds)
{
    const qint64 totalSeconds = qMax<qint64>(0, milliseconds) / 1000;
    const qint64 hours = totalSeconds / 3600;
    const qint64 minutes = (totalSeconds % 3600) / 60;
    const qint64 seconds = totalSeconds % 60;
    if (hours > 0) {
        return QStringLiteral("%1:%2:%3").arg(hours).arg(minutes, 2, 10, QLatin1Char('0')).arg(seconds, 2, 10, QLatin1Char('0'));
    }
    return QStringLiteral("%1:%2").arg(minutes, 2, 10, QLatin1Char('0')).arg(seconds, 2, 10, QLatin1Char('0'));
}
