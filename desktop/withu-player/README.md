# withU Player

这是 withU 的 Windows 原生桌面端基础工程，使用 C++17 + Qt 6 Widgets / Multimedia。主界面、情侣空间、一起看、媒体库和播放器控制都走 Qt/C++；发布包只内置进程内 `libmpv-2.dll`，不启动外部 mpv 进程，也不依赖服务器 FFmpeg 解码。

## 当前能力

- 打开本地 `mkv/mp4/mov/avi/webm/ts/m2ts` 视频；
- 粘贴 HTTP/HTTPS 视频直链，包括 OpenList 返回的临时直链；
- 播放、暂停、停止、前后 10 秒、进度拖动、音量和倍速；
- 默认入口仍是 Qt 原生情侣空间，播放器继续由进程内 libmpv 解码；设置中新增“打开网页界面”，可用 WebView2 直接渲染当前 WithU 网页，用于逐步复用网页端 DOM/CSS；WebView2 初始化失败时自动保留 Qt 原生界面，不影响桌面播放器；
- 界面采用 withU 的暖白、粉色、浅绿色配色。

## 解码能力

发布包的 libmpv 已携带 FFmpeg 解码能力，不要求服务器安装 FFmpeg。桌面端使用 libmpv 播放 `mkv / mp4 / m3u8 / iso` 等资源；当前默认 `--hwdec=no`，用于 VMware 等没有稳定 GPU 合成的环境，实体机可继续使用软件解码并按后续验收开启硬件路径。VMware 与实体机共用同一套 DLL，差异只在显卡驱动与硬件解码可用性。

## 构建

推荐使用项目已准备的 Qt 6.8.3 + MinGW 13.1.0 + CMake 3.31 + Ninja 环境：

```powershell
$env:QT_ROOT = "C:\WithU\tools\Qt\6.8.3\mingw_64"
pwsh -File .\build.ps1
```

脚本会自动配置、编译、安装并调用 Qt 的 `windeployqt` 部署 Qt 运行库，同时把 `third_party/libmpv/libmpv-2.dll` 和 x64 `WebView2Loader.dll` 复制到发布目录根部。旧外部 `mpv` 和 VLC 目录不会进入发布包；也可以把 `WITHU_CMAKE_GENERATOR` 设为 `Visual Studio 17 2022` 改用 MSVC，但此时 `QT_ROOT` 必须指向 `msvc2022_64` Qt 安装，而不是当前的 MinGW 目录。OpenList 直链过期时，客户端会重新请求 WithU 的受保护解析入口。
