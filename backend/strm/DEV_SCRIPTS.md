# Docker 开发脚本使用指南

本项目提供了 Docker 开发脚本来简化开发环境的构建和启动过程。

## 脚本概览

### dev-docker.sh
**高级 Docker 管理脚本** - 提供完整的开发环境管理功能

**注意**：Windows 用户建议使用 Git Bash 运行此脚本。

**功能特性：**
- 完整的环境初始化（`install`）
- 标准启动模式（`start`）
- 开发模式启动（`start-dev`，支持热重载）
- 镜像构建管理（`build`、`rebuild`）
- 实时日志查看（`logs`、`logs-f`）
- 服务状态检查（`status`）
- 容器交互（`exec`）
- 环境清理（`clean`、`clean-all`）
- 数据备份（`backup`）
- 健康检查（`health`）

**基本用法：**
```bash
# 初始化开发环境
./dev-docker.sh install

# 启动服务（生产模式）
./dev-docker.sh start

# 启动服务（开发模式，支持热重载）
./dev-docker.sh start-dev

# 重新构建并启动
./dev-docker.sh rebuild

# 查看实时日志
./dev-docker.sh logs -f

# 进入容器调试
./dev-docker.sh exec

# 健康检查
./dev-docker.sh health

# 备份数据
./dev-docker.sh backup

# 停止服务
./dev-docker.sh stop

# 清理环境
./dev-docker.sh clean-all
```

## 快速开始

### 首次使用

1. **初始化开发环境**
   ```bash
   ./dev-docker.sh install
   ```

2. **启动应用**
   ```bash
   # 生产模式
   ./dev-docker.sh start

   # 开发模式（热重载）
   ./dev-docker.sh start-dev
   ```

3. **访问应用**
   - 打开浏览器访问：`http://localhost:3111`
   - 应用启动后会自动进行健康检查

### 开发模式特性

`start-dev` 命令提供以下开发特性：

- **热重载支持**：前端和后端代码修改自动重载
- **多端口映射**：
  - `3111:80` - 主应用端口
  - `3000:3000` - 前端开发服务器
  - `8080:8080` - 后端 API
- **调试日志**：启用详细的调试日志
- **源码挂载**：本地源码直接挂载到容器中

### 日常开发

**查看日志：**
```bash
./dev-docker.sh logs -f        # 实时日志
./dev-docker.sh logs           # 最近100行日志
```

**重启服务：**
```bash
./dev-docker.sh restart        # 重启容器
./dev-docker.sh rebuild        # 重新构建并启动
```

**进入容器调试：**
```bash
./dev-docker.sh exec            # 进入 bash shell
./dev-docker.sh exec sh         # 进入 sh shell
```

## 数据目录结构

脚本会自动创建以下目录结构：

```
project/
├── data/                       # 应用数据目录
│   ├── config/                 # 配置文件
│   └── db/                     # 数据库文件
├── logs/                       # 应用日志
├── strm/                       # STRM 文件输出
└── backups/                    # 数据备份
```

## 环境配置

脚本会自动处理环境配置：

1. **检查 .env 文件** - 如果不存在，从 `.env.docker.example` 复制
2. **创建必要目录** - 自动创建数据存储目录
3. **设置权限** - 为 Linux/macOS 设置适当的文件权限

## 文件处理器链

系统在处理视频文件时采用责任链模式，包含以下处理器：

| Order | 处理器 | 功能 |
|-------|--------|------|
| 10 | FileDiscoveryHandler | 文件发现 |
| 20 | FileFilterHandler | 文件过滤 |
| 30 | StrmGenerationHandler | STRM 文件生成 |
| 40 | NfoDownloadHandler | NFO 文件下载 |
| 41 | ImageDownloadHandler | 图片文件下载 |
| 42 | SubtitleCopyHandler | 字幕文件复制 |
| 50 | MediaScrapingHandler | 媒体刮削 |
| 60 | OrphanCleanupHandler | 孤立文件清理 |

**字幕文件支持格式：**
- `.srt` - SubRip 字幕
- `.ass` - Advanced Substation Alpha
- `.vtt` - WebVTT 字幕
- `.ssa` - SubStation Alpha
- `.sub` - SubViewer
- `.idx` - VobSub 索引

**图片下载优先级：**
1. 本地文件检查
2. OpenList 下载
3. TMDB 刮削

## 故障排除

### 常见问题

**1. Docker 未运行**
```
Docker 未运行，请启动 Docker
```
**解决方案：** 启动 Docker Desktop

**2. 端口被占用**
```
应用启动超时，请检查日志
```
**解决方案：**
- 检查端口 3111 是否被占用
- 使用 `./dev-docker.sh stop` 停止现有容器

**3. 构建失败**
```
镜像构建失败
```
**解决方案：**
- 检查网络连接
- 使用 `./dev-docker.sh rebuild --no-cache` 重新构建

### 调试命令

**查看详细日志：**
```bash
./dev-docker.sh logs -f
```

**检查容器状态：**
```bash
./dev-docker.sh status
```

**手动进入容器：**
```bash
docker exec -it ostrm bash
```

## 高级功能

### 备份数据
```bash
./dev-docker.sh backup
# 备份文件保存在 backups/ 目录
```

### 深度清理
```bash
./dev-docker.sh clean-all
# 删除容器、镜像和缓存
```

### 重启服务
```bash
./dev-docker.sh restart
```

## 脚本比较

| 使用场景 | 推荐命令 |
|---------|----------|
| 首次初始化 | `./dev-docker.sh install` |
| 日常开发 | `./dev-docker.sh start-dev` |
| 生产部署 | `./dev-docker.sh start` |
| 问题排查 | `./dev-docker.sh logs -f` |
| 数据备份 | `./dev-docker.sh backup` |
| 环境清理 | `./dev-docker.sh clean-all` |

## 贡献

如果您发现脚本的问题或有改进建议，请：
1. 提交 Issue 描述问题
2. 提交 Pull Request 贡献代码
3. 在讨论中分享使用经验

---

**注意：** 这些脚本专为本项目设计，确保在项目根目录下运行。

**Windows 用户**：建议使用 Git Bash 运行 `./dev-docker.sh`。
