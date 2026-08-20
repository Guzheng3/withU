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

