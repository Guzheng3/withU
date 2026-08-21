# Tasks: 实现计划

## Task 1: 创建 dev-podman.sh 脚本

**描述**: 创建 Podman 启动脚本，功能与 dev-docker.sh 等效

**文件**: `dev-podman.sh`

**实现步骤**:

1. 定义常量（项目名、容器名、默认端口、颜色）
2. 实现辅助函数：
   - `print_success()` - 成功提示
   - `print_error()` - 错误提示
3. 实现核心功能：
   - `check_dependencies()` - 检查 Podman 和 podman-compose/podman compose
   - `setup_environment()` - 创建必要目录
   - `clean_and_deploy()` - 清理已有容器和镜像，构建并启动
4. 实现主函数，只支持 `start` 命令

**验收标准**:
- [x] 脚本可执行 (`chmod +x`)
- [x] 复用 `docker-compose.yml` 配置
- [x] `podman-compose` 或 `podman compose` 命令可用时脚本正常工作
