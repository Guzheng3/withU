# withU 部署到 Linux 服务器（含内置 mihomo 代理托管）

withU + withUstrm 全家桶在 **Linux 服务器**上的部署指南。核心特性：
- **内置 mihomo/clash 代理**：只填一个订阅地址，mihomo 自动拉订阅、`url-test` 自动挑选可用节点，本地起混合代理端口供 TMDB 刮削/海报下载使用——服务器无需任何 GUI 客户端。
- 所有服务只绑 `127.0.0.1`，withU 后台是唯一对外入口（8088 由 Nginx/PHP 反代时对外）。

## 1. 环境准备（Debian/Ubuntu）

```bash
sudo apt update
sudo apt install -y git curl mariadb-server \
  php-cli php-mysql php-gd php-curl php-xml php-mbstring php-zip php-intl php-sodium \
  openjdk-21-jre-headless nodejs npm unzip
sudo systemctl enable --now mariadb
```

> Java 必须 ≥21（withUstrm 后端要求）。Node 建议 ≥18（前端 `nuxt generate` 需要）。

## 2. 拉取代码

```bash
cd /opt
git clone https://github.com/Guzheng3/withU.git
cd withU
# 工作目录 = /opt（runtime 存到 /opt/runtime，不入 git）
```

## 3. 配置代理订阅地址（关键）

**二选一**（推荐写进 config 文件，重启不丢）：

```bash
# 方式 A：环境变量
export WITHU_PROXY_SUB_URL="https://你的机场订阅地址"

# 方式 B：配置文件（部署后常驻）
cat > config/mihomo.json <<'EOF'
{
  "subUrl": "https://你的机场订阅地址",
  "port": 7897,
  "mirror": ""
}
EOF
```

- `subUrl`：机场订阅链接（Clash/Surge 格式均可，mihomo proxy-providers 直接消费）
- `port`：混合代理端口，默认 **7897**（与 withUstrm 的 TMDB 代理配置一致，勿改）
- `mirror`：留空=直连下载 mihomo 二进制；服务器访问 GitHub 慢可填 `https://ghproxy.net/`
- 未配置订阅时：若服务器已有 `7897` 在监听（如系统级 Clash）则直接复用，否则跳过（TMDB 刮削会失败，但主站不受影响）

## 4. 首次启动（自动初始化 + 构建 + 启动）

```bash
bash deploy-local/start-linux.sh
```

脚本幂等，会自动：
1. 启动 MariaDB（systemctl 或手动 mariadbd）
2. 建 `couple_website` / `withu_media` 库并导入 schema、生成 `config/config.php` / `config/database.php`
3. 下载 mihomo 二进制（**优先复用系统已有 mihomo**，没有则下载对应 Linux 架构）→ 生成配置 → 启动 → 自动选节点
4. 构建 withUstrm 后端（gradlew bootJar）+ 前端（nuxt generate）→ 启动 8080/3111
5. 启动 withU PHP 服务 8088

## 5. 对外暴露（仅 withU 后台）

推荐用 Nginx 反代 withU，并把 withUstrm 关在后台里（已是默认）:

```nginx
server {
    listen 80;
    server_name your.domain.com;
    root /opt/withU;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
    location /admin/strm.php/ {
        proxy_pass http://127.0.0.1:8088/admin/strm.php/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

安全边界（默认已满足）：
- withUstrm 后端 8080 / bridge 3111 / mihomo 7897 **全部只绑 127.0.0.1**，外网不可达
- withU 后台网关 `admin/strm.php` 需情侣账号登录 + bridge 内部密钥校验，未登录直接 302 到 `/login.php`
- TMDB 走 127.0.0.1:7897（mihomo AUTO 组自动选可用节点）

## 6. 常用操作

```bash
bash deploy-local/start-linux.sh   # 启动全家（幂等）
bash deploy-local/stop-linux.sh    # 停止全家
tail -f /opt/runtime/mihomo/mihomo.log    # mihomo 日志
tail -f /opt/runtime/strm/backend.log     # withUstrm 后端日志
```

换订阅/加节点：改 `config/mihomo.json` 的 `subUrl` → 重启 mihomo（`bash deploy-local/stop-linux.sh && bash deploy-local/start-linux.sh`）→ mihomo 自动重新拉取订阅并健康检查。

## 7. 常见问题

| 现象 | 处理 |
|---|---|
| TMDB 刮削失败/海报空 | 检查 `config/mihomo.json` 订阅是否配置、`curl -x 127.0.0.1:7897 https://api.themoviedb.org` 是否 200 |
| `[mihomo] 未配置订阅地址` | 设置 `WITHU_PROXY_SUB_URL` 或写 `config/mihomo.json` |
| mihomo 二进制下载失败 | 服务器直连 GitHub 慢 → 在 `config/mihomo.json` 设 `mirror: "https://ghproxy.net/"`；或提前 `apt install mihomo`（部分发行版有） |
| withUstrm 后端构建失败 | 确认 `java -version` ≥21、`node -v` ≥18 |
| MariaDB root 有密码 | 在 `start-linux.sh` 中把 `MYSQL` 变量改为带密码，或先 `sudo mysql` 建好库 |

## 8. Windows 本地开发（对照）

本机 Windows 上：`node repo/deploy-local/start-withu.cjs`（WMI 拉起、scoop 路径），未配订阅时自动复用本机 Clash（7897）。两套脚本逻辑一致、配置格式相同。
