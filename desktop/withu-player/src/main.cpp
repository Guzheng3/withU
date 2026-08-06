#include "MainWindow.h"

#include <QApplication>

int main(int argc, char *argv[])
{
    // Keep Qt's auxiliary multimedia objects available for shared-view state,
    // while actual desktop playback is handled by the in-process libmpv path.
    qputenv("QT_MEDIA_BACKEND", "windows");
    QApplication app(argc, argv);
    QApplication::setApplicationName(QStringLiteral("withU Desktop"));
    QApplication::setApplicationVersion(QStringLiteral("0.2.0"));
    QApplication::setOrganizationName(QStringLiteral("withU"));

    MainWindow window;
    window.show();
    return app.exec();
}
