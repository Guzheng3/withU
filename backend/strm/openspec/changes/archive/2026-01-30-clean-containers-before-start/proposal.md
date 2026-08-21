## Why

当前 `dev-docker.sh` 脚本在启动时不会检查已有容器和镜像，导致重复启动时可能使用旧镜像或残留容器，造成部署不一致的问题。

## What Changes

- 新增启动前检查和清理已有容器/镜像的功能
- 强制重新构建镜像（不使用缓存）
- 移除所有辅助功能（备份、进入容器、开发模式、健康检查等），只保留核心启动流程

## Capabilities

### New Capabilities
- `clean-before-start`: 启动前自动清理已有容器和镜像，确保干净部署环境

## Impact

- 修改 `dev-docker.sh` 脚本
- 简化脚本功能，只保留 start 命令
