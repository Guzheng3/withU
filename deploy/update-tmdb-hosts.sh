#!/usr/bin/env bash
# ============================================================
# update-tmdb-hosts.sh —— TMDB hosts 自动更新（对抗 DNS 污染）
# 数据源：cnwikee/CheckTMDB 每日更新的 Tmdb_host_ipv4
# 只替换 /etc/hosts 中本脚本管理的标记块，不碰其他内容。
#
# 用法：
#   bash deploy/update-tmdb-hosts.sh
#   # 建议加入 cron 每日自动更新：
#   # 17 7 * * * root bash /path/to/withU/deploy/update-tmdb-hosts.sh >> /var/log/tmdb-hosts.log 2>&1
#
# 说明：仅写入 IPv4 条目（国内多数服务器无 IPv6 出口，AAAA 记录反而会拖垮连接）。
# ============================================================
set -u

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] [tmdb-hosts] $*"; }

HOSTS="${HOSTS_FILE:-/etc/hosts}"
MARK_BEGIN="# BEGIN WITHU-TMDB"
MARK_END="# END WITHU-TMDB"
BACKUP_DIR="${HOSTS_BACKUP_DIR:-/var/lib/tmdb-hosts-backup}"
mkdir -p "$BACKUP_DIR"

RAW_URLS=(
  "https://raw.githubusercontent.com/cnwikee/CheckTMDB/main/Tmdb_host_ipv4"
  "https://ghproxy.net/https://raw.githubusercontent.com/cnwikee/CheckTMDB/main/Tmdb_host_ipv4"
  "https://gh-proxy.com/https://raw.githubusercontent.com/cnwikee/CheckTMDB/main/Tmdb_host_ipv4"
)
TMP="$(mktemp)"

SOURCE=""
for u in "${RAW_URLS[@]}"; do
  if curl -sf --max-time 20 -o "$TMP" "$u" && grep -q "api\.themoviedb\.org" "$TMP"; then
    SOURCE="$u"
    break
  fi
done

if [ -z "$SOURCE" ]; then
  rm -f "$TMP"
  log "ERROR: 所有数据源拉取失败，保留现有 hosts 不变"
  exit 1
fi
log "数据源: $SOURCE"

# 只保留 TMDB 域名族（过滤掉文件里的 imdb/trakt/fanart 条目，缩小影响面）。
# 过滤用 python3 字符串查表实现（bash/grep/awk 的长交替正则在部分环境会静默失配）。
FILTERED=$(python3 - "$TMP" <<'PY'
import sys
ok = {'tmdb.org', 'api.tmdb.org', 'api.themoviedb.org', 'files.tmdb.org', 'themoviedb.org',
      'www.themoviedb.org', 'auth.themoviedb.org', 'image.tmdb.org', 'images.tmdb.org'}
for line in open(sys.argv[1], encoding='utf-8', errors='ignore'):
    parts = line.split()
    if len(parts) >= 2 and parts[1] in ok:
        print(f"{parts[0]}\t\t{parts[1]}")
PY
)
rm -f "$TMP"

NEW_BLOCK="$MARK_BEGIN
$FILTERED
$MARK_END"

# 纯 bash 字符串匹配校验，不依赖外部命令
case "$FILTERED" in
  *api.themoviedb.org*) : ;;
  *) log "ERROR: 拉取内容校验失败（缺少关键域名条目），保留现有 hosts 不变"; exit 1 ;;
esac

cp "$HOSTS" "$BACKUP_DIR/hosts.$(date +%Y%m%d%H%M%S)"
ls -1t "$BACKUP_DIR"/hosts.* 2>/dev/null | tail -n +8 | xargs -r rm -f   # 只留最近 7 份

# 移除旧管理块（若存在），再追加新块
if grep -qF "$MARK_BEGIN" "$HOSTS"; then
  sed -i "/^${MARK_BEGIN}$/,/^${MARK_END}$/d" "$HOSTS"
fi
sed -i -e '${/^$/d;}' "$HOSTS"
printf '\n%s\n' "$NEW_BLOCK" >> "$HOSTS"

COUNT=$(printf '%s\n' "$FILTERED" | grep -cE '^[0-9]')
log "OK: 已写入 $COUNT 条 TMDB 解析记录"
exit 0
