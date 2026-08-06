# withU Desktop

Windows 客户端采用 Qt/C++ 实现，代码位于 `withu-player/`。当前已经从单播放器扩展为桌面客户端壳：情侣空间、一起看、媒体库、播放器、设置五个主入口。下一阶段接入 withU 登录、媒体库、观影历史、聊天和“一起看”同步协议。

客户端不依赖服务器 FFmpeg 解码：服务器只提供 OpenList 路径/直链，Windows 端先使用系统媒体后端播放；完整播放器阶段改为 libVLC 或 libmpv，用于优化 H.265、字幕、音轨和硬解控制。

本机已准备便携构建环境：Qt 6.8.3 + MinGW 13.1.0 + CMake 3.31.6 + Ninja 1.12.1。构建命令：

```powershell
$env:QT_ROOT = "C:\WithU\tools\Qt\6.8.3\mingw_64"
pwsh -File .\withu-player\build.ps1
```

输出目录为 `withu-player/dist-wmf`，程序名为 `withU Desktop.exe`。当前只部署 Windows Media Foundation 插件，不包含 Qt FFmpeg 多媒体插件。
