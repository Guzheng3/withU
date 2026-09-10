# 本地启动脚本说明（端口与配置来源）

本目录有两套**互不相同**的本地启动栈，端口刻意错开，避免同机同时跑两套时互相占用。

| 栈 | 入口脚本 | MariaDB | withU PHP | 说明 |
| --- | --- | --- | --- | --- |
| Linux / WSL | `start-linux.sh` / `stop-linux.sh` | `3306`（`MYSQL_PORT`） | `1314`（`WITHU_PORT`） | 默认栈，README 主推；服务只监听 `127.0.0.1` |
| Windows | `start-withu.cjs` / `stop-withu.cjs` | `3307`（写死） | `3314`（写死） | 在 Windows 上跑 PHP + MariaDB 的旧栈 |

## 配置文件是怎么来的

两个脚本都会生成 **同一份**配置，只是落点不同：

```text
config/config.php       ← 脚本生成的“母本”（不入库）
config/database.php     ← 脚本生成的“母本”（不入库）
        │ 复制
        ▼
backend/app/config/config.php
backend/app/config/database.php   ← 运行时真正被读取的那份
```

- `start-linux.sh`：`MYSQL_PORT="${MYSQL_PORT:-3306}"`、`WITHU_PORT="${WITHU_PORT:-1314}"`，生成后 `cp` 到 `backend/app/config/`。
- `start-withu.cjs`：`port = 3314`、MariaDB `--port=3307`，仅在 `backend/app/config/database.php` **不存在**时写入。

> 代码里所有页面/接口读取的都是 `backend/app/config/`（`frontend/inc/config.php`、`frontend/services/*.php`、`frontend/assets/map-api.php` 都解析到 `dirname(__DIR__, 2) . '/backend/app'`）。
> 根目录 `config/` 只是母本，**不被运行时读取**。

## 端口对不上时怎么办

现象：页面报「数据库连接失败」，而 `ss -ltn | grep 3306` 显示数据库其实在 3306 上。

原因：`backend/app/config/database.php` 里的 `port` 与当前实际运行的 MariaDB 不一致（典型情况是 Windows 栈生成的 `3307` 母本，被 WSL 栈的 3306 实例覆盖/共存）。

处理（任选其一）：

```bash
# 1) 让配置跟随正在运行的库（推荐，改一行）
#    backend/app/config/database.php  →  'port' => 3306
sed -i "s/'port' => 3307/'port' => 3306/" backend/app/config/database.php

# 2) 或者按目标栈重新生成配置（删掉母本后重跑脚本）
rm config/database.php config/config.php
bash deploy-local/start-linux.sh      # 3306 / 1314
# 或（Windows）node deploy-local/start-withu.cjs   # 3307 / 3314
```

`start-linux.sh` 支持环境变量覆盖：`MYSQL_PORT=3307 WITHU_PORT=3314 bash deploy-local/start-linux.sh`。

## 其它

- `mcp-image-analyze.cjs`：本地 MCP 图片分析服务，与站点运行无关。
- `php.ini`：本地 PHP 参数参考（大文件上传等）。
- `runtime/`：脚本产生的日志与运行数据（不入库）。
