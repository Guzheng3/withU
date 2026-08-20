#!/usr/bin/env bash
# withU + withUstrm + mihomo  Linux 一键停止
set -u
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
WORKROOT="$(dirname "$ROOT")"
log() { echo "[$(date '+%H:%M:%S')] $*"; }

stop_pidfile() {
  local name="$1"
  local pf="$WORKROOT/runtime/$name.pid"
  if [ -f "$pf" ]; then
    local pid=$(cat "$pf" 2>/dev/null)
    if [ -n "${pid:-}" ] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null; sleep 1; kill -9 "$pid" 2>/dev/null || true
      log "已停止 $name (pid $pid)"
    fi
    rm -f "$pf"
  fi
}

# 按监听端口杀（兜底）
kill_by_port() {
  local port="$1" name="$2"
  local pid=$(ss -ltnp 2>/dev/null | grep ":$port " | grep -oP 'pid=\K[0-9]+' | head -1)
  [ -n "${pid:-}" ] && kill "$pid" 2>/dev/null && log "已停止 $name (pid $pid)"
}

stop_pidfile withu-php
kill_by_port 1314 withu-php
stop_pidfile strm-bridge
kill_by_port 3112 strm-bridge
stop_pidfile strm-backend
kill_by_port 8081 strm-backend
stop_pidfile mihomo
stop_pidfile mariadb

log "服务已全部停止（如需停 MariaDB 服务本身请用 systemctl stop mariadb）"
