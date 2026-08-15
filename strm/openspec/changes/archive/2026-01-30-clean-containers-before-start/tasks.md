## 1. 简化 dev-docker.sh 脚本

- [ ] 1.1 移除所有辅助命令（stop、restart、logs、exec、clean、backup 等）
- [ ] 1.2 只保留 start 命令
- [ ] 1.3 移除开发模式相关函数（create_dev_compose_file、start_services 的 dev_mode 参数）

## 2. 实现启动前清理逻辑

- [ ] 2.1 在 start 命令中先执行 `docker-compose down --rmi all --volumes` 清理已有容器和镜像
- [ ] 2.2 执行 `docker-compose build --no-cache` 强制重新构建镜像
- [ ] 2.3 执行 `docker-compose up -d` 启动服务
