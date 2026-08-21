# 外部媒体库接口（Emby 风格）

本接口为第三方播放器 / 客户端（如 Infuse、VidHub、自研 Web 应用等）提供 Emby 风格的媒体库导航与播放能力。数据源复用内部媒体库的剧集聚合与播放解析逻辑。

## 启用方式

1. 进入「系统设置」→「外部媒体库接口」
2. 勾选「启用外部媒体库接口」
3. 填写或点击「生成随机 Key」生成 API Key
4. 点击「保存设置」

未启用时，所有 `/api/external/**` 接口返回 `401`。

## 认证

所有外部接口都需要携带 API Key，支持两种传法（二选一）：

**请求头方式：**

```http
X-API-Key: <your-api-key>
```

**查询参数方式：**

```http
GET /api/external/info?apiKey=<your-api-key>
```

API Key 不匹配或缺失时返回：

```json
{"code": 401, "message": "API Key 无效", "data": null}
```

## 基础地址

```
http://<host>:<port>/api/external
```

例如本机部署：`http://192.168.1.100:3111/api/external`

---

## 1. 服务信息

返回服务名称、版本、支持的类型等元信息。

```
GET /api/external/info
```

**响应示例：**

```json
{
  "serverName": "ostrm",
  "version": "2.2.6",
  "baseUrl": "/api/external",
  "authEnabled": true,
  "supportedMediaTypes": ["movie", "tv", "anime"]
}
```

---

## 2. 健康检查

```
GET /api/external/health
```

**响应：**

```
ok
```

---

## 3. 媒体列表

分页查询聚合后的媒体列表（电视剧按剧聚合，每部剧一条）。

```
GET /api/external/media
```

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `type` | string | 否 | 媒体类型：`movie` / `tv`（电视剧+动漫） |
| `keyword` | string | 否 | 关键词搜索（匹配标题） |
| `page` | int | 否 | 页码，默认 `1` |
| `pageSize` | int | 否 | 每页条数，默认 `24`，最大 `100` |

**响应示例：**

```json
{
  "total": 16,
  "page": 1,
  "pageSize": 3,
  "items": [
    {
      "id": 337,
      "name": "骄阳似我",
      "type": "Series",
      "mediaType": "tv",
      "originalTitle": "骄阳似我",
      "year": "2025",
      "overview": null,
      "posterUrl": "https://image.tmdb.org/t/p/w500/xxx.jpg",
      "backdropUrl": "https://image.tmdb.org/t/p/w1280/xxx.jpg",
      "voteAverage": 8.5,
      "tmdbId": 123456,
      "episodeCount": 36,
      "sourceFileName": null,
      "sourcePath": null
    }
  ]
}
```

**字段说明：**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | long | 媒体唯一 ID |
| `name` | string | 展示标题 |
| `type` | string | `Movie` 或 `Series` |
| `mediaType` | string | 原始类型：`movie` / `tv` / `anime` |
| `originalTitle` | string | 原始标题 |
| `year` | string | 发行年份 |
| `overview` | string | 简介（列表接口为 null，详情接口返回） |
| `posterUrl` | string | 海报地址 |
| `backdropUrl` | string | 背景图地址 |
| `voteAverage` | double | 评分 |
| `tmdbId` | int | TMDB ID |
| `episodeCount` | int | 集数（电影为 1） |

---

## 4. 媒体详情

返回单部媒体详情及其剧集列表。

```
GET /api/external/media/{id}
```

**响应示例（电视剧）：**

```json
{
  "id": 337,
  "name": "骄阳似我",
  "type": "Series",
  "mediaType": "tv",
  "originalTitle": "骄阳似我",
  "year": "2025",
  "overview": "……剧情简介……",
  "posterUrl": "https://image.tmdb.org/t/p/w500/xxx.jpg",
  "backdropUrl": "https://image.tmdb.org/t/p/w1280/xxx.jpg",
  "voteAverage": 8.5,
  "tmdbId": 123456,
  "scrapeStatus": "SUCCESS",
  "episodes": [
    {
      "id": 302,
      "episodeNo": 1,
      "sourceFileName": "骄阳似我 - S01E01 - 第 1 集.mkv",
      "sourcePath": "/电视剧/骄阳似我/骄阳似我 - S01E01 - 第 1 集.mkv"
    },
    {
      "id": 303,
      "episodeNo": 2,
      "sourceFileName": "骄阳似我 - S01E02 - 第 2 集.mkv",
      "sourcePath": "/电视剧/骄阳似我/骄阳似我 - S01E02 - 第 2 集.mkv"
    }
  ]
}
```

`episodes` 数组每个元素对应一集，`id` 即为该集的播放 ID。

---

## 5. 媒体类型计数

```
GET /api/external/counts
```

**响应示例：**

```json
{"total": 16, "movie": 4, "series": 12}
```

---

## 6. 解析媒体播放地址

将请求重定向到媒体实际播放地址（OpenList 实时换取的对象存储直链）。

```
GET /api/external/stream/{id}
```

**行为：** 返回 `302 Found`，`Location` 指向真实播放地址。播放器跟随重定向即可。

**示例：**

```bash
curl -L -H "X-API-Key: your-key" http://host:3111/api/external/stream/337
```

---

## 7. 解析剧集播放地址

将请求重定向到指定剧集的播放地址。

```
GET /api/external/episode/{episodeId}/stream
```

`{episodeId}` 为详情接口 `episodes[].id`。

**行为：** 返回 `302 Found`，`Location` 指向该集的真实播放地址。

**示例：**

```bash
curl -L -H "X-API-Key: your-key" http://host:3111/api/external/episode/306/stream
```

---

## 错误码

| HTTP 状态 | 说明 |
|-----------|------|
| `401` | 接口未启用 / API Key 缺失或不匹配 |
| `404` | 媒体 ID 不存在 |
| `502` | OpenList 配置不存在、已停用或播放地址解析失败 |

## 常见问题

**Q：接口返回 401？**
确认已在系统设置中启用外部接口并配置了 API Key，且请求携带的 Key 与配置一致。

**Q：播放地址解析失败 / 502？**
确认该媒体关联的 OpenList 配置仍然存在且处于启用状态。
