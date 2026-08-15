## Context

当前 `dev-docker.sh` 脚本功能复杂，包含多种命令（start、stop、logs、exec、backup 等）。启动前不会清理已有容器和镜像，可能导致部署不一致。

## Goals / Non-Goals

**Goals:**
- 简化脚本，只保留核心启动流程
- 启动前自动检查并清理已有容器和镜像
- 强制重新构建镜像，确保使用最新代码

**Non-Goals:**
- 不保留开发模式（热重载）
- 不保留备份、进入容器、日志查看等辅助功能
- 不保留健康检查功能

## Decisions

1. **清理策略**: 使用 `docker-compose down --rmi all --volumes` 完全清理容器和镜像
2. **构建策略**: 使用 `docker-compose build --no-cache` 强制重新构建
3. **部署策略**: 使用 `docker-compose up -d` 启动服务

## Risks / Trade-offs

- [风险] 镜像构建时间较长 → 确保网络连接稳定
- [风险] 本地开发时每次构建耗时 → 如需快速启动，可后续添加开发模式选项
