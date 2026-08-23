# WithU 安卓端对接 API 文档

> 覆盖「情侣空间」与「共同观影」全部接口。本文档面向安卓端 App 开发者。

- 版本：v1.0
- 更新日期：2026-08-16
- 主服务：withU（PHP，默认端口 8902）
- 媒体库服务：withUstrm（Java，端口 8081，可由主服务网关 `/api/strm.php` 代理访问）

---

## 1. 基础信息

### 1.1 Base URL

安卓端一般只对接主服务，统一使用主服务地址：

```
http://<主机IP>:8902
```

所有接口路径以 `/api/` 开头：

```
GET  http://<主机IP>:8902/api/home.php
POST http://<主机IP>:8902/api/desktop.php?action=login
```

媒体库直连服务（可选，高级用法）：

```
http://<主机IP>:8081/api
```

### 1.2 通用响应格式

系统没有统一的 `{code,msg,data}` 结构，多数接口以 `success` 布尔字段作为成功标志，错误信息放在 `message`：

```json
// 成功
{"success": true, "items": [...], "page": 1}

// 失败
{"success": false, "message": "评论内容不能为空"}
```

旧接口（`home` / `albums` / `messages` / `comment` / `upload_*`）使用 `jsonResponse()` 输出；
新接口（`travel*` / `watch` / `desktop` / `strm` / `douban_chart` / `cz` / `like`）使用 `withu_json_response()` 输出。
两者结构一致，均以 `success` 判断。

HTTP 状态码约定：

| 状态码 | 含义 |
|---|---|
| 200 | 成功 |
| 400 | 参数错误 / CSRF 校验失败 |
| 401 | 未登录 |
| 403 | 无权限（非情侣账号角色） |
| 404 | 资源不存在 |
| 405 | 方法不允许 |
| 429 | 请求过于频繁（限流） |
| 500 / 502 | 服务器错误 |

### 1.3 认证机制（重要）

系统采用 **PHP Session + Cookie** 认证。安卓端实现方式：

1. **登录**：`POST /api/desktop.php?action=login`，请求体 JSON `{"username":"...","password":"..."}`。
   成功后服务端通过 `Set-Cookie` 下发 `PHPSESSID`（会话）与 `withu_device`（10 年信任设备，用于"记住我"），并在响应 JSON 中返回 `csrf_token` 与用户信息。
2. **Cookie 持久化**：安卓端必须实现 Cookie 存储（OkHttp CookieJar 或 WebView CookieManager），后续所有请求自动携带。重启 App 后凭 `withu_device` + 会话自动恢复登录态。
3. **CSRF**：所有写操作（POST/PUT/DELETE）必须携带 CSRF token，二选一：
   - 请求头：`X-CSRF-Token: <csrf_token>`
   - 请求体字段：`"_token": "<csrf_token>"`
   token 从 `bootstrap` 或 `login` 响应的 `csrf_token` 字段获取；若收到 400「请求已过期，请刷新页面」，重新拉取 `bootstrap` 换新 token。
4. **角色限制**：影视 / 旅行 / 共看等功能要求登录角色为情侣账号（`role ∈ {user1, user2}`），否则 403。

### 1.4 请求体类型

| 接口范围 | 请求体 |
|---|---|
| 新接口（travel / media / watch / desktop / strm / like） | JSON body（`application/json`） |
| 旧接口（comment / article_chat / upload） | 表单（`application/x-www-form-urlencoded`） |
| 上传接口 | `multipart/form-data` |

---

## 2. 认证与桌面端聚合接口

### 2.1 登录 `POST /api/desktop.php?action=login`

请求体：

```json
{"username": "user1", "password": "123456"}
```

响应（与 `bootstrap` 相同结构，登录成功无 CSRF 要求）：

```json
{
  "success": true,
  "server_time": "2026-08-16 12:00:00",
  "csrf_token": "a1b2c3...",
  "logged_in": true,
  "user": {"id": 1, "username": "user1", "nickname": "宝宝", "role": "user1", "avatar": "..."},
  "partner": {"id": 2, "username": "user2", "nickname": "亲爱的", "role": "user2", "avatar": "..."},
  "theme": {"preset": "pastel-couple", "mode": "auto", "custom": false, "colors": {}},
  "watch_config": {"poll_interval_ms": 500, "sync_threshold_ms": 1000, "heartbeat_interval_ms": 2500, "autoplay_enabled": true},
  "summary": {"media_count": 20, "recognized_media_count": 8, "watch_room_count": 1},
  "watch": {"room_code": "WithU Watch", "media_id": 1, "file_name": "xxx", "playback_state": "paused", "position_ms": 0, "speed": 1, "url": "/api/strm.php?action=resolve&id=1"}
}
```

### 2.2 引导 / 聚合数据 `GET /api/desktop.php?action=bootstrap`

无需登录。返回当前登录态、CSRF token、主题配置、共看配置与当前共看房间快照。安卓端启动时可先调用此接口确认登录态并取 CSRF token。

### 2.3 登出 `POST /api/desktop.php?action=logout`

需登录 + CSRF。响应 `{"success": true}`。

### 2.4 媒体库列表（剧集分组）`GET /api/desktop.php?action=library`

需情侣账号。参数：`q`（可选）、`type_id`（可选 0-4）、`page`、`per_page`（20-500，默认 240）。

响应 `{success, items:[剧集分组], page, per_page, has_more, query, type_id}`，每组含 `play_url`。

### 2.5 观影历史 `GET /api/desktop.php?action=history`

需情侣账号。无参数。

响应 `{success, items:[{id, media_id, file_name, series_name, episode_number, episode_title, cover_url, resolution, play_url, started_at, updated_at, watch_duration_ms, solo_duration_ms, together_duration_ms, last_position_ms, participants_count}]}`，最多 120 条。

### 2.6 文章详情 `GET /api/desktop.php?action=article&id={id}`

需情侣账号。响应 `{success, type:"article", item:{id,title,content,created_at,nickname,avatar}}`。

### 2.7 相册详情 `GET /api/desktop.php?action=album&id={id}`

需情侣账号。响应 `{success, type:"album", item:{id,name,description,created_at,nickname,avatar,images:[{url,thumbnail,description,created_at}]}}`（最多 120 张）。

### 2.8 发表留言 `POST /api/desktop.php?action=message`

需情侣账号 + CSRF。请求体 JSON：`content`（≤100 字，必填）、`is_public`（可选）。

响应 `{success, message_id}`。限流：60 秒 1 条 + 内容安全审核。

---

## 3. 情侣空间 API

### 3.1 首页聚合 `GET /api/home.php`

无需登录（加密内容对游客隐藏）。无参数。

```json
{
  "success": true,
  "love_start_date": "2020-01-01",
  "stats": {"articles": 12, "events": 3, "albums": 5, "messages": 20},
  "articles": [{"id", "title", "nickname", "avatar", "created_at_text", "is_encrypted", "can_view_content", "excerpt"}],
  "albums": [{"id", "name", "display_name", "is_encrypted", "created_at_text", "description", "image_count", "nickname", "avatar", "images": ["url"]}],
  "latest_messages": [{"id", "nickname", "avatar", "content", "time_ago"}]
}
```

### 3.2 相册列表 `GET /api/albums.php`

无需登录（未登录时加密相册不返回真实预览图）。

参数：`page`（可选，默认 1）、`per_page`（可选，默认 3，最大 30）。

响应：`{success, page, per_page, has_more, items:[{id, user_id, name, is_encrypted, created_at, description, image_count, nickname, avatar, is_co_created, images:[{src, type:"image"|"video"}]}]}`（每相册最多 9 张预览）。

### 3.3 留言列表 `GET /api/messages.php`

无需登录（登录用户额外可见自己的私密留言）。

参数：`page`（可选，默认 1）、`per_page`（可选，默认 6，最大 50）。

响应：`{success, page, per_page, has_more, items:[{id, nickname, avatar, content_html, time_ago, location}]}`。

### 3.4 点赞 / 取消点赞 `POST /api/like.php`

登录/游客均可（游客自动分配 session 内 guest key）。JSON body + CSRF。

参数：

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| target_type | string | 是 | `article` / `album` / `event` |
| target_id | int | 是 | 目标 ID |

响应：`{success, liked: bool, count: int}`。

### 3.5 发表评论 `POST /api/comment.php`

登录/游客均可（游客必填 QQ + 昵称）。表单 POST + CSRF（`_token`）。

参数：

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| article_id | int | 三选一 | 文章评论 |
| album_id | int | 三选一 | 相册评论 |
| event_id | int | 三选一 | 事件评论 |
| parent_id | int | 否 | 回复评论时填 |
| content | string | 是 | ≤100 字符 |
| qq | string | 游客必填 | 自动生成 qlogo 头像 |
| guest_nickname | string | 游客必填 | 游客昵称 |
| guest_avatar | string | 游客可选 | 游客头像 |

限流：登录用户 10 秒 1 条，IP 每小时 30 条。

响应：`{success, message:"评论发表成功", comment_id}`。

### 3.6 文章聊天块（情侣共创对话文章）

三个接口权限一致：需登录 + CSRF，且必须是文章作者或另一半（且有共同编辑权限）。

#### 发送对话块 `POST /api/article_chat_send.php`（表单）

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| article_id | int | 是 | 文章 ID |
| speaker | string | 否 | `male` / `female` / `system` |
| type | string | 否 | `text` / `image` / `video`，默认 text |
| content | string | 是 | 内容 |

限流：同用户 2 秒 1 条，IP 每分钟 60 次。
响应：`{success, block:{id, index, speaker, html}}`。

#### 撤回对话块 `POST /api/article_chat_revoke.php`（表单）

参数：`article_id`（必填）、`block_id`（必填）。
响应：`{success: true}`。

#### 调整顺序 `POST /api/article_blocks_sort.php`（表单）

参数：`article_id`（必填）、`blocks_json`（必填，JSON 数组 `[{"id":1,"index":0}, ...]`）。
响应：`{success: true}`。

### 3.7 文件上传（wangEditor 格式）

两个接口返回结构与其余接口不同：

```json
// 成功
{"errno": 0, "data": ["https://.../uploads/xxx.jpg"]}
// 失败
{"errno": 1, "message": "..."}
```

#### 图片上传 `POST /api/upload_image.php`

需登录 + CSRF，`multipart/form-data`，任意文件字段名，`_token` 必填，`article_id` 可选。

#### 视频上传 `POST /api/upload_video.php`

需登录 + CSRF。校验 MIME（`video/mp4` / `video/webm` / `video/ogg`）与扩展名，自动 ffmpeg 转码 H.264 + AAC。大小受站点限制（默认 5MB）或 `video_upload_ignore_site_limit` 开关约束。

### 3.8 旅行 / 天气 `GET|POST /api/travel.php`

需情侣账号。`action` 参数（GET 或 POST）：

| action | 方法 | 参数 | 响应 |
|---|---|---|---|
| weather | GET | `lat`（float）、`lng`（float） | `{success, cached, data:<open-meteo 原始 JSON>}` |
| geocode | GET | `q`（string 必填） | `{success, items:[<nominatim JSON>]}`（最多 5 条） |
| plan | POST | JSON+CSRF：`destination`（必填）、`prompt`、`start_date`、`end_date` | `{success, id, source:"ai"\|"local", plan:{summary, itinerary, weather_alerts, tickets, packing}}` |

### 3.9 足迹地图 `GET|POST /api/travel_map.php`

需情侣账号。`action`（默认 `snapshot`）：

| action | 方法 | 参数（JSON） | 响应 |
|---|---|---|---|
| snapshot | GET | 无 | `{success, positions, locations, routes}` |
| save_position | POST | `latitude`、`longitude`（必填）、`location_name`、`visibility`（private/couple/public） | `{success, message, +snapshot}` |
| save_location | POST | `latitude`、`longitude`、`title`（必填）、`location_name`、`description`、`visit_date`、`is_favorite` | `{success, id, message, +snapshot}` |
| delete_location | POST | `id`（必填） | `{success, +snapshot}` |
| save_route | POST | `points`（数组 ≥2 点）、`title`、`description`、`start_name`、`end_name`、`distance_km` | `{success, id, message, +snapshot}` |

### 3.10 QQ 昵称查询 `GET /api/qq_profile.php`

无需登录（IP 每小时 60 次限流）。参数：`qq`（必填）。

响应：`{success, avatar_url, nickname}`。

### 3.11 豆瓣榜单 `GET /api/douban_chart.php`

无需登录。参数：`type`（`movie`/`tv`，默认 movie）、`limit`（1-30，默认 12）。

响应：`{success, type, list:[{title,url,cover,id,rate,episodes_info,source:"cz"}], cached, fetched_at}`。

---

## 4. 共同观影 API

### 4.1 共看房间 `GET|POST /api/watch.php`

需情侣账号。`action` 从 `$_GET['action'] ?? $_POST['action'] ?? body['room_code']` 读取。房间号 `code` 从 `room` / `room_code` 读取。POST 写操作需 JSON + CSRF。

| action | 方法 | 参数 | 响应 / 行为 |
|---|---|---|---|
| list | GET | 无 | `{success, items:[{id,file_name,url,player_mode,player_code,...}]}` 已识别媒体前 200 |
| create | POST | `media_id`（必填） | `{success, room_code, room_id, history_id}` |
| default | POST | `media_id`（可选） | 加入/创建固定房间 "WithU Watch"；另一半在线且请求换片时返回 `{choice_required:true, current_media_id, ..., requested_media_id, ...}` |
| choose | POST | `choice`（together/solo）、`media_id`（可选） | together→join 默认房；solo→创建独立房 |
| end_together | POST | 无 | `{success, mode:"solo"}` |
| join | POST/GET | room code | 加入房间成员表 |
| heartbeat | POST | 无 | `{success, server_now_ms}` |
| state | GET | `since`（可选） | 房间全量状态 |
| poll | GET | `since`（必填 >0） | 增量事件轮询（最多 20 条） |
| event | POST | 见下 | 上报播放/语音事件 |

**state / poll 响应结构：**

```json
{
  "success": true, "mode": "together", "server_now_ms": 123456,
  "room": {"code": "WithU Watch", "media_id": 1, "file_name": "xxx", "series_name": "yyy",
           "episode_number": 1, "playback_state": "playing", "position_ms": 60000,
           "speed": 1, "url": "...", "cover_url": "...", "duration_ms": 0,
           "resolution": "1080p", "rating": 8.5, "summary": "...", "tags": "...",
           "cast_names": "...", "douban_id": "", "last_sync_unix_ms": 123456},
  "members": [{"user_id": 1, "joined_at": "...", "last_seen_at": "..."}],
  "events": [{"id": 1, "user_id": 1, "event_type": "play", "position_ms": 60000, "speed": 1, "payload": null, "created_at": "..."}],
  "last_event_id": 10
}
```

**上报事件 `event`（POST JSON + CSRF）：**

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| event_type | string | 是 | `play` / `pause` / `seek` / `speed` / `leave` / `voice_offer` / `voice_answer` / `voice_candidate` / `voice_leave` / `chat_message` |
| position_ms | int | 播放类事件必填 | 播放位置 |
| speed | float | 否 | 0.5–3.0，步进 0.5 |
| client_timestamp_ms | int | 否 | 用于补偿传输延迟（最多 1500ms） |

响应：`{success, event_id}`。

**同步建议**：
- 播放端每 `poll_interval_ms`（默认 500ms）调 `watch.php?action=poll&since=lastEventId` 做增量同步；
- 收到 `play` / `pause` / `seek` / `speed` 事件后，若与本地位置偏差超过 `sync_threshold_ms`（默认 1000ms），将本地播放器对齐到事件 `position_ms`；
- 每 `heartbeat_interval_ms`（默认 2500ms）上报一次心跳。

### 4.2 withUstrm 媒体库网关 `GET /api/strm.php`

需情侣账号（未登录 401）。`action`（默认 `info`）。内部通过 HS256 JWT（`sub=withu_admin`）代理 `http://127.0.0.1:8081/api/media-library/**`，安卓端无需关心内部凭证。

| action | 方法 | 参数 | 响应 |
|---|---|---|---|
| info | GET | 无 | `{success, data:{serverName:"withUstrm", version, baseUrl, authEnabled, supportedMediaTypes}}` |
| counts | GET | 无 | `{success, data:{total, movie, series}}` |
| media | GET | `type`（movie/tv）、`keyword`、`page`、`pageSize`（≤100） | `{success, data:{total, page, pageSize, items:[{id,name,type,mediaType,originalTitle,year,posterUrl,backdropUrl,voteAverage,tmdbId,episodeCount}]}}` |
| detail | GET | `id`（必填） | `{success, data:{id,name,type,...,scrapeStatus,episodes:[{id,episodeNo,sourceFileName,sourcePath}]}}` |
| resolve | GET | `id`（必填）、`episode`（可选） | `{success, source:"strm", url, type:"m3u8"\|"mp4", name}` |
| posters | GET | 无 | `{success, data:{<mediaId>:"/api/strm.php?action=img&id=N", ...}}` |
| img | GET | `id`（必填） | `image/jpeg` 海报 |
| proxy | GET | `url`（http/https 必填） | 流式转发（支持 Range） |
| proxy_m3u8 | GET | `url`（m3u8 必填） | m3u8 改写为 proxy_seg |
| proxy_seg | GET | `url`（分片必填） | 转发 TS 分片（缓存 1h） |

### 4.3 共同观影播放流程（推荐链路）

```
1. 登录 → 取 csrf_token + Cookie
2. GET /api/desktop.php?action=library 获取媒体列表（items[].id 即 strm 媒体 id）
3. POST /api/watch.php?action=default {"media_id": N} 加入共看房间
4. 轮询 /api/watch.php?action=poll&since=X 同步播放状态
5. POST /api/watch.php?action=event 上报 play/pause/seek/speed
6. 播放地址：GET /api/strm.php?action=resolve&id=N（可带 &episode=M）拿 {url, type}
```

---

## 5. withUstrm 直连服务 API（可选，端口 8081）

> 仅当需要绕过 PHP 网关直连 withUstrm 后端时使用。普通安卓端推荐走上述 `/api/strm.php` 网关。

- 统一响应：`ApiResponse<T>` = `{"code":200,"message":"...","data":...}`，`code===200` 成功。
- 认证：除标注公开外，均需 `Authorization: Bearer <JWT>`；播放推荐用 `X-API-Key` 头（见下）。
- Swagger：`http://host:8081/swagger-ui.html`

### 5.1 认证（JWT）

| 方法 | URL | 说明 |
|---|---|---|
| POST | `/api/auth/sign-in` | 登录，body `{username,password}`，返回 `data={username, token, expiresAt}` |
| POST | `/api/auth/sign-up` | 注册（首个用户即管理员） |
| GET | `/api/auth/check-user` | 是否已有用户，`{exists}` |
| POST | `/api/auth/sign-out` | 登出（需 JWT） |
| POST | `/api/auth/refresh` | 刷新 token（需旧 token Bearer） |
| GET | `/api/auth/validate` | 校验 token |
| POST | `/api/auth/change-password` | 修改密码 |

### 5.2 媒体库（JWT）

| 方法 | URL | 说明 |
|---|---|---|
| GET | `/api/media-library` | 分页：`taskId/mediaType/keyword/page/pageSize`，返回 `PageResult{total,page,pageSize,items}` |
| GET | `/api/media-library/{id}` | 媒体详情 + `episodes[]` |
| GET | `/api/media-library/{id}/play` | `?sourceId=` 解析播放地址，返回 `{id,title,url,mediaType}` |
| GET | `/api/media-library/tasks` | 任务筛选项 |

### 5.3 外部播放接口（X-API-Key，推荐播放用）

鉴权：请求头 `X-API-Key: <key>` 或 `?apiKey=<key>`。

| 方法 | URL | 说明 |
|---|---|---|
| GET | `/api/external/info` | 服务信息 |
| GET | `/api/external/health` | 纯文本 `ok` |
| GET | `/api/external/media` | 分页媒体列表：`type/keyword/page/pageSize` |
| GET | `/api/external/media/{id}` | 详情 + 剧集 |
| GET | `/api/external/counts` | 媒体类型计数 |
| GET | `/api/external/stream/{id}` | 302 重定向到播放直链 |
| GET | `/api/external/episode/{episodeId}/stream` | 302 重定向指定剧集播放 |

错误码：401（Key 问题）、404（ID 不存在）、502（OpenList 配置缺失/停用/解析失败）。

### 5.4 其他管理接口（JWT，一般安卓端用不到）

| 前缀 | 说明 |
|---|---|
| `/api/media-servers` | Emby/Jellyfin 服务器配置与刷新 |
| `/api/openlist-config` | OpenList 配置 CRUD、浏览、识别 |
| `/api/task-config` | 刮削任务配置、手动刮削、目录结构检查 |
| `/api/system` | 系统配置、mihomo 状态、通知/AI 测试 |
| `/api/logs` | 日志读取/下载/清空（无需 JWT） |
| `/api/version` | 版本检查（无需 JWT） |
| `/api/data-report` | 事件数据上报 |
| `/api/strm-play/{configId}/**` | STRM 播放代理（匿名，302） |
| `/ws/**` | WebSocket |
| `/api/test/**` | 测试调试接口（无需 JWT） |

---

## 6. 常见流程示例

### 6.1 登录并保存 Cookie（OkHttp 示例）

```java
// 1. 登录
RequestBody body = new FormBody.Builder()
        .add("username", "user1")
        .add("password", "123456")
        .build();
Request req = new Request.Builder()
        .url(BASE_URL + "/api/desktop.php?action=login")
        .post(body)
        .build();
// 使用带 CookieJar 的 OkHttpClient，自动保存 PHPSESSID / withu_device
```

### 6.2 带 CSRF 的写请求

```java
// 从 bootstrap/login 响应取得 csrfToken
Request req = new Request.Builder()
        .url(BASE_URL + "/api/like.php")
        .addHeader("X-CSRF-Token", csrfToken)
        .post(jsonBody("{\"target_type\":\"article\",\"target_id\":1}"))
        .build();
```

### 6.3 共同观影同步循环（伪代码）

```
while (true) {
    // 1. 增量轮询
    state = GET /api/watch.php?action=poll&since=lastEventId
    for (event in state.events) {
        switch (event.event_type) {
            play  -> player.play(event.position_ms, event.speed)
            pause -> player.pause()
            seek  -> player.seek(event.position_ms)
            speed -> player.setSpeed(event.speed)
        }
        lastEventId = max(lastEventId, event.id)
    }
    // 2. 上报本地事件
    if (localChanged) POST /api/watch.php?action=event {...}
    // 3. 心跳
    POST /api/watch.php?action=heartbeat
    sleep(poll_interval_ms)
}
```

---

## 7. 安全与限流

| 项目 | 说明 |
|---|---|
| CSRF | 所有写操作必须携带 `X-CSRF-Token` 头或 `_token` 字段 |
| 评论 | 登录用户 10 秒 1 条，IP 每小时 30 条 |
| 聊天块 | 同用户 2 秒 1 条，IP 每分钟 60 次 |
| 留言 | 60 秒 1 条 |
| QQ 查询 | IP 每小时 60 次 |
| 内容审核 | 评论/留言/聊天内容经 `withu_moderate_text` 安全审核 |
| IP 黑名单 | 评论接口校验 IP 黑名单 |

---

## 8. 附录：错误码速查

| 错误 | 场景 |
|---|---|
| 400 请求已过期，请刷新页面 | CSRF token 失效，重新拉 `bootstrap` |
| 401 | 未登录或登录态失效 |
| 403 | 非情侣账号角色访问受限功能 |
| 404 | 资源不存在 / 仅允许 WebDAV 来源 |
| 429 | 触发限流 |
| 502 | OpenList 配置缺失/停用/解析失败（外部接口） |
