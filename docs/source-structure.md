# WithU 项目源码结构说明

> 面向两位授权用户的情侣专属网站：点滴记录、留言墙、相册、纪念事件、地图足迹、天气旅行、一起看/同步观影、聊天、网页播放器、后台管理。
>
> 项目由两部分组成：**主站 PHP**（`frontend/` 前台 + `backend/app/` 后台与接口，`router.php` 统一路由）和 **withUstrm 媒体库**（独立仓库，通过外部媒体库接口对接，见 [README.md](../README.md)）。

---

## 一、根目录

| 文件 / 目录 | 用途 |
| --- | --- |
| `router.php` | **统一路由入口**。前台 `frontend/` + 后台 `backend/app/` 共用此入口，跑在 1314 端口（`php -S 0.0.0.0:1314 -t . router.php`）。负责 MIME 静态资源、后台 `/admin/`、后台 API `/api/`、前台页面与目录索引的分发 |
| `README.md` | 项目说明（功能、技术要求、安装、withUstrm 对接、FFmpeg） |
| `DEPLOY-LINUX.md` / `DEPLOY-BAOTA.md` | Linux / 宝塔面板部署文档 |
| `LICENSE` | MIT 许可 |
| `.gitignore` | Git 忽略规则（配置、上传、运行时、构建产物、AI 工具目录等） |
| `skills-lock.json` | AI 技能版本锁定 |
| `frontend/` | 前台页面与静态资源 |
| `backend/app/` | 后台管理、接口、认证、影视对接网关（主站 PHP） |
| `backend/server/` | 遗留 Node 服务（已被 `router.php` + PHP 后台取代，见第四节） |
| `backend/runtime/` | 运行时缓存（strm 海报/背景图缓存等） |
| `config/` | 运行时生成的站点配置（不入库，含 `config.php` / `database.php`） |
| `deploy/` | Nginx / 宝塔站点配置、Cloudflare Worker、TMDB hosts 脚本 |
| `deploy-local/` | 本地一键启动 / 停止脚本（Linux / Windows） |
| `docs/` | 项目文档 |
| `scripts/` | 迁移与工具脚本 |

---

## 二、主站 PHP 后端 `backend/app/`

### 2.1 页面（`backend/app/` 根）

| 文件 | 用途 |
| --- | --- |
| `login.php` | 登录 / 注册页（用户名 = 字母数字，用于登录；QQ 号仅用于拉取头像） |
| `logout.php` | 登出 |
| `install.php` | 安装向导（数据库初始化，由 `.installed` + `enable_install.lock` 门控） |
| `password_reset.php` | 伴侣改密独立落地页（不依赖登录态） |
| `article.php` | 文章详情页（含评论 / 聊天创作） |
| `articles.php` | 文章列表页 |
| `messages.php` | 留言墙页面 |
| `events.php` | 纪念事件页面 |
| `travel.php` | 地图足迹 / 天气旅行页面 |
| `watch.php` | 影视库页面（搜索、筛选、分组、最近播放） |
| `watch_history.php` | 播放历史页面 |
| `watch_play.php` | 网页播放器页（MP4/HLS/withUstrm 源、分集、弹幕、一起看） |
| `player.php` | 外部链接播放入口（`/player.php?url=...` → 复用 `watch_play.php`） |
| `cz_player.php` | 旧链接兼容：302 跳转到 `watch_play.php?source=cz` |
| `404.html` / `favicon.ico` / `robots.txt` | 站点基础文件 |
| `api-docs.html` | API 接口文档页 |

### 2.2 后台管理 `backend/app/admin/`（移动端优先）

| 文件 | 用途 |
| --- | --- |
| `index.php` | 后台仪表盘 |
| `albums.php` / `album_add.php` / `album_manage.php` | 相册列表 / 新建 / 图片管理 |
| `articles.php` / `article_add.php` / `article_edit.php` | 文章列表 / 撰写 / 编辑 |
| `article_comments.php` | 单篇文章评论管理 |
| `events.php` / `event_add.php` / `event_edit.php` | 纪念事件列表 / 新增 / 编辑 |
| `messages.php` | 留言列表管理 |
| `moderation.php` | 内容安全审核（拦截记录） |
| `comment_ip_blacklist.php` | 评论 / 留言 IP 黑名单 |
| `devices.php` | 已信任设备管理 |
| `invites.php` | 情侣邀请码管理（生成第二个账号的注册链接） |
| `map.php` | 地图足迹配置管理 |
| `profile.php` | 个人资料（含「我的伴侣」卡片、伴侣改密链接） |
| `settings.php` | 系统设置 |
| `player_art.php` / `player_settings.php` | 播放器设置（`player_settings.php` 302 兼容旧入口） |
| `together_settings.php` | 一起看功能设置 |
| `strm_settings.php` | withUstrm 媒体库对接配置页 |
| `tools_image_stats.php` | 图片体积与相册带宽统计小工具 |
| `header.php` / `footer.php` | 后台公用头部 / 底部导航（移动端 Tabbar） |

### 2.3 API `backend/app/api/`（JSON 接口）

| 文件 | 用途 |
| --- | --- |
| `home.php` | 首页聚合数据接口 |
| `albums.php` | 相册分页加载 |
| `comment.php` | 评论提交 |
| `like.php` | 点赞 / 收藏 |
| `messages.php` | 留言分页加载 |
| `auth-status.php` | 登录状态查询（供跨域前端读取登录态） |
| `upload_image.php` / `upload_video.php` | 编辑器图片 / 视频上传（后台） |
| `article_chat_send.php` / `article_chat_revoke.php` / `article_blocks_sort.php` | 聊天创作模式：发送 / 撤回 / 排序对话块 |
| `cz.php` | 厂长资源（4kcz.com）解析接口 |
| `douban_chart.php` | 豆瓣新剧 / 新电影榜单（带缓存） |
| `desktop.php` | 桌面客户端数据接口 |
| `qq_profile.php` | QQ 头像 / 资料获取（注册表单头像、昵称自动带出） |
| `strm.php` | withUstrm 媒体库对接网关 |
| `travel.php` / `travel_map.php` | 旅行 / 天气、地图足迹数据接口 |
| `watch.php` | 影视库查询接口（搜索 / 筛选 / 最近播放 / 猜你喜欢） |

### 2.4 核心类 `backend/app/core/`

| 文件 | 用途 |
| --- | --- |
| `Database.php` | 数据库连接类（单例，PDO） |
| `Auth.php` | 认证与权限（登录、注册、session、信任设备、权限检查） |
| `helpers.php` | 通用辅助函数（UTF-8 中文版，含 CSRF、迁移等） |
| `withu.php` | SITE_NAME 等站点常量兜底、`withu_token()` 等工具 |
| `Travel.php` | 旅行 / 天气 HTTP 封装（curl JSON） |
| `TravelMap.php` | 地图与情侣足迹数据层（幂等建表） |
| `MediaTranscode.php` | 为浏览器不支持的 HEVC/MKV 生成短期 H264/AAC 播放缓存（FFmpeg） |
| `Moderation.php` | 内容安全审核（规则优先，AI 辅助，全量留痕） |
| `CzCatalog.php` / `CzSource.php` | 厂长资源本地采集库 / 解析算法库 |
| `Searcher.class.php` | IP 定位库（Ip2Region 的 PHP 封装） |
| `Parsedown.php` / `ParsedownMarkdown.php` | Markdown 解析库（第三方） |

### 2.5 配置与数据 `config/`、`database/`、`views/`

| 文件 | 用途 |
| --- | --- |
| `config/config.php` | 站点配置（调试、时区、BASE_URL、上传限制、登录风控、SECRET_KEY，安装生成，不入库） |
| `config/database.php` | 数据库连接配置（安装向导生成，不入库） |
| `database/schema.sql` | 主站完整建表语句 |
| `database/ip2region.xdb` | IP 归属地数据库文件 |
| `views/header.php` / `footer.php` | 公共视图模板（页头 / 页脚） |
| `.installed` | 安装完成标记（不入库） |

**数据库主要表**（来自 `schema.sql`）：`users`、`albums`、`album_images`、`album_videos`、`album_permissions`、`articles`、`article_blocks`、`article_segments`、`article_permissions`、`comments`、`messages`、`likes`、`content_likes`、`events`、`travel_plans`、`weather_cache`、`couple_invites`、`trusted_devices`、`login_attempts`、`comment_attempts`、`message_attempts`、`comment_ip_blacklist`、`moderation_events`、`settings`、`watch_rooms`、`watch_history`、`visitor_logs`、`site_visits` 等。

### 2.6 静态资源 `backend/app/assets/`

| 目录 | 用途 |
| --- | --- |
| `css/` | 后台 / 页面样式（admin_apple、admin_pages、admin_v2、auth、theme 等） |
| `js/` | 后台脚本、播放器（hls、article-player）、编辑器（wangeditor）等 |
| `vendor/` | 第三方库：ArtPlayer、HLS.js、Plyr 等 |
| `admin-art/` | 后台 UI 框架资源（Bootstrap、Layui、jQuery 等） |
| `fonts/` | FontAwesome、字体模板 |
| `images/` | 默认头像、海报占位、logo 等图片 |

---

## 三、前台资源 `frontend/`

### 3.1 页面（PHP）

| 文件 | 用途 |
| --- | --- |
| `index.php` | 首页（含大图轮播、时光碎片、结尾一句话） |
| `timeline.php` | 时间线 |
| `albums.php` | 相册列表 |
| `album-detail.php` / `album-detail-private.php` | 相册详情 / 私密相册详情 |
| `articles.php` | 文章列表 |
| `about.php` | 关于页面 |
| `messages.php` | 留言墙 |
| `lovelist.php` | 心愿单 |
| `page.php` | 通用页面 |
| `_qqavatar.php` | QQ 头像服务端代理（本地缓存 qlogo，同源输出） |

### 3.2 前端脚本 `frontend/assets/js/`

`app.js`（全局）、`auth-status.js`（登录态）、`chat.js`（一起看聊天/弹幕）、`components.js`、`context-menu.js`、`interaction.js`、`map.js` / `map-sdk.js`（地图足迹/高德 SDK）、`mini-map.js`、`mobile-nav.js`、`music-player.js`、`page-*.js`（各页逻辑：index、albums、album-detail、articles、detail、lovelist、messages、timeline）、`pjax.js`、`sakura.js`、`tooltip.js`、`visitor-hash.js`、`webp-default.js`（WebP 默认加载）、`withu-private.js`、`html2canvas.min.js` / `clipboard.js` 等。

### 3.3 其它目录

| 目录 | 用途 |
| --- | --- |
| `inc/` | 前台公共包含：`config.php`（从数据库生成 `$withuConfigJson`）、`auth.php`、`header.php`、`footer.php`、`head-avatars.php` |
| `services/` | 前台数据服务：`article-list.php`（文章分页）、`photo-list.php`（相册照片三级数据源）、`message.php` / `message-list.php` / `message-common.php`（留言读写）、`weather.php`、`info-service.php`、`moments.php`、`random_quote.php`、`webp-default.php` 及 JSON 数据（`map-all.json`、`weather.json`、`album-photos.json`、`quotes.json`） |
| `Lovefolder/` | 相册上传的图片文件 |
| `OwO/` | 表情包图片 |
| `Style/` | 主题样式与第三方库（masonry、pjax、nprogress、meting 等） |
| `_external/` | 外部站点资源快照（含本地化 Google Fonts） |
| `ext/` | 字体文件（Dancing Script、JetBrains Mono 等 woff2） |

---

## 四、遗留 Node 服务 `backend/server/`（已停用）

早期基于 LikeGirl 协议的 Node 后台，已被 `router.php` + PHP 后台取代，保留仅为历史参考，不参与当前运行：

| 文件 | 用途 |
| --- | --- |
| `server.js` | 旧本地服务器主入口 |
| `admin.js` / `lg-admin-server.js` | 旧管理后台模块 |
| `reverse-proxy.js` | 旧反向代理（已被 `router.php` 取代） |
| `store.js` | 旧 JSON 数据存取层 |
| `admin-data/` / `data/` | 旧后台日志与 JSON 数据快照 |

---

## 五、部署与脚本

### 5.1 `deploy/`

| 文件 | 用途 |
| --- | --- |
| `baota-nginx-withu.conf` | 宝塔面板 Nginx 站点配置（与 `router.php` 路由等价） |
| `cloudflare-worker-tmdb.js` | TMDB 图床 Cloudflare Worker 反代代码 |
| `update-tmdb-hosts.sh` | 更新 `/etc/hosts` 中 TMDB 可用 IP |
| `php82-local.ini` | 本地 PHP 8.2 配置参考 |

### 5.2 `deploy-local/`

| 文件 | 用途 |
| --- | --- |
| `start-linux.sh` / `stop-linux.sh` | Linux 一键启动 / 停止（MariaDB + withU PHP 1314，幂等） |
| `start-withu.cjs` / `stop-withu.cjs` | Windows 本地启动 / 停止 |
| `mcp-image-analyze.cjs` | MCP 图片分析服务 |
| `php.ini` | 本地 PHP 配置 |

### 5.3 `docs/` 与 `scripts/`

| 文件 | 用途 |
| --- | --- |
| `docs/api-android.md` | Android 端 API 对接文档 |
| `docs/source-structure.md` | 本文档 |
| `scripts/codegraph-sync.sh` | CodeGraph 索引初始化 / 增量同步 |
| `scripts/migrate-map-messages-to-db.php` | 留言体系迁移到数据库（幂等） |

---

## 六、服务端口与启动关系

```
                    ┌────────────────────────────┐
                    │  router.php (PHP built-in) │  1314 端口
                    │  前台 frontend/ + 后台      │
                    └────────────┬───────────────┘
                                 │ /api/strm.php 网关
                    ┌────────────▼───────────────┐
                    │  withUstrm（独立仓库）       │
                    │  bridge.js (Node) → 3111   │
                    │  Spring Boot (Java) → 8081 │
                    └────────────────────────────┘
```

- `start-linux.sh` 启动 MariaDB 与 withU PHP（1314）；withUstrm 由独立仓库的 `install-linux.sh` 启动（8081 + 3111）。
- 后台管理入口：`http://127.0.0.1:1314/admin/`；前台首页：`http://127.0.0.1:1314/`。
