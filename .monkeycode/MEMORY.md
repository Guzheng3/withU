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
- Date: 2026-08-18
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
  - 数据文件集中在 lg-server/data/：map-config.json（高德 Key 与 securityMode=proxy，Key=39b478526482ffb6c069eee6f78faf77）、weather-config.json（后台手配城市1/2+ip）、chat-data.json、interactions.json、beacons.json；相册照片扫描 lg-site/Lovefolder/，元数据在 lg-server/admin-data/photos-meta.json。
  - 高德采用「代理服务安全模式」（_AMapSecurityConfig={serviceHost:'/',serviceMode:'proxy'}），由服务端转发 /_AMapService/** 到 webapi.amap.com，无需 securityJsCode；10 个 HTML 页面的硬编码 amapKey/securityJsCode 由 server.js 正则替换注入。
  - 后台登录：POST /admin/api/login（JSON body {adminName,pw}，pw 为明文由服务端 md5），cookie lg_admin；默认账号 admin/lovezz。
  - 测试坑：store.js 的 load() 对 '@mapAll'/'@lgConfig' 特殊 key 必须经 ABS_MAP 映射到绝对路径，否则读到空数据；https.request headers 里 referer 不能设为 undefined（Node22 抛 ERR_HTTP_INVALID_HEADER_VALUE），应 delete。

[Project Knowledge Summary]
- Date: 2026-08-21
- Context: Discovered by Agent while 实现首页观影按钮跳转 watch.php
- Category: Environment Configuration
- Instructions:
  - withU 观影/影视后端在项目根（PHP 主站，跑在 1314，数据库 couple_website，含 media_library/watch_history/watch_rooms 等表）：watch.php（影视库页）、watch_play.php（播放页）、watch_history.php、api/watch.php（房间）、api/strm.php、api/media_cover.php。lg-site(8901) 不包含这些 PHP，lg-server 静态根是 lg-site/。
  - lg-server/server.js 增加 PHP 主站代理 proxyToPhp()：lg-site 静态 404 且路径以 .php 结尾或 /api/ 开头时，转发到 http://127.0.0.1:1314（保留 method/query/body/cookie，删除响应 transfer-encoding/connection 头）。首页「观影」悬浮按钮 href="watch.php" 即经此代理可达。
  - 代理 POST 关键坑：lg-server 的 createServer 在 req.on('end') 内统一处理，body 已收集进 body 变量，此时 req 流已结束，proxyToPhp 必须用收集的 body（preq.end(body) + 按字节重设 content-length），不能用 req.pipe(preq)——否则 PHP 收到 Content-Length 声明但 body 空，一直等 body 直到超时挂起。
  - PHP 主站 login.php 登录需 CSRF：先 GET 取表单 name="_token" 值（存 PHPSESSID），POST 时带 _token。登录成功 302 /；未登录访问 watch.php 302 /login.php。
  - 验证用测试账号 withu_test/123456 已写入 couple_website.users（role=user1）；真实账号 withu1/WithU@1314。
  - 1314 端口前端已彻底移除：1314 现为反向代理（lg-server/reverse-proxy.js）→ 8901 lg-server，1314 域名直接显示 lg-site 完整前端（lg-site/index.html），首页即情侣空间入口；withU PHP 主站服务移到 127.0.0.1:8902（仅本机，外部不可直接访问），lg-server server.js 的 PHP_BACKEND=8902，lg-site 观影按钮（8901/watch.php 或 1314/watch.php）经代理→8902 PHP 保持可用。
