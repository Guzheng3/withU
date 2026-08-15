#!/bin/bash

# Ostrm Docker 开发脚本（简化版）
# 启动前自动清理并重新构建镜像

set -e

# 配置变量
PROJECT_NAME="ostrm"
CONTAINER_NAME="app"
DEFAULT_PORT="3111"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# 检查依赖
check_dependencies() {
    # 检查Docker
    if ! command -v docker &> /dev/null; then
        print_error "Docker未安装或不在PATH中"
        exit 1
    fi

    # 检查Docker daemon
    if ! docker info &> /dev/null; then
        print_error "Docker daemon未运行，请启动Docker"
        exit 1
    fi

    # 检查docker-compose
    if command -v docker-compose &> /dev/null; then
        DOCKER_COMPOSE="docker-compose"
    elif docker compose version &> /dev/null; then
        DOCKER_COMPOSE="docker compose"
    else
        print_error "docker-compose未安装"
        exit 1
    fi
}

# 设置环境
setup_environment() {
    # 创建必要的目录
    mkdir -p data/config data/db logs strm data/tmp 2>/dev/null || true
}

# 清理并重新构建
clean_and_deploy() {
    echo "开始清理和部署..."

    # 停止并删除容器、镜像和卷
    echo "清理已有容器和镜像..."
    $DOCKER_COMPOSE down --rmi all --volumes 2>/dev/null || true

    # 构建镜像（不使用缓存）
    echo "构建镜像..."
    $DOCKER_COMPOSE build --no-cache

    # 启动服务
    echo "启动服务..."
    $DOCKER_COMPOSE up -d

    print_success "部署完成！"
    echo "访问地址: http://localhost:${DEFAULT_PORT}"
}

# 主函数
main() {
    local command=${1:-start}

    case $command in
        start)
            check_dependencies
            setup_environment
            clean_and_deploy
            ;;
        *)
            print_error "未知命令: $command"
            echo "用法: $0 start"
            exit 1
            ;;
    esac
}

main "$@"
