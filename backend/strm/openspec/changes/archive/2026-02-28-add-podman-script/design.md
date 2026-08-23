# Design: Podman 启动脚本设计

## 概述

`dev-podman.sh` 脚本将作为 `dev-docker.sh` 的 Podman 版本，提供相同的功能集。

## 命令行接口

```bash
./dev-podman.sh [command]

Commands:
  start           启动服务（默认）- 清理并重新构建镜像后启动
```

## 技术实现

### 1. 依赖检查

```bash
# 检查 podman
if ! command -v podman &> /dev/null; then
    echo "Podman 未安装"
    exit 1
fi

# 检查 podman-compose 或 podman compose
if command -v podman-compose &> /dev/null; then
    PODMAN_COMPOSE="podman-compose"
elif podman compose version &> /dev/null; then
    PODMAN_COMPOSE="podman compose"
else
    echo "需要 podman-compose 或 podman (v4.1+) 自带 compose"
    exit 1
fi
```

### 2. 兼容性处理

- Podman 默认以 rootless 模式运行
- 使用 `podman compose`（v4.1+）或 `podman-compose`
- 卷挂载路径处理与 Docker 相同
- 端口映射使用相同语法

## 文件结构

```
dev-podman.sh
```

## 配置文件复用

- 直接使用现有的 `docker-compose.yml`
- 使用 `.env` 文件（如存在）或创建默认环境变量
- 无需额外配置文件
