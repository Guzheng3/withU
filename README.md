# WithU

WithU 是面向两位授权用户的情侣专属网站，提供点滴记录、留言墙、爱情相册、纪念事件、地图与足迹、天气旅行、一起看/同步观影，并通过对接独立部署的 **withUstrm** 媒体服务，提供影视库浏览与在线播放能力。

项目地址：[GitHub](https://github.com/Guzheng3/withU.git)

## 功能

- **影视库**：`/watch.php` 提供影视浏览、搜索、筛选、分组、最近播放和详情页，数据来自 withUstrm 媒体库。
- **网页播放**：`/watch_play.php` 支持 MP4 / HLS 直链播放、分集切换、播放历史、倍速与手势操作。
- **一起看**：两位用户加入同一房间，同步播放/暂停、拖动、换集，支持聊天与弹幕；strm 媒体同样可入房同步。
- **站点功能**：文章日记、相册、留言墙、纪念事件、地图足迹、天气旅行、评论审核、信任设备等。
- **管理后台**：Admin v3 轻量通透界面，涵盖内容管理、影视与播放设置、系统管理。

## 架构

| 服务 | 说明 | 默认地址 |
| --- | --- | --- |
| withU 主站 | PHP 站点，`frontend/`（前台）+ `backend/app/`（后台与接口），`router.php` 统一路由 | `http://127.0.0.1:1314` |
| withUstrm 后端 | Spring Boot（独立仓库），SQLite 存储（首次启动自动建库），提供媒体库与外部接口 | `http://127.0.0.1:8081` |
| withUstrm bridge | Node 静态服务，承载 Nuxt 前端并反代后端 API | `http://127.0.0.1:3111` |

withU 与 withUstrm 通过 **外部媒体库接口** 对接（`/api/external/**`，`X-API-Key` 鉴权），主站服务端持有 Key 并代理全部请求，前端拿不到任何凭据。

## 技术要求

| 组件 | 版本 | 用途 |
| --- | --- | --- |
| PHP | 8.2+（启用 PDO MySQL、cURL、OpenSSL、JSON、mbstring、fileinfo、GD） | withU 主站 |
| MariaDB / MySQL | 5.7+ | withU 主站数据 |
| OpenJDK | 21+ | withUstrm 后端构建与运行 |
| Node.js | 18+ | withUstrm 前端构建与 bridge |
| FFmpeg | 可选 | 转码与视频封面，使用独立发布包 |

## 目录结构

| 路径 | 用途 |
| --- | --- |
| `frontend/` | 前台页面与静态资源 |
| `backend/app/` | 后台管理、接口、影视对接网关 |
| `backend/app/api/strm.php` | withUstrm 媒体库网关（列表/详情/解析/流式代理） |
| `backend/app/admin/strm_settings.php` | 后台「withUstrm 媒体库」对接配置页 |
| `deploy-local/` | 脚本一键部署（Linux / WSL） |
| `deploy/` | Nginx / 宝塔站点配置与 PHP 参数参考 |
| `config/` | 运行时生成的站点配置（不入库） |

---

## 安装与部署

提供两种方式：**方式一 脚本一键**（Linux / WSL，推荐）与 **方式二 宝塔面板可视化**。

### 方式一：脚本一键安装（Linux / WSL）

#### 1. 安装依赖

```bash
# Debian / Ubuntu
sudo apt update
sudo apt install -y mariadb-server php-cli php-mysql php-gd php-curl php-xml php-mbstring php-zip \
  openjdk-21-jre-headless nodejs npm git curl
sudo systemctl enable --now mariadb
```

#### 2. 安装并启动 withU 主站

```bash
git clone https://github.com/Guzheng3/withU.git
cd withU
bash deploy-local/start-linux.sh
```

脚本幂等，自动完成：检查 MariaDB → 创建 `couple_website` 库并导入结构 → 生成 `config/config.php` / `config/database.php` 与 `.installed` → 启动 PHP 内置服务器。输出示例：

```text
withU 前台:  http://127.0.0.1:1314/
withU 后台:  http://127.0.0.1:1314/admin/
```

停止：`bash deploy-local/stop-linux.sh`。

#### 3. 安装并启动 withUstrm

```bash
cd ..        # 与 withU 同级
git clone https://github.com/Guzheng3/withUstrm.git
cd withUstrm
bash install-linux.sh
```

脚本自动完成：

1. 环境检查（Java 21+ / Node 18+，版本不符直接报错）；
2. 构建后端（`gradlew bootJar`，首次会下载 Gradle 发行版，耐心等）与前端（`npm install` + `nuxt generate`，产物已存在则跳过，`FORCE_BUILD=1` 可强制重建）；
3. 初始化数据目录 `../withUstrm-data`（SQLite 数据库由后端首次启动时 Flyway 自动建表，无需配置）；
4. 首次创建管理员账号（默认用户名 `admin`，随机密码仅显示一次；可用 `ADMIN_USER` / `ADMIN_PASS` 环境变量指定）；
5. 生成启动脚本并启动后端（8081）与 bridge（3111），均只监听 `127.0.0.1`；
6. 自动启用「外部媒体库接口」并生成 API Key，保存到 `withUstrm-data/external-api-key.txt`。

可用环境变量：`WITHUSTRM_DATA`（数据目录）、`BACKEND_PORT`（默认 8081）、`BRIDGE_PORT`（默认 3111）、`ADMIN_USER` / `ADMIN_PASS`、`FORCE_BUILD=1`。

子命令：`bash install-linux.sh stop` 停止、`restart` 重启。

#### 4. 对接两个服务

1. 登录 withU 后台（`http://127.0.0.1:1314/admin/`）；
2. 进入 **影视与播放 → withUstrm 媒体库**（`/admin/strm_settings.php`）；
3. 服务地址填 `http://127.0.0.1:8081`，API Key 粘贴 `withUstrm-data/external-api-key.txt` 的内容；
4. 点击「测试连接」，看到媒体计数后保存；
5. 访问 `/watch.php` 浏览媒体库，播放与一起看入口均在影视库内。

### 方式二：宝塔面板可视化安装

面向宝塔面板用户的全可视化部署方案：withU 主站走宝塔站点 + Nginx，withUstrm 媒体服务走「Python 项目管理器」托管，两者通过 `127.0.0.1` 内网对接。

#### 环境与版本要求

| 组件 | 来源 | 版本 |
| --- | --- | --- |
| Nginx | 宝塔软件商店 | 1.24+ |
| PHP | 宝塔软件商店 | **8.2**（必须），安装后在「软件商店 → PHP 8.2 → 设置 → 安装扩展」勾选 `mbstring / curl / pdo_mysql / mysqli / gd / fileinfo` |
| MySQL / MariaDB | 宝塔软件商店 | 5.7+ / 8.0 |
| Java (JDK 21+) | 宝塔文件管理上传 | withUstrm 后端运行需要 |
| Node.js 18+ | 宝塔「软件商店 → Node.js 版本管理器」或文件管理上传 | withUstrm 前端 bridge 需要 |

#### 1. 上传代码

- 方案 A（推荐）：宝塔「文件」→ 进入 `/www/wwwroot` → 终端执行 `git clone https://github.com/Guzheng3/withU.git`
- 方案 B：本地压缩后通过文件管理上传并解压

#### 2. 创建站点

1. 宝塔 → **网站** → **添加站点**
2. 域名：你的域名（或 `IP:端口`）
3. 根目录：指向 withU **项目根目录**（`/www/wwwroot/withU`，即含 `router.php` 的那层）
4. PHP 版本：`PHP-82`
5. 数据库：选 **MySQL**，宝塔自动创建，**记下库名、用户名、密码**

#### 3. 粘贴 Nginx 配置

1. 点击站点 → **设置** → **配置文件**
2. 把仓库 `deploy/baota-nginx-withu.conf` 的内容合并进 `server { }` 块（替换宝塔默认的 location 部分）
3. 保存（宝塔会自动 `nginx -t` 校验，报错即语法有问题）

该配置实现了与 `router.php` 等价的路由：

- `/admin/**`、`/api/**`、`watch*.php`、`player.php` → `backend/app/`
- 其余全部 → `frontend/`
- 禁止外部访问 `backend/`、`config/`、`core/`、`deploy/` 等敏感目录
- 静态资源 30 天缓存；上传目录禁止执行 PHP

#### 4. PHP 参数

站点 → 设置 → **配置文件（PHP）** 或宝塔「软件商店 → PHP 8.2 → 设置 → 配置修改」：

```ini
upload_max_filesize = 1024M
post_max_size = 1024M
max_execution_time = 300
memory_limit = 512M
```

#### 5. 初始化主站

1. 在站点根目录创建空文件 `enable_install.lock`（文件管理 → 新建文件）
2. 浏览器访问 `你的域名/install.php`，按向导填写数据库信息，完成初始化
3. 初始化完成后 **删除** `enable_install.lock`

#### 6. withUstrm 上传与构建

1. 宝塔「文件」→ `/www/wwwroot` → 终端：`git clone https://github.com/Guzheng3/withUstrm.git`
2. 终端构建（仅首次需要）：

```bash
cd /www/wwwroot/withUstrm
bash install-linux.sh
```

脚本会自动：检查 Java/Node → 构建前后端 → 生成数据目录 → 启动服务 → 启用外部媒体库接口并生成 API Key（输出中有提示）。构建完成后该脚本退场，后续生命周期交给宝塔管理。

#### 7. 加入宝塔守护（可视化启停/日志/开机自启）

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

> `start-backend.sh` / `start-bridge.sh` 由 `install-linux.sh` 生成，只监听 `127.0.0.1`。如需公网访问管理界面，再建一个站点反代 `http://127.0.0.1:3111`（网站 → 反代），否则保持内网即可。

#### 8. 对接主站

1. 登录 withU 后台 → **影视与播放 → withUstrm 媒体库**（`/admin/strm_settings.php`）
2. 服务地址：`http://127.0.0.1:8081`
3. API Key：复制 `/www/wwwroot/withUstrm-data/external-api-key.txt` 内容
4. 点击「测试连接」→ 保存。成功后访问 `/watch.php` 即可浏览媒体库

#### 常见问题

| 现象 | 处理 |
| --- | --- |
| 站点打开 404 / 403 | 检查站点根目录是否指向项目根目录（含 `router.php` 那层），Nginx 配置是否合并完整 |
| `/watch.php` 打不开 | 确认 Nginx 配置中 `watch|watch_play|watch_history` 的 rewrite 段存在 |
| withUstrm 服务反复重启 | 宝塔「Python 项目管理器」查看日志；检查 `start-*.sh` 的 `exec` 行是否存在 |
| API Key 无效 | 重新执行 `bash install-linux.sh` 同步 Key 文件，或到管理界面重新生成 |
| 后台登录页样式丢失 | `/admin-assets/` 别名配置未生效，检查 Nginx 配置 |

### Nginx 部署说明

- `deploy/baota-nginx-withu.conf` 与 `router.php` 路由等价（`/admin`、`/api`、`watch*.php` → `backend/app/`，其余 → `frontend/`），并包含敏感目录拦截、uploads 禁执行、静态资源缓存与大文件上传参数，已经真实 Nginx 实例逐条验证。
- 若使用 `php -S` 内置服务器（方式一），无需 Nginx 配置，`router.php` 即路由入口。

---

## withUstrm 媒体库对接（安全边界）

- API Key 只保存在主站数据库（服务器端），前端拿不到任何凭据；
- 影视库、播放、解析接口全部要求情侣账号（user1/user2）登录；
- withUstrm 建议只绑定内网地址，不对外暴露；如需公网访问其管理界面，用 Nginx 反代并自行加访问控制。

## TMDB 国内访问方案

withUstrm 刮削依赖 TMDB。国内网络环境下 `api.themoviedb.org` 通常被 DNS 污染、`image.tmdb.org` 图床普遍不可达，推荐以下组合方案（两者互补，缺一不可）。

### 第一步：API 走 hosts 直连（修复 DNS 污染）

`api.themoviedb.org` 的问题本质是 DNS 污染，用 [CheckTMDB](https://github.com/cnwikee/CheckTMDB) 每日更新的可用 IP 写入 `/etc/hosts` 即可解决：

```bash
# 立即执行一次（需 root）
bash deploy/update-tmdb-hosts.sh

# 加入 cron 每日自动更新（07:17）
echo '17 7 * * * root bash /path/to/withU/deploy/update-tmdb-hosts.sh >> /var/log/tmdb-hosts.log 2>&1' \
  > /etc/cron.d/withu-tmdb-hosts
```

脚本只替换 `/etc/hosts` 中 `# BEGIN WITHU-TMDB` ~ `# END WITHU-TMDB` 标记块，不碰其他条目；拉取失败或校验不过时保持原样；自动备份旧文件。注意只写入 IPv4 条目——国内多数服务器无 IPv6 出口，AAAA 记录反而会拖垮连接。

### 第二步：图床走 Cloudflare Worker 反代

`image.tmdb.org` 无法通过 hosts 解决，用 Cloudflare 边缘节点反代。仓库已提供现成代码 [`deploy/cloudflare-worker-tmdb.js`](./deploy/cloudflare-worker-tmdb.js)：

1. 登录 [dash.cloudflare.com](https://dash.cloudflare.com) → **Workers 和 Pages** → 创建 → 命名（如 `tmdb-proxy`）→ 部署；
2. **编辑代码**：删除默认内容，粘贴 `deploy/cloudflare-worker-tmdb.js` 全文，重新部署；
3. **绑定自定义域**：Worker → 设置 → 域和路由 → 添加自定义域（如 `tmdb.example.com`）。
   域名 DNS 必须托管在 Cloudflare；`*.workers.dev` 默认域在国内不稳定，不要用；
4. 验证：

```bash
curl https://tmdb.example.com/3/movie/550
# → 401 JSON（未带 API key 的正常响应，说明链路已通）
curl -o t.png https://tmdb.example.com/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png
# → 真实 PNG 图片
```

代码中已启用 24 小时边缘缓存（`cacheEverything`），海报第二次起毫秒级返回，批量刮削提速明显。免费额度每天 10 万次请求，个人使用足够；**不要公开分享该域名**，避免被滥用。

### 第三步：配置 withUstrm（混合路由）

在 withUstrm 管理界面 → 系统设置 → TMDB 中填写：

| 配置项 | 值 | 说明 |
| --- | --- | --- |
| `baseUrl` / `chinaApiUrl` | `https://api.themoviedb.org` | API 走 hosts 直连，快且稳 |
| `imageBaseUrl` / `chinaImageUrl` | `https://tmdb.example.com` | 图床走 Worker 反代（唯一可行路径） |
| `apiKey` | 你的 TMDB API Key | [themoviedb.org](https://www.themoviedb.org/settings/api) 免费注册获取 |

> 实测结论：API 小 JSON 请求直连（0.9~4s）比绕 Worker（1~10s 且偶发超时）更稳，故 API 不绕反代；图床则必须走反代。

### 其他注意事项

- **无 IPv6 出口的服务器**：Cloudflare 域名为双栈，Java 不会像 curl 那样自动回退 IPv4，启动需加 `-Djava.net.preferIPv4Stack=true`（`install-linux.sh` 已自动检测并附加）。
- **可选优化（优选 IP）**：若 Worker 域名时快时慢，可对 CF 边缘 IP 做延迟探测，把最快 IP 写入 `/etc/hosts` 固定该域名，稳定性显著提升。
- **备选方案**：若已有机场订阅，可用 mihomo 等代理统一解决（withUstrm 支持 `tmdb.proxyHost` / `tmdb.proxyPort`）；hosts + Worker 方案的优势是零依赖、零成本。

## FFmpeg 独立运行包

FFmpeg 用于视频兼容性转码、媒体探测和封面生成，不随仓库分发。请从发布文件下载 `withU-ffmpeg-runtime-*.zip` 解压到站点根目录，确保存在：

```text
bin/ffmpeg/ffmpeg
bin/ffmpeg/ffprobe
```

Linux 下注意保留可执行权限（`chmod +x`）。没有 FFmpeg 时直链播放仍可用，仅转码与自动封面不可用。

## 本地开发

```bash
# Linux / WSL
bash deploy-local/start-linux.sh    # 一键启动（幂等）
bash deploy-local/stop-linux.sh     # 一键停止

# PHP 语法检查
php -l backend/app/watch_play.php
```

Windows 环境可参考 `deploy-local/start-withu.cjs`（路径按本机调整）。

## 安全说明

- 站点不提供公开注册，仅两位授权用户（user1/user2）可登录。
- 运行时生成的 `config/config.php`、`config/database.php`、`.installed` 不入库，注意保管 `SECRET_KEY`。
- 媒体播放直链经主站服务端解析与代理，签名地址不暴露给前端。
