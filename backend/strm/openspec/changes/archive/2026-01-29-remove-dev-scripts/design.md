# 设计方案：移除冗余开发脚本

## 设计目标

简化项目结构，移除冗余的开发启动脚本，统一使用 `dev-docker.sh` 作为唯一的开发环境脚本。

## 设计原则

1. **最小化变更**：仅删除冗余文件，不修改任何功能代码
2. **保持兼容**：保留 `dev-docker.sh` 的完整功能
3. **文档同步**：更新相关技术文档

## 设计详情

### 删除的文件

| 文件 | 大小 | 说明 |
|------|------|------|
| `dev-start.sh` | 5,481 bytes | 功能被 dev-docker.sh 完全覆盖 |
| `dev-start.bat` | 4,609 bytes | 功能被 dev-docker.sh 完全覆盖 |
| `dev-docker.bat` | 8,798 bytes | Git Bash 可运行 dev-docker.sh |

### 保留的文件

| 文件 | 大小 | 说明 |
|------|------|------|
| `dev-docker.sh` | 10,780 bytes | 完整功能的开发脚本 |

### dev-docker.sh 功能列表

- `install` - 初始化开发环境
- `start` - 启动生产模式
- `start-dev` - 启动开发模式（热重载）
- `stop` - 停止容器
- `restart` - 重启容器
- `build` - 构建镜像
- `rebuild` - 强制重建镜像
- `logs` - 查看日志
- `logs-f` - 实时跟踪日志
- `status` - 检查状态
- `exec` - 进入容器Shell
- `clean` - 清理容器
- `clean-all` - 清理所有（容器、镜像、卷）
- `backup` - 备份数据
- `health` - 健康检查

## 技术决策

### 为什么删除 dev-docker.bat

1. Git Bash 可以直接运行 `dev-docker.sh`
2. 减少 Windows 脚本的维护成本
3. 避免功能不一致的问题

### 为什么删除 dev-start.sh

1. 功能与 `dev-docker.sh start` 完全重叠
2. 用户容易混淆使用哪个脚本
3. 简化用户决策

## 影响分析

### 用户影响

- **正面**：减少混淆，只需学习一个脚本
- **中性**：Windows 用户需要安装 Git Bash

### 开发影响

- **无影响**：GitHub Actions 不使用这些脚本

## 测试计划

无需测试，仅删除文件。
