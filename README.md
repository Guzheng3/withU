# WithU

WithU 是面向两位授权用户的情侣专属网站，提供点滴记录、留言墙、爱情相册、纪念事件、地图与足迹、天气旅行、一起看/同步观影，并通过对接独立部署的 **withUstrm** 媒体服务，提供影视库浏览与在线播放能力。

项目地址：[GitHub](https://github.com/Guzheng3/withU.git)

## 功能

- **影视库**：`/watch.php` 提供影视浏览、搜索、筛选、分组、最近播放和详情页，数据来自 withUstrm 媒体库。
- **网页播放**：`/watch_play.php` 支持 MP4 / HLS 直链播放、分集切换、播放历史、倍速与手势操作。
- **一起看**：两位用户加入同一房间，同步播放/暂停、拖动、换集，支持聊天与弹幕；strm 媒体同样可入房同步。
- **站点功能**：文章日记、相册、留言墙、纪念事件、地图足迹、天气旅行、评论审核、信任设备等。
- **管理后台**：Admin v3 轻量通透界面，涵盖内容管理、影视与播放设置、系统管理。

## 架构

| 服务 | 说明 | 默认地址 |
| --- | --- | --- |
| withU 主站 | PHP 站点，`frontend/`（前台）+ `backend/app/`（后台与接口），`router.php` 统一路由 | `http://127.0.0.1:1314` |
| withUstrm 后端 | Spring Boot（独立仓库），SQLite 存储，提供媒体库与外部接口 | `http://127.0.0.1:8081` |
| withUstrm bridge | Node 静态服务，承载 Nuxt 前端并反代后端 API | `http://127.0.0.1:3111` |

withU 与 withUstrm 通过 **外部媒体库接口** 对接（`/api/external/**`，`X-API-Key` 鉴权），主站服务端持有 Key 并代理全部请求，前端拿不到任何凭据。

## 技术要求

- PHP 8.2+，启用 PDO MySQL、cURL、OpenSSL、JSON、mbstring、fileinfo、GD。
- MariaDB / MySQL 5.7+（主站数据）。
- Node.js ≥ 18（withUstrm 前端构建与 bridge）。
- OpenJDK ≥ 21（withUstrm 后端构建与运行）。
- FFmpeg（可选）：用于转码与视频封面，使用独立发布包，见下文。

## 目录结构

| 路径 | 用途 |
| --- | --- |
| `frontend/` | 前台页面与静态资源 |
| `backend/app/` | 后台管理、接口、影视对接网关 |
| `backend/app/api/strm.php` | withUstrm 媒体库网关（列表/详情/解析/流式代理） |
| `backend/app/admin/strm_settings.php` | 后台「withUstrm 媒体库」对接配置页 |
| `deploy-local/` | 本地/服务器启动脚本 |
| `frontend/../config/`（`config/`） | 运行时生成的站点配置（不入库） |

## 快速开始（Linux）

```bash
# 1. 准备 MariaDB、PHP、Node、JDK（见技术要求）
# 2. 启动主站（幂等：自动建库、生成 config、拉起 PHP 服务）
bash deploy-local/start-linux.sh

# 3. 停止
bash deploy-local/stop-linux.sh
```

withUstrm 为独立部署的服务：后端 `gradlew bootJar` 构建、前端 `nuxt generate` 构建、`bridge.js` 承载静态产物并反代 API。首次对接步骤见下节。

## withUstrm 媒体库对接

1. 在 withUstrm 管理界面 → 系统设置 → **外部媒体库接口**，启用并生成 API Key。
2. 登录 withU 后台 → **影视与播放 → withUstrm 媒体库**（`/admin/strm_settings.php`）：
   - 填写 withUstrm 服务地址（如 `http://127.0.0.1:8081`）；
   - 粘贴 API Key，点击「测试连接」确认，保存。
3. 访问 `/watch.php` 浏览媒体库；播放与一起看入口均在影视库内。

安全边界：API Key 只保存在主站数据库（服务器端）；影视库、播放、解析接口全部要求情侣账号（user1/user2）登录；withUstrm 建议只绑定内网地址，不对外暴露。

## TMDB 国内访问方案

withUstrm 刮削依赖 TMDB。国内网络环境推荐组合方案：

1. **API 直连**：`api.themoviedb.org` 的不可达多为 DNS 污染，可通过 [CheckTMDB](https://github.com/cnwikee/CheckTMDB) 每日更新的 hosts（IPv4 条目）写入 `/etc/hosts` 解决，建议配 cron 自动更新。
2. **图片反代**：`image.tmdb.org` 在多数线路无法通过 hosts 解决，推荐用 Cloudflare Worker 十行代码反向代理（`/t/p/*` → 图床，其余 → API），绑定一个 DNS 托管在 Cloudflare 的自定义域（`workers.dev` 国内不稳定）。
3. **withUstrm 推荐配置**：`baseUrl` / `chinaApiUrl` 用官方 API 域名（配合 hosts），`imageBaseUrl` / `chinaImageUrl` 指向反代域名。
4. **无 IPv6 出口的服务器**：Cloudflare 域名为双栈，Java 不会自动回退 IPv4，启动需加 `-Djava.net.preferIPv4Stack=true`。
5. 可选：对 CF 边缘 IP 做延迟探测后写入 hosts 固定（"优选 IP"），可显著提升稳定性。

## FFmpeg 独立运行包

FFmpeg 用于视频兼容性转码、媒体探测和封面生成，不随仓库分发。请从发布文件下载 `withU-ffmpeg-runtime-*.zip` 解压到站点根目录，确保存在：

```text
bin/ffmpeg/ffmpeg
bin/ffmpeg/ffprobe
```

Linux 下注意保留可执行权限（`chmod +x`）。没有 FFmpeg 时直链播放仍可用，仅转码与自动封面不可用。

## 本地开发

```bash
# Linux / WSL
bash deploy-local/start-linux.sh    # 一键启动（幂等）
bash deploy-local/stop-linux.sh     # 一键停止
```

Windows 环境可参考 `deploy-local/start-withu.cjs`（路径按本机调整）。

## 安全说明

- 站点不提供公开注册，仅两位授权用户（user1/user2）可登录。
- 运行时生成的 `config/config.php`、`config/database.php`、`.installed` 不入库，注意保管 `SECRET_KEY`。
- 媒体播放直链经主站服务端解析与代理，签名地址不暴露给前端。
