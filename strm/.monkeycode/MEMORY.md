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

[Project Knowledge Summary]
- Date: 2026-08-13
- Context: Discovered by Agent while fixing movie directory mis-matching (xcmnnyx wrongly matched to "电影故事" 683717) in ostrm project
- Category: Troubleshooting & Debugging
- Instructions:
  - `TaskMediaParser.parseMovie`/`parseSeries` 会把父目录名当作片名兜底；当父目录是媒体库分类目录（电影/电视剧/movie/tv/anime 等）时会导致所有文件被错误解析成该分类名并匹配到错误 TMDB 条目。修复方式：新增 `CATEGORY_DIR_PATTERN`（含 电影/电视剧/剧集/综艺/动漫/动画/movie/movies/tv/tvshows/anime 等）和 `isCategoryDirectory`，在父目录兜底和 titleSource 选择时跳过分类目录与季目录。
  - `recognizeByConfig`（识别接口）对每文件：resolve 引擎置信度 >=0.6 用 resolve 结果；否则回退 TaskMediaParser.parse；再 `<70` 时调 AI（AI 未启用返回 null 不生效）。低于 70 或 TMDB 未匹配（findMatch 返回 null）的文件应进入人工核验区（matched=false + matchMessage），不可静默跳过，也不可自动匹配。
  - 后端 Gradle 编译必须加 `--no-configuration-cache`（配置缓存复用会导致 Lombok `@Slf4j` 生成的 `log` 报 "cannot find symbol"，是缓存假象而非真实错误）。
  - 镜像构建命令：`cd /workspace/ostrm && DOCKER_BUILDKIT=1 docker build -t ostrm-app:latest .`；容器重建：`docker rm -f app` + `docker run -d --name app --restart always -p 3111:80` + 4 挂载（data/config→/maindata/config、data/db→/maindata/db、strm→/app/backend/strm、logs→/maindata/log）+ env（LOG_PATH/DATABASE_PATH/CONFIG_PATH/USER_INFO_PATH/FRONTEND_LOGS_PATH/SPRING_PROFILES_ACTIVE=prod/APP_VERSION=dev）。

[Project Knowledge Summary]
- Date: 2026-08-13
- Context: Discovered by Agent while diagnosing strm 文件 401 无法播放问题，最终实现应用播放代理方案
- Category: Troubleshooting & Debugging
- Instructions:
  - openlist.qinghan.vip 的 `/d/路径` 下载接口拒绝匿名访问（401 返回 "expire missing"），带 sign/token/Bearer/expire 参数均 401；该站点 `/api/fs/get` 返回的 `raw_url`（EOS 对象存储直链，`X-Amz-Expires=900` 短期签名）才是可播放地址（GET+Range 返回 206）。
  - STRM 文件内容直接写 OpenList `/d/` 路径会 401 无法播放。解决方案（应用播放代理）：strm 写入 `{strm_base_url}/api/strm-play/{configId}/{URL编码的OpenList完整路径}`；`StrmPlaybackController`（新增，permitAll 匿名访问）用 config 保存的 token 实时调 `resolveRawUrl` 换取 raw_url，302 重定向到对象存储直链。媒体库读 strm → 请求代理 → 302 → EOS 直链 206 可播放。
  - `StrmGenerationHandler.buildFileUrl`：openlistConfig.strmBaseUrl 非空时构造代理 URL（用 `file.getPath()` 完整路径，OpenList 虚拟路径不含 base_path，如 `/电视剧/南部档案/xxx.mkv`），否则回退 `url+?sign=`。
  - `strm_base_url` 配置项应填 ostrm 应用对外可访问地址（如 http://192.168.94.38:3111），外部媒体库需能访问它。
  - Spring Boot 路径映射 `/{configId}/{**path}` 非法（`**` 不能作 @PathVariable 变量名开头），会报 "Char '*' not allowed at start of captured variable name"；改用 `@GetMapping("/{configId}/**")` + `request.getRequestURI()` 手动解析并 `URLDecoder.decode`。
  - 新 controller 必须加入 WebSecurityConfig 的 publicEndPointMatcher（permitAll），否则媒体库无登录态请求返回 401。
  - 快速验证 jar 改动：`docker cp backend/build/libs/openlisttostrm.jar app:/app/openlisttostrm.jar && docker restart app`，但容器重建会丢失，最终需重建镜像固化。
  - resolve 引擎返回 tmdbId 时（新僵尸先生 132169/云边 1241905）不再被 `<70` 置信度阈值拦截，direct loadMatch 精确匹配；只有 tmdbId 为空才看置信度进人工核验。MediaInfo 新增 tmdbId 字段。

[Project Knowledge Summary]
- Date: 2026-08-14
- Context: Discovered by Agent while deploying ostrm preview after adding Douban debug tab
- Category: Environment Configuration
- Instructions:
  - ostrm 前端 Nuxt dev server 默认只监听 `[::1]:3000`，平台预览代理无法访问；`nuxt.config.ts` 需配置 `devServer: { host: '0.0.0.0', port: 3000 }` 才能被 `request_preview` 访问。
  - 后端 `bootRun` 用 background terminal 启动时不要设短 timeout（应用就绪后会被 killed_by_timeout 杀掉），应设 timeout=0 长期驻留。

[User Instruction Summary]
- Date: 2026-08-15
- Context: 用户指定 ostrm 部署端口约定（在优化媒体库海报显示后明确指示）
- Instructions:
  - 只保留 3111 这一组端口作为 ostrm 的部署端口；本地开发的 3000(Nuxt dev)/8080(Spring Boot) 及其内部辅助端口(如 37079/35411) 不用于预览交付。
  - 后续任何重构、重建、重新部署都必须部署到 3111 端口（Docker 容器 `app`：`docker run -d --name app --restart always -p 3111:80`），不得另起其他端口组。

