# Proposal: 添加 Podman 启动脚本

## 摘要

创建 `dev-podman.sh` 脚本，用于在使用 Podman 而非 Docker 的环境中启动 Ostrm 项目。

## 问题

当前项目的 `dev-docker.sh` 脚本仅支持 Docker，但部分开发者的机器上安装的是 Podman（Docker 的替代品，兼容 OCI 标准）。需要提供一个使用 Podman 的启动脚本，使这些用户也能方便地启动项目。

## 目标

1. 创建 `dev-podman.sh` 脚本，功能与 `dev-docker.sh` 等效
2. 支持 `podman` 和 `podman-compose` 命令
3. 复用现有的 `docker-compose.yml` 配置文件
4. 提供与原脚本相同的用户体验

## 影响范围

- 新增文件：`dev-podman.sh`
- 无需修改现有代码
- 向后兼容，不影响现有 Docker 用户
