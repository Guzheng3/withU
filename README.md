# WithU

WithU 是面向两位授权用户的情侣专属网站。项目提供点滴记录、留言墙、爱情相册、纪念事件、地图与足迹、天气旅行、一起看/同步观影、聊天/弹幕、网页播放器、后台管理，并提供可选的 Windows Qt 桌面客户端。

项目地址：[GitHub](https://github.com/Guzheng3/withU.git)

## 功能

- **影视库**：`watch.php` 提供影视搜索、筛选、分组、最近播放和猜你喜欢。
- **网页播放**：`watch_play.php` 支持 MP4、HLS 与 withUstrm 提供的媒体来源、分集切换、播放历史和清晰度信息。
- **一起看**：两位用户可加入同一房间，同步播放/暂停、拖动、倍速和换集；支持聊天、弹幕以及浏览器支持时的麦克风连线。
- **媒体元数据**：withUstrm 提供海报、评分、类型、演员、简介、总集数和完结状态；播放页同时显示总集数与库内集数。
- **访问边界**：影视库、播放和管理功能默认只向现有的两位授权用户开放，不提供公开注册流程。
- **桌面客户端**：可选 Windows Qt/libmpv/WebView2 客户端，复用 WithU 的网页资源和播放能力。

## 技术要求

- PHP 8.2（推荐），启用 PDO MySQL、cURL、OpenSSL、JSON、mbstring、fileinfo 等常用扩展。
- MySQL 5.7+/8.x 或 MariaDB；影视元数据与播放资源由独立的 withUstrm 服务提供（基于 SQLite，不依赖主库）。
- Nginx 或 Apache，并将站点根目录指向本项目。
- Redis 仅在部署配置或相关服务启用时需要。
- HTTPS 是生产环境推荐配置，尤其是一起看麦克风和外部媒体源场景。

FFmpeg 不属于 GitHub 源码仓库，使用时请单独下载发布包，见下方说明。

## 目录

| 目录 | 用途 |
| --- | --- |
| `admin/` | 后台管理页面 |
| `api/` | 播放、媒体、一起看等接口 |
| `assets/` | CSS、JavaScript、字体和图片 |
| `core/` | 认证、数据库与一起看/播放器公共逻辑 |
| `database/` | 主站数据库结构 |
| `deploy/` | Nginx 和部署说明 |
| `desktop/` | Windows Qt 桌面客户端源码 |
| `scripts/` | 维护脚本 |
| `views/` | 页面模板和公共视图 |
| `uploads/`、`runtime/`、`storage/`、`logs/` | 本地运行数据，不应提交到仓库 |

本地生成的 `config/config.php`、`config/database.php` 和 `.installed` 也不提交到仓库。安装配置、媒体路径、日志和账号信息请保留在部署环境中。

## 安装

1. 将源码复制到 Web 站点根目录，并准备 PHP 与 MySQL/MariaDB。
2. 创建一个空的主站数据库，或准备一个有权限创建数据库和表的数据库账号。
3. 在站点根目录创建空文件 `enable_install.lock`。
4. 浏览器访问 `/install.php`，按向导填写数据库信息并完成初始化。
5. 安装完成后删除或移走 `enable_install.lock`；生产环境建议将 `install.php` 移出 Web 根目录。
6. 登录后台创建/确认两位授权用户，并确保 withUstrm 服务已启动（见下方说明）。

安装向导会生成 `config/database.php` 和 `.installed`。不要把这两个文件中的连接信息复制到 GitHub，也不要在 README、日志或工单中粘贴密码。

### withUstrm 媒体库

- 影视元数据（海报、评分、简介、演员、分集）由 withUstrm 提供，主站经 `/api/strm.php` 网关代理访问。
- 播放链路：主站向 withUstrm 请求播放地址并转发，播放时按需解析，不缓存过期签名。

## FFmpeg 独立运行包

FFmpeg 用于视频兼容性转码、媒体探测和视频封面生成；OpenList/WebDAV 直链播放不经过 FFmpeg。请从发布文件中下载 `withU-ffmpeg-runtime-20260724.zip`，直接解压到与 `index.php`、`watch.php` 同级的 Web 站点根目录。

解压后至少应存在以下路径：

```text
bin/ffmpeg/ffmpeg.exe
bin/ffmpeg/ffprobe.exe
bin/ffmpeg/linux-x86_64/ffmpeg
bin/ffmpeg/linux-x86_64/ffprobe
```

Windows 部署可删除 `linux-x86_64/`；Linux 部署可删除 Windows 的 `.exe` 文件。Linux 文件如果丢失可执行权限，请执行：

```bash
chmod +x bin/ffmpeg/linux-x86_64/ffmpeg bin/ffmpeg/linux-x86_64/ffprobe
```

请保留包内的 FFmpeg GPL 许可证文件。FFmpeg 运行包较大，因此不放入 GitHub 源码仓库；没有它时，基础直链播放仍可用，但转码和自动生成视频封面可能不可用。

## 本地开发

Windows 本地开发环境位于项目外的 `C:\WithU\dev`。启动服务：

```powershell
powershell -ExecutionPolicy Bypass -File C:\WithU\dev\start-withu.ps1
```

本地地址：<http://127.0.0.1:8080/>

PHP 语法检查示例：

```powershell
C:\WithU\tools\php82\php.exe -c C:\WithU\dev\php.ini -l watch_play.php
```

停止服务：

```powershell
powershell -ExecutionPolicy Bypass -File C:\WithU\dev\stop-withu.ps1
```

### CodeGraph 代码图谱

[CodeGraph](https://github.com/colbymchenry/codegraph) 为本项目提供代码索引与知识图谱，支持符号搜索、调用链查询和代码结构浏览。索引数据存放在 `.codegraph/`，已在 `.gitignore` 中忽略。

安装（独立二进制，无需 Node）：

```bash
curl -fsSL https://raw.githubusercontent.com/colbymchenry/codegraph/main/install.sh | sh
```

初始化/增量同步：

```bash
scripts/codegraph-sync.sh          # init 或 sync
scripts/codegraph-sync.sh -q       # 静默模式(适合 git hooks)
```

常用查询：

```bash
codegraph status                  # 索引状态统计
codegraph query <符号>            # 搜索符号
codegraph callers <符号>          # 谁调用了它
codegraph callees <符号>          # 它调用了谁
codegraph node <文件或符号>       # 源码 + 调用链
codegraph files                   # 文件结构
```

## Windows 桌面客户端

桌面客户端需要 Qt 6.8.3 MinGW、CMake、Ninja 和本地 libmpv/WebView2 依赖。构建 Release：

```powershell
cd desktop\withu-player
.\build.ps1 -QtRoot C:\WithU\tools\Qt\6.8.3\mingw_64 -Configuration Release
```

输出目录为 `desktop\withu-player\dist-wmf`，程序名为 `withU Desktop.exe`。构建脚本会部署 Qt 运行库，并复制可用的 libmpv、WebView2 Loader 和网页图片资源。桌面源码与大型运行库不随 Web 源码包发布。

## 发布文件

推荐使用发布目录中的两个包：

- `withU-web-release-20260724-core.zip`：不含 FFmpeg 的精简 Web 源码包。
- `withU-ffmpeg-runtime-20260724.zip`：独立 FFmpeg 运行包，解压到 Web 站点根目录。

Windows 桌面端发布包另行提供。发布包不包含本机数据库、上传媒体、运行日志、调试配置和测试账号。

## 安全与许可证

- 生产环境请启用 HTTPS，使用强密码，并限制数据库和 withUstrm 管理入口的网络访问。
- 不要提交 `config/` 中的本地配置、上传媒体、运行日志、FFmpeg/libmpv 二进制、备份和含测试账号的脚本。
- 本项目遵循 [MIT License](LICENSE)。二次开发请保留许可证要求的原项目信息：`I Love Day 情侣成长记录小站`、[原项目地址](https://github.com/MiTaosot/I_Love_Day)、作者 `MiTao`，以及原许可证中列出的 AI 贡献说明。
