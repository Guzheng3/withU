# User Instruction Memory

This file records user instructions, preferences, and teachings for reference in future interactions.

## Format

### User Instruction Entry
User instruction entries should follow this format:

[User Instruction Summary]
- Date: [YYYY-MM-DD]
- Context: [Mentioned scenario or time]
- Instructions:
  - [Content of user teaching or instruction, described line by line]

### Project Knowledge Entry
Entries discovered by the Agent during task execution should follow this format:

[Project Knowledge Summary]
- Date: [YYYY-MM-DD]
- Context: Discovered by Agent while performing [specific task description]
- Category: [Operations & Deployment|Build Methods|Testing Methods|Troubleshooting & Debugging|Workflow & Collaboration|Environment Configuration]
- Instructions:
  - [Specific knowledge points, described line by line]

## Deduplication Strategy
- Before adding a new entry, check for similar or identical instructions.
- If a duplicate is found, skip the new entry or merge it with the existing one.
- When merging, update the context or date information.
- This helps avoid redundant entries and keeps the memory file tidy.

## Entries

[User Instruction Summary]
- Date: 2026-08-18（已废弃，用户 2026-08-18 纠正方向为"别管后端适配，用 LG 前端"，见下一条目）
- Context: 将 LG 前端（lg-site）接入 withU 后端时，用户明确要求对接方式
- Instructions:
  - 前后端对接采用"直接修改代码实现原生链接"：改前端 JS 让接口直连后端原生接口，禁止用代理/路由转发（如 server.js 转发到后端）的方式。
  - 需要先对比前后端能力：共有的部分做链接，只有一边有的先列出，不强行对接。

[User Instruction Summary]
- Date: 2026-08-18（已废弃：后续用户改以 withU 为主体、引入 lg 前端后反向代理与观影代理均已启用，见 2026-08-21 条目）
- Context: 用户纠正方向：明确"别管后端适配，把前端替换成 LG 前端"
- Instructions:
  - 不做 withU 后端/数据库适配：lg-site 不接入 withU 的 api/lg.php 网关，不用 withU 数据库当数据源。
  - lg-site 保持 LG 原始前端：对接 LG 自己的 lg-server（node，端口 8901，/workspace/withU/lg-server/server.js，ROOT=lg-site），lg-server 同源提供静态页面 + /services/*、/assets/map-api.php 数据 stub。
  - lg-site 前端接口 URL 用 LG 原始相对路径（services/weather.php、assets/map-api.php、EncryptCheck.html 等），禁止 /api/lg.php。
  - withU 的 api/lg.php 网关已停用（备份于 /tmp/opencode/rollback-bak/api-lg.php.bak）。
  - LG 品牌保持原样（logo "LG Demo"、LGNewUi 徽标、版权），不替换成 withU 品牌。
  - 预览入口：lg-server 8901（https://8901-671f8e88838cb484.monkeycode-ai.online），不占用 8899（/tmp/shots 平台服务）。

[Project Knowledge Summary]
- Date: 2026-08-19
- Context: Discovered by Agent while 实现 lg-site 头像组后"未登录显示登录、登录后显示管理后台"的动态切换
- Category: Troubleshooting & Debugging
- Instructions:
  - withU 数据库登录账号是 withu1 / withu2（密码 WithU@1314），不是 user1/user2。
  - lg-site(8901) 与 withU(1314) 是同一预览域（*.monkeycode-ai.online）的不同子域，属同站：跨子域 fetch 会携带 SameSite=Lax cookie，可真实读取登录态；若用 127.0.0.1 访问 lg-site 则跨站，SameSite=Lax cookie 不被发送，登录态检测失效（调试时必须用预览域名）。
  - withU 登录态依赖 withu_device 设备凭证 + PHPSESSID 两者（Auth::restoreTrustedDevice），单独 PHPSESSID 无效。
  - 登录态查询接口：withU /api/auth-status.php（GET，返回 {logged_in, user}，CORS 允许 *.monkeycode-ai.online 与回环，凭据透传）；lg-site 前端 assets/js/lg-auth-status.js 页面加载时 fetch 并按状态切换 #lgnewuiHeaderActions 内 a[data-entry="login"]（未登录显示）与 a[data-entry="admin"]（登录后显示）。


[Project Knowledge Summary]
- Date: 2026-08-20
- Context: Discovered by Agent while 实现 lg-site 全部后端接口、后台管理、高德地图接入
- Category: Operations & Deployment
- Instructions:
  - lg-server(8901) 已实现全部 services 接口真实持久化，单端口预览即完整站点：静态页 + /services/*、/assets/map-api.php、/admin 后台、/_AMapService 高德代理、动态配置注入（amapKey/securityJsCode/服务模式）。
  - 数据文件集中在 backend/server/data/：map-config.json（高德 Key 与 securityMode=proxy，Key=39b478526482ffb6c069eee6f78faf77）、weather-config.json（后台手配城市1/2+ip）、chat-data.json、interactions.json、beacons.json；相册照片扫描 frontend/Lovefolder/，元数据在 backend/server/admin-data/photos-meta.json。
  - 高德采用「代理服务安全模式」（_AMapSecurityConfig={serviceHost:'/',serviceMode:'proxy'}），由服务端转发 /_AMapService/** 到 webapi.amap.com，无需 securityJsCode；10 个 HTML 页面的硬编码 amapKey/securityJsCode 由 server.js 正则替换注入。
  - 后台登录：POST /admin/api/login（JSON body {adminName,pw}，pw 为明文由服务端 md5），cookie withu_admin；默认账号 admin/lovezz。
  - 测试坑：store.js 的 load() 对 '@mapAll'/'@lgConfig' 特殊 key 必须经 ABS_MAP 映射到绝对路径，否则读到空数据；https.request headers 里 referer 不能设为 undefined（Node22 抛 ERR_HTTP_INVALID_HEADER_VALUE），应 delete。

[Project Knowledge Summary]
- Date: 2026-08-21
- Context: Discovered by Agent while 实现首页观影按钮跳转 watch.php
- Category: Environment Configuration
- Instructions:
  - withU 观影/影视后端在 backend/app（PHP 主站，跑 127.0.0.1:8902，数据库 couple_website，含 watch_history/watch_rooms 等表）：watch.php（影视库页）、watch_play.php（播放页）、watch_history.php、api/watch.php（房间）、api/strm.php。影视数据源是 withUstrm（backend/strm，SQLite openlist2strm.db，运行目录 /workspace/runtime/strm/）；MySQL 媒体库（withu_media）与本地媒体库代码（MediaRepository/MediaDatabase 等）已全部移除，影视列表/详情/播放统一经 api/strm.php 或内部 JWT 接口（api/strm.php 之外还可直接调 backend/strm 的 127.0.0.1:8081/api/media-library）。
  - backend/server/server.js 增加 PHP 主站代理 proxyToPhp()：frontend 静态 404 且路径以 .php 结尾或 /api/ 开头时，转发到 http://127.0.0.1:8902（保留 method/query/body/cookie，删除响应 transfer-encoding/connection 头）。首页「观影」悬浮按钮 href="watch.php" 即经此代理可达。
  - 代理 POST 关键坑：backend/server 的 createServer 在 req.on('end') 内统一处理，body 已收集进 body 变量，此时 req 流已结束，proxyToPhp 必须用收集的 body（preq.end(body) + 按字节重设 content-length），不能用 req.pipe(preq)——否则 PHP 收到 Content-Length 声明但 body 空，一直等 body 直到超时挂起。
  - PHP 主站 login.php 登录需 CSRF：先 GET 取表单 name="_token" 值（存 PHPSESSID），POST 时带 _token。登录成功 302 /；未登录访问 watch.php 302 /login.php。
  - 验证用测试账号 withu_test/123456 已写入 couple_website.users（role=user1）；真实账号 withu1/WithU@1314。
  - 1314 端口前端已彻底移除：1314 现为反向代理（backend/server/reverse-proxy.js）→ 8901 backend/server，1314 域名直接显示 frontend 完整前端（frontend/index.html），首页即情侣空间入口；withU PHP 主站服务移到 127.0.0.1:8902（仅本机，外部不可直接访问），backend/server server.js 的 PHP_BACKEND=8902，frontend 观影按钮（8901/watch.php 或 1314/watch.php）经代理→8902 PHP 保持可用。

[Project Knowledge Summary]
- Date: 2026-08-21
- Context: Discovered by Agent while 按用户要求将项目重组为标准目录结构（以 withU 为主体，去掉 lg 命名）
- Category: Operations & Deployment
- Instructions:
  - 仓库标准结构（git mv 完成，commit 17be6ed）：frontend/（原 lg-site 前端，含 index.html、assets/、services/、_external/withuadmin/、Lovefolder/）；backend/server/（原 lg-server Node 服务，server.js/store.js/admin.js/reverse-proxy.js + data/ + admin-data/ + app-config.json）；backend/app/（withU PHP 主站：watch.php、api/、core/、config/、index.php 等 + .installed + uploads/ + runtime/）；backend/strm/（原 strm）。根目录保留 deploy/deploy-local/desktop/docs/scripts/。
  - 站点配置文件名 lg-config.json → app-config.json（backend/server/）；server.js 路径常量 ROOT=path.join(__dirname,'..','..','frontend')、PHP_ROOT=path.join(__dirname,'..','app')；store.js ROOT 与 lgConfig 映射、admin.js ROOT/CONFIG_FILE 均已指向新路径。
  - .gitignore 已同步新路径：backend/app/config/{database.php,config.php,mihomo.json}、backend/app/uploads/*、backend/app/runtime/* 与 backend/runtime/* 忽略（php -S 8902 运行 PHP 主站时会在 backend/ 下生成 runtime/schema-version、schema-migration.lock）。
  - 运行命令（重组后）：8902 用 `php -S 127.0.0.1:8902 -t /workspace/withU/backend/app`；8901 用 `cd backend/server && PORT=8901 node server.js`；1314 用 `node backend/server/reverse-proxy.js`。重组后已验证：8901 首页/map-api/weather/qqavatar/admin、8902 login.php、8901 watch.php 登录代理、1314 反代首页与 map-api 全部 200。

[Project Knowledge Summary]
- Date: 2026-08-21
- Context: Discovered by Agent while 按用户指令"不要写 lg 名称"彻底清理 withU 中全部 lg 标识（commit ff72ee7，承接 17be6ed 目录重组）
- Category: Operations & Deployment
- Instructions:
  - 前端运行文件全部去 lg 命名：assets/js/ 与 Style/css/ 下 lg-*.js/css 去前缀（lg-app.js→app.js 等），lgnewui-private.js→withu-private.js、LGNewUiOwO.js→withu-owoui.js、lgnewui-*.css→withu-*.css；类名/id 前缀统一 lg-/lgnewui-/lgnew-/lg_ → withu-（含 lgnewuiHeaderActions→withuHeaderActions、lgnew-new-photo-*→withu-new-photo-* 等）；后台目录 _external/lgadmin→_external/withuadmin；后台 cookie lg_admin→withu_admin。
  - 全局标识符改名：window.LG_CONFIG→WITHU_CONFIG、LG_AOS_CONFIG→WITHU_AOS_CONFIG、LG_COUNTUP_ENABLED→WITHU_COUNTUP_ENABLED、lg_love（音乐对象）→withu_love、lg_visitor_geo/lg_comment_*/lg_enter_to_send 等存储 key→withu_*、X-LG-Access-* 响应头→X-WithU-Access-*；app-config.json 顶层键 LG_CONFIG/LGApp_config→WITHU_CONFIG/WITHUApp_config（server.js 读 .WITHU_CONFIG，键名必须一致，否则配置注入失效）。
  - PHP 主站（backend/app）：withu_lg_ui.js/css→withu-sakura.js/css（樱花，header/footer/travel 引用已同步）、withu-withu-hero 冗余类名→withu-hero。
  - _external/ 下的 *.kikiw.cn（loveli/blog/wiki/www/auth-love）是外部参考站快照备份，**保留原始 lg 内容不动**；只有 _external/withuadmin（后台 UI）参与去 lg。
  - 验证方式：`node --check backend/server/*.js`、`php -l`、重启 8901 后 curl 全页面与全部静态资源 200、8902 首页 withu-hero/withu-sakura 正常、watch.php 代理 200。

[Project Knowledge Summary]
- Date: 2026-08-22
- Context: Discovered by Agent while 在 devbox 中配置完整开发环境（补齐 MariaDB/PHP/JDK/withUstrm）
- Category: Environment Configuration
- Instructions:
  - MariaDB root 默认走 unix_socket 认证，TCP(127.0.0.1) 登录被拒（1698）：PHP 主站须用专用账号 `withu`/`withu_dev`（已授权 couple_website 库），database.php 用 withu 而非 root。
  - JDK 21 安装在 /opt/jdk/current（Temurin 21.0.12.1），不在 apt 源；strm 构建/启动用 `JAVA_HOME=/opt/jdk/current`。磁盘 20G/16G 空闲、内存 7.8G 可用仅 ~200M，构建 gradle/nuxt 必须在后台终端并设 memory_percent。
  - withUstrm(strm) 组件：后端 jar=`backend/strm/backend/build/libs/openlisttostrm.jar`（gradlew bootJar -x test），前端=`frontend/.output/public`（npm ci + NUXT_APP_BASE_URL=/admin/strm.php/ npx nuxt generate）。运行目录 `/workspace/runtime/strm/`（含 jwt.txt/bridge-secret.txt），后端 127.0.0.1:8081（java -Xmx512m），bridge 127.0.0.1:3112。
  - PHP 网关 admin/strm.php 读取密钥路径是 `/workspace/backend/runtime/strm/{bridge-secret.txt,jwt.txt}`（dirname(__DIR__,2).'/runtime/strm/'），bridge.js 则读 `/workspace/runtime/strm/`——两处必须保持一致，否则网关转发 403。
  - 后台终端 timeout 默认 120s，长期驻留服务（node/php/java/bridge）创建时须设 timeout=0，否则进程会被自动终止（曾导致 strm 后端与 bridge 先后超时退出）。
  - 登录验证链路：GET login.php 取 name="_token" 表单值(存 PHPSESSID) → POST `_token=&username=withu_test&password=123456` → 302 /；影视数据统一由 withUstrm（SQLite）提供，MySQL 侧无媒体库迁移逻辑。
