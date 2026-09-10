# ⚠️ 已停用：遗留 Node 服务

本目录是早期基于 LikeGirl 协议的 Node 后台（`server.js` + `admin.js` / `lg-admin-server.js` / `store.js` / `reverse-proxy.js`），
**自 `router.php` + PHP 后台（`backend/app/`）上线后已完全停用**，保留仅为历史参考。

## 运行时状态

| 项 | 说明 |
| --- | --- |
| 是否参与运行 | 否。站点由 `router.php`（或 `deploy/baota-nginx-withu.conf`）统一路由到 `backend/app/` + `frontend/` |
| 端口 | 旧脚本默认 `8899`（`PORT` 环境变量），与当前站点端口（1314）无关 |
| 数据 | `data/*.json`、`admin-data/oplog.jsonl` 为旧服务的 JSON 存储，已由 MySQL 表取代 |

## 暴露面

- `router.php`：显式对 `/backend` 与 `/backend/**` 返回 404（见 `router.php` 顶部路由段）。
- Nginx：`deploy/baota-nginx-withu.conf` 中 `location ^~ /backend` 为 `internal;`，且额外声明 `location ^~ /backend/server/ { deny all; }`，外部不可达。

## 注意

`app-config.json` 内含旧的第三方天气 token 与站点展示配置；`data/` 内含访客信标数据。
这些文件不参与当前站点运行，**不要**把它们挂到任何对外可访问的路径上。

## 迁移去向

| 旧文件 | 现在的位置 |
| --- | --- |
| `server.js` / `reverse-proxy.js` | `router.php` |
| `admin.js` / `lg-admin-server.js` | `backend/app/admin/` |
| `store.js` + `data/*.json` | MySQL（`backend/app/database/schema.sql`） |
