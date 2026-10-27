# 天气 API（课表端 / 网站共用）

天气由 withU 服务端统一向和风天气（QWeather）获取，客户端不接触 API Key、JWT Token 或私钥。

## 1. 认证与入口

课表 App 走现有课表接口，复用与 `login` / `bootstrap` / `partner` 相同的会话：

```http
GET {baseUrl}/api/timetable.php?action=weather&mode=now&lat=30.29&lng=120.15
Cookie: PHPSESSID=...; withu_device=...
Accept: application/json
```

- 需情侣账号登录；未登录 `401`，非情侣角色 `403`。
- 天气为只读 GET，不需要 CSRF token。
- 网站前台使用 `GET /services/weather.php`，保持原 `{code:200,data}` 契约，底层复用同一套和风配置。

## 2. 请求参数

| 参数 | 必填 | 说明 |
|---|---|---|
| `action` | 是 | 固定 `weather` |
| `mode` | 否 | `now`（默认）/ `hourly` / `daily` / `lookup` |
| `lat` / `lng` | now/hourly/daily 必填 | WGS84 十进制坐标；服务端按和风要求转成 `经度,纬度` 并保留两位小数 |
| `q` | lookup 二选一 | 城市/地点关键字 |
| `days` | 否 | daily 用，默认 `7`，支持 `3/7/10/15` |
| `hours` | 否 | hourly 用，默认 `24`，支持 `24/72/168`（取决于和风套餐） |
| `lang` | 否 | 默认 `zh`，透传和风语言参数 |
| `unit` | 否 | 默认 `m`（公制），可选 `i` |
| `label` | 否 | 展示名覆盖；非空时服务端不再做反查，直接用该名称作为 `location.name` |
| `number` | 否 | lookup 返回条数，1–20，默认 10 |

## 3. 响应外层

```json
{
  "success": true,
  "code": 200,
  "server_time": "2026-10-26 22:10:00",
  "source": "qweather",
  "cached": false,
  "stale": false,
  "location": {
    "name": "杭州市",
    "latitude": 30.29,
    "longitude": 120.15,
    "admin1": "浙江省",
    "country": "中国",
    "district": null,
    "timezone": "Asia/Shanghai",
    "adcode": "101210101"
  },
  "data": {}
}
```

- `location` 同时兼容 qhkb `WeatherLocation.fromJson` 的字段命名。
- `cached=true` 表示命中新鲜缓存；`stale=true` 表示上游失败、返回的是 24 小时内的旧缓存。

## 4. mode=now

`data` 同时提供 qhkb 现有 `CurrentWeather.fromAmapLive` 兼容字段和 withU 网页字段：

```json
{
  "weather": "多云",
  "temperature": "21",
  "humidity": "63",
  "winddirection": "东北风",
  "windpower": "3",
  "reporttime": "2026-10-26 22:00:00",

  "temp": 21,
  "feelsLike": 20,
  "desc": "多云",
  "icon": "101",
  "windDir": "东北风",
  "windScale": "3",
  "vis": 16,
  "city": "杭州市",
  "obsTime": "2026-10-26T22:00+08:00",

  "wmo_code": 2,
  "wind360": 45,
  "windSpeed": 12,
  "precip": 0.0,
  "pressure": 1013,
  "cloud": 10,
  "dew": 14,
  "available": true
}
```

App 可直接调用 `CurrentWeather.fromAmapLive(data)`；如需按 WMO 分类，使用 `weatherCategoryFromWmo(data.wmo_code)`。

## 5. mode=hourly

`data` 与 Open-Meteo `/v1/forecast` 的 `hourly` 列式结构同构：

```json
{
  "latitude": 30.29,
  "longitude": 120.15,
  "hourly": {
    "time": ["2026-10-26T23:00", "2026-10-27T00:00"],
    "temperature_2m": [20, 19],
    "precipitation_probability": [10, 60],
    "precipitation": [0.0, 0.5],
    "snowfall": [null, null],
    "weather_code": [2, 61]
  }
}
```

- `time` 为不带时区偏移的墙钟时间 `YYYY-MM-DDTHH:mm`，与课程时间同语义。
- `weather_code` 已由服务端把和风图标码映射为 WMO 码，App 现有聚合/分类逻辑可直接复用。
- `snowfall` 和风小时预报不提供，固定为 `null`。
- 和风逐小时通常为 24 小时；`72h/168h` 取决于账号套餐。

App 可直接调用 `WeatherForecast.fromOpenMeteoResponse(data, fetchedAt: DateTime.now())`。

## 6. mode=daily

```json
{
  "daily": [
    {
      "fxDate": "2026-10-27",
      "tempMax": 23,
      "tempMin": 14,
      "iconDay": "101",
      "textDay": "多云",
      "iconNight": "150",
      "textNight": "晴",
      "windDirDay": "东北风",
      "windScaleDay": "1-3",
      "windDirNight": "东风",
      "windScaleNight": "1-3",
      "humidity": 65,
      "precip": 0.0,
      "pressure": 1013,
      "vis": 16,
      "uvIndex": 4,
      "sunrise": "06:10",
      "sunset": "17:20",
      "weather_code": 2
    }
  ]
}
```

## 7. mode=lookup

```json
{
  "success": true,
  "code": 200,
  "locations": [
    {
      "name": "杭州市",
      "latitude": 30.28746,
      "longitude": 120.15358,
      "admin1": "浙江省",
      "country": "中国",
      "district": null,
      "timezone": "Asia/Shanghai",
      "adcode": "101210101",

      "id": "101210101",
      "lat": "30.28746",
      "lon": "120.15358",
      "adm1": "浙江省",
      "adm2": "杭州市",
      "tz": "Asia/Shanghai",
      "utcOffset": "+08:00",
      "type": "city",
      "rank": "10"
    }
  ]
}
```

`locations[]` 可直接交给 `WeatherLocation.fromJson`。和风 GeoAPI 为城市级反查，`district` 通常为 `null`。

## 8. 错误响应

```json
{
  "success": false,
  "code": 502,
  "error": "weather_auth_failed",
  "message": "和风天气认证失败，请检查 API Key 或 Token 配置",
  "server_time": "2026-10-26 22:10:00"
}
```

| HTTP | error | 含义 |
|---|---|---|
| 400 | `weather_invalid_params` | 缺少坐标/参数非法 |
| 401 | `weather_login_required` | 未登录或会话失效 |
| 403 | `weather_couple_required` | 非情侣账号 |
| 429 | `weather_rate_limited` | 和风限流 |
| 502 | `weather_auth_failed` | Key/Token 无效或无权限 |
| 502 | `weather_quota_exceeded` | 和风额度/套餐不足 |
| 502 | `weather_upstream_error` | 和风异常 |
| 503 | `weather_not_configured` | 后台尚未配置和风 |
| 504 | `weather_timeout` | 上游超时 |

App 对 `weather_not_configured`、`weather_upstream_error`、`weather_timeout` 应静默隐藏天气。

## 9. 服务端配置

后台「设置 → 基础信息 → 天气服务」：

| 配置项 | 说明 |
|---|---|
| `qweather_api_host` | 和风控制台分配的 API Host，可只填域名 |
| `qweather_auth_type` | `auto` / `apikey` / `jwt` |
| `qweather_api_key` | API Key 模式 |
| `qweather_jwt_token` | 静态 Token，优先级最高 |
| `qweather_jwt_key_id` | JWT 自动签发的 `kid` |
| `qweather_jwt_developer_id` | JWT 自动签发的 `iss`（Q 开头的 Developer ID） |
| `qweather_jwt_project_id` | JWT 自动签发的 `sub`（Project ID） |
| `qweather_jwt_private_key` | Ed25519 私钥 PEM，仅服务端保存 |
| `qweather_lang` | 默认 `zh` |
| `qweather_unit` | 默认 `m` |

自动模式的选择顺序：静态 Token → API Key → JWT 私钥自动签发（15 分钟有效，缓存到过期前 60 秒）。

## 10. 缓存

| 模式 | TTL |
|---|---|
| `now` | 5 分钟 |
| `hourly` | 30 分钟 |
| `daily` | 60 分钟 |
| `lookup` | 24 小时 |

缓存文件位于 `backend/app/runtime/weather-cache/`，不包含任何凭据。上游失败时优先返回 24 小时内的旧缓存。
