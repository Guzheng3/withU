# WithU 项目源码结构说明

> 面向两位授权用户的情侣专属网站：点滴记录、留言墙、相册、纪念事件、地图足迹、天气旅行、一起看/同步观影、聊天、网页播放器、后台管理，以及可选 Windows Qt 桌面客户端。
>
> 项目由四大部分组成：**主站 PHP 后端**（`backend/app/`）、**Node.js 本地服务**（`backend/server/`）、**withUstrm 媒体库组件**（`backend/strm/`，Java Spring Boot + Nuxt 前端）和 **前台页面/资源**（`frontend/`）。

---

## 一、根目录

| 文件 | 用途 |
| --- | --- |
| `router.php` | **统一路由入口**。前台 `frontend/` + 后台 `backend/app/` 共用此入口，跑在 1314 端口（`php -S 0.0.0.0:1314 -t ... router.php`）。负责 MIME 静态资源、后台 `/admin/`、后台 API `/api/`、前台页面与目录索引的分发 |
| `README.md` | 项目说明（功能、技术要求、安装、FFmpeg、本地开发、CodeGraph 使用说明） |
| `DEPLOY-LINUX.md` | Linux 服务器部署文档 |
| `LICENSE` | MIT 许可 |
| `.gitignore` | Git 忽略规则（含 `.codegraph/` 索引目录） |
| `strm` | 符号链接 → `backend/strm` |
| `.codegraph/` | CodeGraph 代码索引数据（本地生成，不入库） |
| `.monkeycode/` | 本地 IDE 配置 |
| `.output/` | 构建输出目录 |
| `build/` | Java 构建产物（含 `build/libs/openlisttostrm.jar`） |
| `node_modules/` | npm 依赖 |

---

## 二、主站 PHP 后端 `backend/app/`

### 2.1 前台页面（`backend/app/` 根）

| 文件 | 用途 |
| --- | --- |
| `index.php` | 前台首页（点滴记录/聚合数据展示） |
| `login.php` | 登录 / 注册页面（`login.php.bak` 为其备份） |
| `logout.php` | 登出 |
| `install.php` | 安装向导（数据库初始化） |
| `sitemap.php` | Sitemap XML 生成器 |
| `album.php` | 相册详情页 |
| `albums.php` | 相册列表页 |
| `article.php` | 文章详情页（含评论/聊天创作） |
| `articles.php` | 文章列表页 |
| `messages.php` | 留言墙页面 |
| `events.php` | 纪念事件页面 |
| `travel.php` | 地图足迹 / 天气旅行页面 |
| `watch.php` | 影视库页面（搜索、筛选、分组、猜你喜欢） |
| `watch_history.php` | 播放历史页面 |
| `watch_play.php` | 网页播放器页（MP4/HLS/withUstrm 源、分集、弹幕、一起看） |
| `player.php` | 外部链接播放入口（`/player.php?url=...` → 复用 `watch_play.php`） |
| `cz_player.php` | 旧链接兼容：302 跳转到 `watch_play.php?source=cz` |
| `404.html` | 404 页面 |
| `favicon.ico` / `robots.txt` / `stuck_view.png` | 站点基础文件 |
| `api-docs.html` | API 接口文档页 |

### 2.2 后台管理 `backend/app/admin/`（移动端优先，新版后台）

| 文件 | 用途 |
| --- | --- |
| `index.php` | 后台仪表盘 |
| `albums.php` / `album_add.php` / `album_manage.php` | 相册列表 / 新建相册 / 相册图片管理 |
| `articles.php` / `article_add.php` / `article_edit.php` | 文章列表 / 撰写 / 编辑 |
| `article_comments.php` | 单篇文章评论管理 |
| `events.php` / `event_add.php` / `event_edit.php` | 纪念事件列表 / 新增 / 编辑 |
| `messages.php` | 留言列表管理 |
| `moderation.php` | 内容安全审核（拦截记录） |
| `comment_ip_blacklist.php` | 评论/留言 IP 黑名单 |
| `devices.php` | 已信任设备管理 |
| `invites.php` | 情侣邀请码管理 |
| `map.php` | 地图足迹配置管理 |
| `profile.php` | 个人资料 |
| `settings.php` | 系统设置 |
| `player_art.php` | 播放器设置（观潮 ART 风格） |
| `player_settings.php` | 旧入口兼容：302 跳转到 `player_art.php` |
| `together_settings.php` | 一起看功能设置 |
| `strm.php` | withUstrm 内置组件后台鉴权网关 |
| `strm_home.php` | 媒体库 STRM 后台内嵌页 |
| `tools_image_stats.php` | 图片体积与相册带宽统计小工具 |
| `header.php` / `footer.php` | 后台公用头部 / 底部导航（移动端 Tabbar） |

### 2.3 API `backend/app/api/`（JSON 接口）

| 文件 | 用途 |
| --- | --- |
| `home.php` | 首页聚合数据接口 |
| `albums.php` | 相册分页加载 |
| `comment.php` | 评论提交 |
| `like.php` | 点赞/收藏 |
| `messages.php` | 留言分页加载 |
| `auth-status.php` | 登录状态查询（供跨域前端读取登录态） |
| `upload_image.php` | wangEditor 图片上传（后台） |
| `upload_video.php` | wangEditor 视频上传（后台） |
| `article_chat_send.php` | 聊天创作模式：发送对话块 |
| `article_chat_revoke.php` | 聊天创作模式：撤回对话块 |
| `article_blocks_sort.php` | 对话块排序 |
| `cz.php` | 厂长资源（4kcz.com）解析接口 |
| `douban_chart.php` | 豆瓣新剧/新电影榜单（首页用，带缓存） |
| `desktop.php` | 桌面客户端数据接口 |
| `qq_profile.php` | QQ 头像/资料获取 |
| `strm.php` | withUstrm 媒体库对接网关 |
| `travel.php` | 旅行/天气数据接口 |
| `travel_map.php` | 地图足迹数据接口 |
| `watch.php` | 影视库查询接口（搜索/筛选/最近播放/猜你喜欢） |

### 2.4 核心类 `backend/app/core/`

| 文件 | 用途 |
| --- | --- |
| `Database.php` | 数据库连接类（单例） |
| `Auth.php` | 认证与权限（登录、session、信任设备、权限检查） |
| `helpers.php` | 通用辅助函数（UTF-8 中文版） |
| `withu.php` | SITE_NAME 等站点常量兜底 |
| `Travel.php` | 旅行/天气 HTTP 封装（curl JSON） |
| `TravelMap.php` | 地图与情侣足迹数据层（幂等建表） |
| `MediaTranscode.php` | 为浏览器不支持的 HEVC/MKV 生成短期 H264/AAC 播放缓存（FFmpeg） |
| `Moderation.php` | 内容安全审核（规则优先，AI 辅助，全量留痕） |
| `CzCatalog.php` | 厂长资源（4kcz.com）本地采集库 |
| `CzSource.php` | 厂长资源解析算法库（解析源编码 cz） |
| `Searcher.class.php` | IP 定位库（Ip2Region 的 PHP 封装） |
| `Parsedown.php` | Markdown 解析库（第三方） |

### 2.5 配置与数据 `backend/app/config/`、`database/`、`views/`

| 文件 | 用途 |
| --- | --- |
| `config/config.php` | 站点配置（调试开关、时区、BASE_URL、上传限制、登录风控阈值、SECRET_KEY） |
| `config/database.php` | 数据库连接配置（安装向导生成，不入库） |
| `database/schema.sql` | 主站完整建表语句 |
| `database/ip2region.xdb` | IP 归属地数据库文件（Ip2Region） |
| `views/header.php` / `footer.php` / `home.php` | 公共视图模板（页头/页脚/首页布局） |
| `.installed` | 安装完成标记 |

**数据库主要表**（来自 `schema.sql`）：`users`、`albums`、`album_images`、`album_image_uploads`、`album_videos`、`album_permissions`、`articles`、`article_blocks`、`article_segments`、`article_permissions`、`article_contributions`、`article_edit_logs`、`comments`、`messages`、`likes`、`content_likes`、`events`、`travel_plans`、`weather_cache`、`couple_invites`、`trusted_devices`、`login_attempts`、`comment_attempts`、`message_attempts`、`comment_ip_blacklist`、`moderation_events`、`settings`、`watch_rooms`、`watch_room_members`、`watch_events`、`watch_history` 等。

### 2.6 静态资源 `backend/app/assets/`

| 目录 | 用途 |
| --- | --- |
| `css/` | 后台/页面样式（admin_apple、admin_pages、admin_v2、auth、theme、withu-sakura 等） |
| `js/` | 后台脚本（admin_v2.js、auth.js）、播放器（hls.min.js、article-player.js）、编辑器（wangeditor.min.js）、withu-sakura.js |
| `vendor/` | 第三方库：ArtPlayer 播放器（artplayer-5.4.0.js）、HLS.js、Plyr 播放器 |
| `admin-art/` | 后台 UI 框架资源（Bootstrap 3.4.1、Layui、jQuery、字体模板） |
| `fonts/` | FontAwesome、阿里巴巴普惠体等字体 |
| `images/` | 默认头像、海报占位、logo 等图片 |

---

## 三、Node.js 本地服务 `backend/server/`

| 文件 | 用途 |
| --- | --- |
| `server.js` | 本地服务器主入口：静态站点 + 前端服务接口（真实数据持久化）+ 高德地图 JS API 代理（`/_AMapService`）+ 动态配置注入 |
| `admin.js` | 管理后台模块（基于 LikeGirl 后台协议还原，挂载到 `server.js`） |
| `lg-admin-server.js` | 独立 lg 后台服务（单独端口 8903，提供 withuadmin 后台） |
| `reverse-proxy.js` | 1314 端口反向代理：`/admin/` 与 `/admin-assets/` 走 PHP 后台（8902），其余转发到 withU server（8901） |
| `store.js` | JSON 数据存取层（统一持久化 + 内存缓存） |
| `app-config.json` | 应用配置 |
| `admin-data/oplog.jsonl` | 后台操作日志（追加式 JSON Lines） |
| `data/beacons.json` | 信标（足迹）数据 |
| `data/interactions.json` | 互动数据 |
| `data/map-config.json` | 地图配置 |
| `data/weather-config.json` | 天气配置 |

---

## 四、withUstrm 媒体库组件 `backend/strm/`

独立媒体库系统（Spring Boot Java 后端 + Nuxt 3 前端 + Node 桥接），提供影视元数据（海报/评分/简介）、STRM 文件生成、自动刮削、定时任务等能力。

### 4.1 根文件

| 文件 | 用途 |
| --- | --- |
| `bridge.js` | 桥接服务器（仅监听 127.0.0.1:3112）：Nuxt 静态产物 + 反代 Spring Boot API + SPA 回退 |
| `app_version.json` | 发布版本号（release v2.6.0 / beta v2.5.15） |
| `package.json` / `package-lock.json` | Nuxt 前端依赖 |
| `Dockerfile` / `docker-compose.yml` / `dev-docker.sh` / `dev-podman.sh` | Docker 构建/开发脚本 |
| `nginx.conf` / `Caddyfile` | 生产反代配置 |
| `AGENTS.md` / `CLAUDE.md` / `README.md` / `DEV_SCRIPTS.md` | 开发文档 |
| `openspec/` | OpenSpec 规范（specs + changes） |
| `web-bundles/` | 内置 Web 资源包（agents/expansion-packs/teams） |

### 4.2 Java 后端 `backend/strm/backend/src/main/java/com/hienao/openlist2strm/`

| 包 | 主要文件与用途 |
| --- | --- |
| `ApplicationService.java` | Spring Boot 启动类 |
| `config/` | 配置类：CORS、JWT、密码编码器、路径校验、Quartz 调度、RestTemplate、任务线程池、缓存、豆瓣 Cookie 加密 |
| `config/security/` | 安全层：WebSecurityConfig、JwtAuthenticationFilter/Token、UserDetailsService、HTTP 防火墙、登录失败处理 |
| `controller/` | REST 控制器：媒体库、媒体服务器、Openlist 配置、STRM 播放、任务配置、系统配置、数据上报、日志、签名、版本、豆瓣/TMDB/标题测试、外部 API |
| `dto/` | 数据传输对象（媒体、任务、Openlist、TMDB、版本检查、登录注册、分页等） |
| `entity/` | 实体类（MediaLibraryItem、TaskConfig、OpenlistConfig、MediaServerConfig、ManualScrapingJob 等） |
| `exception/` | 业务异常 + 全局异常处理器 |
| `handler/` | 文件处理责任链：发现、过滤、优先级解析、刮削、STRM 生成、字幕复制、图片下载、NFO 下载、孤儿清理 |
| `handler/context/` | 文件处理上下文与刮削会话 |
| `job/` | Quartz 定时任务：任务配置、数据备份、邮件、日志清理、版本检查、配置删除清理 |
| `listener/` | 应用启动监听 |
| `mapper/` | MyBatis Mapper 接口 |
| `notification/` | 刮削结果通知（事件、渲染器、邮件/推送） |
| `service/` | 业务服务：Openlist API（限流）、刮削、媒体库、媒体服务器 API、封面、TMDB、AI 文件名识别、STRM 文件、任务执行/清单/结构检查、通知、缓存、数据上报、日志、签名、版本检查、mihomo 代理 |
| `title/` | 媒体标题解析：路径解析、候选打分、拼音归一化、多个元数据提供者（TMDB/豆瓣/DMDB/网页搜索）、标题解析结果 |
| `util/` | 工具类：媒体文件解析、季目录解析、目录结构校验、标题清洗、TMDB ID 提取、URL 编码 |
| `validation/` | Cron 表达式校验 |
| `constant/AppConstants.java` | 常量定义 |

### 4.3 Java 资源 `backend/strm/backend/src/main/resources/`

| 文件 | 用途 |
| --- | --- |
| `application.yml` / `application-prod.yml` | Spring 配置（开发/生产） |
| `logback-spring.xml` | 日志配置 |
| `db/migration/V1_0_*.sql` | Flyway 数据库迁移脚本（从初始 schema 到 v1.0.20，含 Openlist 配置、任务配置、媒体库表、手动刮削等） |
| `mapper/*.xml` | MyBatis SQL 映射 |

### 4.4 Java 测试 `backend/strm/backend/src/test/`

覆盖服务、工具、处理器、标题解析、集成缓存、JWT 单元测试等（`*Test.java`）。

### 4.5 Nuxt 前端 `backend/strm/frontend/app/`

| 路径 | 用途 |
| --- | --- |
| `app.vue` | 根组件 |
| `error.vue` | 错误页 |
| `layouts/default.vue` | 默认布局（页头/页脚） |
| `pages/index.vue` | 首页/仪表盘 |
| `pages/auth/login.vue` / `register.vue` / `change-password.vue` | 登录 / 注册 / 改密 |
| `pages/settings/index.vue` | 系统设置 |
| `pages/logs/index.vue` | 日志查看 |
| `pages/api-docs/index.vue` | API 文档页 |
| `pages/media-library/index.vue` | 媒体库列表 |
| `pages/media-library/[id].vue` | 媒体详情（整页海报背景） |
| `pages/media-library/play/[id].vue` | 播放页（ArtPlayer） |
| `pages/manual-scraping/[taskId].vue` | 手动刮削任务详情 |
| `pages/task-management/[id].vue` | 任务管理详情 |
| `components/` | AppHeader、AppFooter、目录浏览器（ConfigDirectoryBrowser）、刮削树节点、任务结构树节点、MediaCard、ArtPlayerPlayer |
| `core/api/client.ts` | API 客户端封装 |
| `core/api/types/api.types.ts` | API 类型定义 |
| `core/stores/auth.ts` / `version.ts` | Pinia 状态（认证 / 版本） |
| `core/utils/` | token 管理、token 刷新、API 工具、日志、校验、格式化、helpers |
| `core/constants/index.ts` | 常量 |
| `middleware/auth.ts` / `guest.ts` | 登录守卫 / 访客守卫 |
| `middleware/docker-port.global.ts` | Docker 端口兼容中间件 |
| `modules/media-library/services/mediaLibraryApi.ts` | 媒体库 API 调用 |

### 4.6 strm 其他

| 路径 | 用途 |
| --- | --- |
| `scripts/readme-screenshots.mjs` | 生成 README 截图的脚本 |
| `docs/` | strm 文档（添加 Openlist、添加任务、AI 识别配置、外部 API、FAQ、开发、演示图等） |
| `assets/readme/` | README 资源 |

---

## 五、前台资源 `frontend/`

### 5.1 页面（PHP + HTML 双版本）

| 文件 | 用途 |
| --- | --- |
| `index.php` / `index.html` | 首页 |
| `timeline.php` / `timeline.html` | 时间线 |
| `albums.php` / `albums.html` | 相册列表 |
| `album-detail.php` / `album-detail.html` | 相册详情 |
| `album-detail-private.html` | 私密相册详情（仅 HTML 版） |
| `articles.php` / `articles.html` | 文章列表 |
| `about.php` / `about.html` | 关于页面 |
| `messages.php` / `messages.html` | 留言墙 |
| `lovelist.php` / `lovelist.html` | 心愿单 |
| `page.php` / `page.html` | 通用页面 |
| `favicon.png` | 站点图标 |

### 5.2 前端脚本 `frontend/assets/js/`

| 文件 | 用途 |
| --- | --- |
| `app.js` | 全局应用脚本 |
| `auth-status.js` | 登录态检查 |
| `chat.js` | 一起看聊天/弹幕 |
| `components.js` | 通用组件 |
| `context-menu.js` | 右键菜单 |
| `interaction.js` | 交互逻辑 |
| `map.js` / `map-sdk.js` | 地图足迹 / 高德地图 SDK 封装 |
| `mini-map.js` | 迷你地图 |
| `mobile-nav.js` | 移动端导航 |
| `music-player.js` | 音乐播放器 |
| `page-*.js` | 各页面逻辑（index、albums、album-detail、articles、detail、lovelist、messages、timeline） |
| `pjax.js` | PJAX 局部刷新 |
| `sakura.js` | 樱花飘落特效 |
| `tooltip.js` | 提示工具 |
| `visitor-hash.js` | 访客指纹 |
| `withu-private.js` | 私密功能逻辑 |
| `html2canvas.min.js` / `clipboard.js` | 第三方库 |

### 5.3 其他目录

| 目录 | 用途 |
| --- | --- |
| `Lovefolder/` | 相册上传的图片文件（约 220 个） |
| `OwO/` | 表情包图片（emoji 系列） |
| `Style/` | 主题样式与第三方 JS（masonry 瀑布流、jquery.pjax、nprogress、meting 音乐、withu-owoui 等） |
| `_external/` | 外部站点资源快照（withuadmin 后台资源、QQ 头像、wiki 等） |
| `ext/` | 字体文件（Dancing Script、JetBrains Mono 等 woff2） |
| `music/` | 音乐 LRC 歌词文件 |
| `services/` | 前端 JSON 数据：`map-all.json`（地图）、`weather.json`（天气）、`music-player-data.json`（播放器数据） |

---

## 六、部署与脚本

### 6.1 `deploy/`

| 文件 | 用途 |
| --- | --- |
| `baota-nginx-withu.conf` | 宝塔面板 Nginx 站点配置 |
| `php82-local.ini` | 本地 PHP 8.2 配置 |

### 6.2 `deploy-local/`

| 文件 | 用途 |
| --- | --- |
| `start-linux.sh` | Linux 一键启动（MariaDB + withU PHP 1314 + strm 后端 8081 + bridge 3112，含内置 mihomo 代理） |
| `stop-linux.sh` | Linux 一键停止 |
| `start-withu.cjs` / `stop-withu.cjs` | Windows 本地启动/停止 |
| `setup-strm.cjs` | withUstrm 一键构建与启动器生成（幂等） |
| `setup-mihomo.cjs` | mihomo 代理配置初始化 |
| `mcp-image-analyze.cjs` | MCP 图片分析服务 |
| `php.ini` | 本地 PHP 配置 |

### 6.3 `docs/` 与 `scripts/`

| 文件 | 用途 |
| --- | --- |
| `docs/api-android.md` | Android 端 API 对接文档 |
| `scripts/codegraph-sync.sh` | CodeGraph 索引初始化/增量同步脚本 |

---

## 七、服务端口与启动关系

```
                 ┌────────────────────────────┐
                 │  router.php (PHP built-in) │  1314 端口
                 │  前台 frontend/ + 后台      │
                 └────────────┬───────────────┘
                              │ /api/strm.php 网关
                 ┌────────────▼───────────────┐
                 │  bridge.js (Node)          │  127.0.0.1:3112
                 │  Nuxt 静态产物 + API 反代   │
                 └────────────┬───────────────┘
                              │
                 ┌────────────▼───────────────┐
                 │  Spring Boot (Java 21)     │  8081 端口
                 │  openlisttostrm.jar        │
                 └────────────────────────────┘
```

- `start-linux.sh` 依次启动 MariaDB、withU PHP（1314）、strm 后端（8081）、strm bridge（3112）。
- 后台管理入口：`http://127.0.0.1:1314/admin/`；前台首页：`http://127.0.0.1:1314/`。
