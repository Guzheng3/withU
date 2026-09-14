#!/bin/sh
# CodeGraph 代码图谱索引同步脚本
#
# 用法:
#   scripts/codegraph-sync.sh          # 同步当前项目索引并输出结果
#   scripts/codegraph-sync.sh -q       # 静默同步(适合 git hooks / 自动任务)
#
# 依赖: codegraph CLI(独立安装, 无需 Node)。安装方式:
#   curl -fsSL https://raw.githubusercontent.com/colbymchenry/codegraph/main/install.sh | sh
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
QUIET=""

case "${1:-}" in
  -q|--quiet) QUIET="-q" ;;
  "") ;;
  *) echo "usage: $0 [-q]" >&2; exit 2 ;;
esac

if ! command -v codegraph >/dev/null 2>&1; then
  echo "codegraph 未安装。请先运行:" >&2
  echo "  curl -fsSL https://raw.githubusercontent.com/colbymchenry/codegraph/main/install.sh | sh" >&2
  exit 1
fi

# 索引尚未初始化时先 init，否则做增量 sync
if [ ! -d "$ROOT/.codegraph" ]; then
  echo "CodeGraph 索引不存在，执行首次初始化..."
  codegraph init "$ROOT" $QUIET
else
  codegraph sync "$ROOT" $QUIET
fi
