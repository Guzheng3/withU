# 宝塔面板可视化安装指南（withU + withUstrm）

> 面向宝塔面板用户的全可视化部署方案。withU 主站走宝塔站点 + Nginx，
> withUstrm 媒体服务走「Python 项目管理器 / Supervisor 管理器」托管，
> 两者通过 127.0.0.1 内网对接。

## 0. 环境与版本要求

| 组件 | 来源 | 版本 |
| --- | --- | --- |
| Nginx | 宝塔软件商店 | 1.24+ |
| PHP | 宝塔软件商店 | **8.2**（必须），安装后在「软件商店 → PHP 8.2 → 设置 → 安装扩展」勾选 `mbstring / curl / pdo_mysql / mysqli / gd / fileinfo` |
| MySQL / MariaDB | 宝塔软件商店 | 5.7+ / 8.0 |
| Java (JDK 21+) | 宝塔文件管理上传 | withUstrm 后端运行需要 |
| Node.js 18+ | 宝塔「软件商店 → Node.js 版本管理器」或文件管理上传 | withUstrm 前端 bridge 需要 |

## 1. withU 主站

### 1.1 上传代码

- 方案 A（推荐）：宝塔「文件」→ 进入 `/www/wwwroot` → 终端执行 `git clone https://github.com/Guzheng3/withU.git`
- 方案 B：本地压缩后通过文件管理上传并解压

### 1.2 创建站点

1. 宝塔 → **网站** → **添加站点**
2. 域名：你的域名（或 `IP:端口`）
3. 根目录：指向 withU **项目根目录**（`/www/wwwroot/withU`）
4. PHP 版本：`PHP-82`
5. 数据库：选 **MySQL**，宝塔自动创建，**记下库名、用户名、密码**

### 1.3 粘贴 Nginx 配置

1. 点击站点 → **设置** → **配置文件**
2. 把仓库 `deploy/baota-nginx-withu.conf` 的内容合并进 `server { }` 块（替换宝塔默认的 location 部分）
3. 保存（宝塔会自动 `nginx -t` 校验，报错即语法有问题）

该配置实现了与 `router.php` 等价的路由：
- `/admin/**`、`/api/**`、`watch*.php`、`player.php` → `backend/app/`
- 其余全部 → `frontend/`
- 禁止外部访问 `backend/`、`config/`、`core/`、`deploy/` 等敏感目录
- 静态资源 30 天缓存；上传目录禁止执行 PHP

### 1.4 PHP 参数

站点 → 设置 → **配置文件（PHP）** 或宝塔「软件商店 → PHP 8.2 → 设置 → 配置修改」：

```ini
upload_max_filesize = 1024M
post_max_size = 1024M
max_execution_time = 300
memory_limit = 512M
```

### 1.5 初始化

1. 在站点根目录创建空文件 `enable_install.lock`（文件管理 → 新建文件）
2. 浏览器访问 `你的域名/install.php`，按向导填写数据库信息，完成初始化
3. 初始化完成后 **删除** `enable_install.lock`

## 2. withUstrm 媒体服务

### 2.1 上传与构建

1. 宝塔「文件」→ `/www/wwwroot` → 终端：`git clone https://github.com/Guzheng3/withUstrm.git`
2. 终端构建（仅首次需要）：

```bash
cd /www/wwwroot/withUstrm
bash install-linux.sh
```

脚本会自动：检查 Java/Node → 构建前后端 → 生成数据目录 → 启动服务 → 启用外部媒体库接口并生成 API Key（输出中有提示）。
构建完成后该脚本就退场了，后续生命周期交给宝塔管理。

### 2.2 加入宝塔守护（可视化启停/日志/开机自启）

在宝塔安装「**Python 项目管理器**」（软件商店搜索，免费）。

1. 打开 Python 项目管理器 → **项目** → **添加项目**
2. 填写两个项目：

| 字段 | 后端 | bridge |
| --- | --- | --- |
| 项目名称 | `withustrm-backend` | `withustrm-bridge` |
| 启动方式 | 选择「Shell 命令」或「脚本路径」 | 同左 |
| 脚本路径 | `/www/wwwroot/withUstrm-data/start-backend.sh` | `/www/wwwroot/withUstrm-data/start-bridge.sh` |
| 项目目录 | `/www/wwwroot/withUstrm-data` | `/www/wwwroot/withUstrm` |

3. 保存后宝塔会自动以 **Supervisor** 守护：开机自启、崩溃自动拉起
4. 管理界面可直接「启动/停止/重启/查看日志」

> 说明：`start-backend.sh` / `start-bridge.sh` 由 `install-linux.sh` 生成，
> 只监听 `127.0.0.1`。如需公网访问管理界面，再建一个站点反代
> `http://127.0.0.1:3111`（网站 → 反代），否则保持内网即可。

### 2.3 对接主站

1. 登录 withU 后台 → **影视与播放 → withUstrm 媒体库**（`/admin/strm_settings.php`）
2. 服务地址：`http://127.0.0.1:8081`
3. API Key：复制 `/www/wwwroot/withUstrm-data/external-api-key.txt` 内容
4. 点击「测试连接」→ 保存。成功后访问 `/watch.php` 即可浏览媒体库。

## 3. TMDB 刮削（可选）

国内服务器刮削需要额外配置，方案与脚本部署一致，详见 `README.md` 的
「TMDB 国内访问方案」章节。withUstrm 管理界面（`127.0.0.1:3111`）→
系统设置中配置 TMDB API Key 与图片反代地址即可。

## 4. 常见问题

| 现象 | 处理 |
| --- | --- |
| 站点打开 404 / 403 | 检查站点根目录是否指向项目根目录（含 `router.php` 那层），Nginx 配置是否合并完整 |
| `/watch.php` 打不开 | 确认 Nginx 配置中 `watch|watch_play|watch_history` 的 rewrite 段存在 |
| withUstrm 服务反复重启 | 宝塔「Python 项目管理器」查看日志；检查 `start-*.sh` 的 `exec` 行是否存在 |
| API Key 无效 | 重新执行 `bash install-linux.sh` 同步 Key 文件，或到管理界面重新生成 |
| 后台登录页样式丢失 | `/admin-assets/` 别名配置未生效，检查 Nginx 配置 |
