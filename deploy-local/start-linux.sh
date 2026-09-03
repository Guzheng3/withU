#!/usr/bin/env bash
# ============================================================
# withU  Linux 服务器一键启动
# ============================================================
# 前置要求（首次部署）：
#   1. apt install mariadb-server php-cli php-mysql php-gd php-curl php-xml php-mbstring
#   2. git clone https://github.com/Guzheng3/withU.git && cd withU
#   3. bash deploy-local/start-linux.sh    ← 启动服务（本脚本，幂等）
# ============================================================
set -u
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
WORKROOT="$(dirname "$ROOT")"     # 工作目录（runtime 所在）
MYSQL_PORT="${MYSQL_PORT:-3306}"
WITHU_PORT="${WITHU_PORT:-1314}"
log() { echo "[$(date '+%H:%M:%S')] $*"; }

is_listening() { (echo >/dev/tcp/127.0.0.1/$1) >/dev/null 2>&1; }

detach() { # 脱离会话、稳定后台运行，并写 pid 文件
  local name="$1" cmd="$2" logfile="$3"
  nohup setsid bash -c "$cmd" >>"$logfile" 2>&1 &
  disown 2>/dev/null || true
  echo $! >"$WORKROOT/runtime/$name.pid"
  log "启动 $name (pid $!) → $logfile"
}

# ---------- 1. MariaDB ----------
log "检查 MariaDB (端口 $MYSQL_PORT)..."
if is_listening $MYSQL_PORT; then
  log "MariaDB 已在运行"
else
  if command -v systemctl >/dev/null 2>&1 && systemctl is-active mariadb >/dev/null 2>&1; then
    log "systemctl 管理的 MariaDB 已运行"
  else
    if command -v mariadbd >/dev/null 2>&1; then
      log "手动启动 mariadbd..."
      detach mariadb "mariadbd --bind-address=127.0.0.1 --port=$MYSQL_PORT" "$WORKROOT/runtime/mariadb.log"
    elif command -v mysqld_safe >/dev/null 2>&1; then
      log "启动 mysqld_safe..."
      detach mariadb "mysqld_safe --port=$MYSQL_PORT" "$WORKROOT/runtime/mariadb.log"
    else
      log "ERROR: 未找到 mariadbd/mysqld，请先 apt install mariadb-server"
      exit 1
    fi
    for i in $(seq 1 30); do is_listening $MYSQL_PORT && break; sleep 1; done
    is_listening $MYSQL_PORT || { log "ERROR: MariaDB 未就绪"; exit 1; }
  fi
fi

# ---------- 1.5 首次初始化（幂等：库已存在则跳过） ----------
log "检查数据库初始化..."
MYSQL="mysql --protocol=tcp -h 127.0.0.1 -P $MYSQL_PORT -u root"
if ! $MYSQL -e "USE couple_website" >/dev/null 2>&1; then
  log "初始化 couple_website 库（导入 database/schema.sql）..."
  $MYSQL -e "CREATE DATABASE IF NOT EXISTS couple_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  $MYSQL couple_website < "$ROOT/backend/app/database/schema.sql" || { log "ERROR: schema.sql 导入失败"; exit 1; }
  log "couple_website 初始化完成"
fi
if ! $MYSQL -e "USE withu_media" >/dev/null 2>&1; then
  log "初始化 withu_media 库..."
  $MYSQL -e "CREATE DATABASE IF NOT EXISTS withu_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'withu'@'127.0.0.1' IDENTIFIED BY 'withu_dev'; GRANT ALL ON withu_media.* TO 'withu'@'127.0.0.1'; CREATE USER IF NOT EXISTS 'withu'@'localhost' IDENTIFIED BY 'withu_dev'; GRANT ALL ON withu_media.* TO 'withu'@'localhost'; FLUSH PRIVILEGES;"
  log "withu_media 初始化完成"
fi
if [ ! -f "$ROOT/config/config.php" ]; then
  log "生成 config/config.php..."
  mkdir -p "$ROOT/config" "$ROOT/backend/app/config"
  cat > "$ROOT/config/config.php" <<PHPEOF
<?php
define('DEBUG_MODE', false);
date_default_timezone_set('Asia/Shanghai');
define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', 'http://127.0.0.1:$WITHU_PORT');
define('UPLOAD_DIR', ROOT_PATH.'/uploads/');
define('UPLOAD_URL', BASE_URL.'/uploads/');
define('MAX_FILE_SIZE', 5*1024*1024);
define('SECRET_KEY', 'withu-linux-' . bin2hex(random_bytes(16)));
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900);
define('LOGIN_LOCKOUT_SECONDS', 900);
define('SITE_NAME', '我们的小情侣网站');
PHPEOF
  cp "$ROOT/config/config.php" "$ROOT/backend/app/config/config.php"
fi
if [ ! -f "$ROOT/config/database.php" ]; then
  log "生成 config/database.php..."
  mkdir -p "$ROOT/config" "$ROOT/backend/app/config"
  cat > "$ROOT/config/database.php" <<PHPEOF
<?php
return ['host'=>'127.0.0.1','port'=>$MYSQL_PORT,'dbname'=>'couple_website','username'=>'root','password'=>'','charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]];
PHPEOF
  cp "$ROOT/config/database.php" "$ROOT/backend/app/config/database.php"
fi
touch "$ROOT/.installed" "$ROOT/backend/app/.installed"
mkdir -p "$ROOT/backend/app/uploads" "$ROOT/backend/app/runtime" "$ROOT/backend/app/storage" "$ROOT/backend/app/logs"

# ---------- 2. withU PHP 服务 ----------
log "启动 withU PHP 服务 (127.0.0.1:$WITHU_PORT)..."
if is_listening $WITHU_PORT; then
  log "withU 已在运行"
else
  if command -v php-fpm >/dev/null 2>&1 && [ -f /etc/nginx/sites-enabled/withu ]; then
    log "检测到 nginx+php-fpm 配置，请用 systemctl start nginx php-fpm"
    systemctl start nginx php8.1-fpm 2>/dev/null || systemctl start nginx php-fpm 2>/dev/null || true
  else
    detach withu-php "php -S 127.0.0.1:$WITHU_PORT -t '$ROOT' '$ROOT/router.php'" "$WORKROOT/runtime/withu-php.log"
    for i in $(seq 1 10); do is_listening $WITHU_PORT && break; sleep 1; done
    log "withU PHP 就绪: http://127.0.0.1:$WITHU_PORT/"
  fi
fi

log "=============================================="
log "全部启动完成："
log "  withU 前台:        http://127.0.0.1:$WITHU_PORT/"
log "  withU 后台:        http://127.0.0.1:$WITHU_PORT/admin/"
log "=============================================="
