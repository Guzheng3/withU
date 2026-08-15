# CLAUDE.md

本文档为 Claude Code (claude.ai/code) 提供代码仓库的操作指南。

## 项目概述

Ostrm 是一个现代化的全栈应用程序，用于将文件列表转换为 STRM 流媒体文件（原 OpenList to Stream 项目）。项目采用容器化架构，支持多平台部署，具备完整的用户认证、任务调度和媒体刮削功能。

- **前端**: Nuxt.js 3.13.0 + Vue 3.4.0 + Tailwind CSS 3.4.15
- **后端**: Spring Boot 3.3.9 + Gradle 8.12.1 + Java 21
- **数据库**: SQLite 3.47.1.0 + Flyway 11.4.0 数据库迁移
- **DevOps**: Docker + Docker Compose + GitHub Actions CI/CD
- **架构**: 多阶段容器化部署，支持热重载

## 项目架构

> **架构文档说明**:
> - 本文档 (CLAUDE.md) 提供项目的**总体概览和开发指南**
> - 详细的前端架构说明请参考 [`frontend/ARCHITECTURE.md`](file:///home/hienao/Code/github/ostrm/frontend/ARCHITECTURE.md)
> - 详细的后端架构说明请参考 [`backend/ARCHITECTURE.md`](file:///home/hienao/Code/github/ostrm/backend/ARCHITECTURE.md)

### 总体结构
```
frontend/                  # Nuxt.js 3.13.0 前端应用
├── app/                   # 源码目录（Feature-Sliced Design 改良版）
│   ├── core/              # 核心层：技术基础设施
│   │   ├── api/           # HTTP 客户端封装
│   │   │   ├── client.ts  # 基础 ofetch 配置
│   │   │   ├── interceptors/  # 请求/响应拦截器
│   │   │   └── types/     # API 相关类型定义
│   │   ├── ui/            # 基础 UI 组件库
│   │   ├── utils/         # 通用工具函数
│   │   │   ├── validation/   # 校验工具
│   │   │   ├── formatters/   # 格式化工具
│   │   │   └── helpers/   # 辅助函数
│   │   ├── constants/     # 全局常量
│   │   └── types/         # 全局 TypeScript 类型定义
│   │
│   ├── modules/           # 业务模块层（按功能领域划分）
│   │   ├── auth/          # 认证授权模块
│   │   │   ├── components/
│   │   │   ├── composables/   # useAuth, usePermission
│   │   │   ├── pages/     # login, register, change-password
│   │   │   ├── services/  # auth.service.ts
│   │   │   └── stores/    # auth.store.ts
│   │   ├── dashboard/     # 仪表盘模块
│   │   │   ├── composables/
│   │   │   ├── pages/     # index.vue
│   │   │   └── stores/
│   │   ├── settings/      # 设置模块
│   │   │   ├── composables/
│   │   │   ├── pages/     # index.vue
│   │   │   └── stores/    # version.store.ts
│   │   ├── task/          # 任务管理模块
│   │   │   ├── composables/
│   │   │   └── pages/     # [id].vue
│   │   ├── logs/          # 日志模块
│   │   │   └── composables/
│   │   └── shared/        # 共享模块
│   │       └── components/    # AppHeader.vue
│   │
│   ├── layouts/           # 布局组件
│   ├── middleware/        # 路由中间件
│   └── plugins/           # Nuxt 插件
│
├── assets/                # 静态资源（CSS、图片等）
├── public/                # 公共静态文件
├── nuxt.config.ts         # Nuxt 配置
├── tsconfig.json          # TypeScript 配置
└── package.json           # 依赖配置
├── backend/           # Spring Boot 3.3.9 后端应用
│   └── src/main/java/com/hienao/openlist2strm/
│       ├── controller/  # REST API 控制器（认证、配置、任务）
│       ├── service/     # 业务逻辑层
│       ├── handler/     # 文件处理器链（责任链模式）
│       │   ├── FileProcessorHandler.java     # 处理器接口
│       │   ├── FileProcessorChain.java       # 链执行器
│       │   ├── FileDiscoveryHandler.java     # Order: 10 - 文件发现
│       │   ├── FileFilterHandler.java        # Order: 20 - 文件过滤
│       │   ├── StrmGenerationHandler.java    # Order: 30 - STRM生成
│       │   ├── NfoDownloadHandler.java       # Order: 40 - NFO下载（优先级：本地 > OpenList > 刮削）
│       │   ├── ImageDownloadHandler.java     # Order: 41 - 图片下载
│       │   ├── SubtitleCopyHandler.java      # Order: 42 - 字幕复制
│       │   ├── MediaScrapingHandler.java     # Order: 50 - 媒体刮削
│       │   ├── OrphanCleanupHandler.java     # Order: 60 - 孤立文件清理
│       │   ├── context/                      # 共享上下文
│       │   └── FileProcessingContext.java    # 处理上下文
│       ├── mapper/      # MyBatis 数据访问层
│       ├── entity/      # 数据库实体
│       ├── job/         # Quartz 定时任务
│       ├── config/      # Spring 配置类
│       └── security/    # JWT 认证配置
├── .github/           # GitHub Actions CI/CD 工作流
├── docker-compose.yml # 容器编排
└── dev-docker.sh      # 开发环境脚本
```

### 核心组件

**前端 (Nuxt.js 3.18.0)**:
- **架构模式**: 单页应用 (SPA)，客户端渲染 (CSR)
- **架构设计**: Feature-Sliced Design 改良版，目录结构清晰分离
  - `app/core/` - 技术基础设施层
  - `app/modules/` - 业务功能模块层
- **核心技术**: Vue 3.4.0 Composition API + `<script setup>` + TypeScript 支持
- **状态管理**: Pinia 2.1.7 (2 个状态仓库: `auth.store.ts`, `version.store.ts`)
- **路由守卫**: 3 个中间件 (`auth.ts`, `guest.ts`, `docker-port.global.ts`)
- **页面组件**: 7 个页面（自动路由），使用 NuxtLayout 布局系统
- **API 封装**: 基于 ofetch 的统一 HTTP 客户端，自动处理 Token 和错误
- **工具模块**: Zod 运行时校验 + 工具函数集合
- **插件系统**: Pinia 插件初始化
- **样式系统**: Tailwind CSS 3.4.15 毛玻璃设计 + @tailwindcss/forms 0.5.9
- **性能优化**: vue-virtual-scroller 2.0.0-beta.8 虚拟滚动
- **认证机制**: JWT 令牌自动注入和刷新，Cookie 存储

**后端 (Spring Boot 3.3.9)**:
- **架构模式**: 分层架构 (Controller → Service → Mapper → Entity)
- **核心框架**: Java 21 + Gradle (Kotlin DSL) + Spring Boot 3.3.9
- **表现层**: 7 个 REST API 控制器 + WebSocket 实时通信
- **业务层**: 20 个服务类 + 16 个文件处理器（责任链模式）
- **数据层**: MyBatis 3.0.4 (2 个 Mapper) + 2 个实体类
- **DTO 层**: 18 个数据传输对象（按功能分组: sign/task/tmdb 等）
- **配置管理**: 18 个配置类（含独立 security 子目录）
- **任务调度**: Spring Quartz (5 个 Job，RAM 存储模式)
- **数据库**: SQLite 3.47.1.0 + Flyway 11.4.0 迁移管理
- **安全认证**: Spring Security + JWT (java-jwt 4.4.0)
- **API 文档**: SpringDoc OpenAPI 2.6.0 + Swagger UI
- **缓存系统**: Caffeine 3.2.0 本地缓存
- **代码质量**: PMD 7.9.0 + Spotless 7.0.2 + JaCoCo 0.8.12

**核心功能**:
- OpenList 配置管理（CRUD 操作）
- STRM 文件生成任务（批量处理 + 进度跟踪）
- 定时任务执行（CRON 表达式 + 错误处理）
- AI 媒体刮削（可选，支持可配置 Provider）
- URL 编码控制和 Base URL 替换
- 多平台容器部署 + 健康检查

### 数据库

- **SQLite 3.47.1.0**: 主数据库，文件存储 + WAL 模式
- **数据表**: `openlist_config`, `task_config`, `user_info`, 迁移跟踪
- **迁移文件**: `backend/src/main/resources/db/migration/` 目录
- **路径**: `/maindata/db/openlist2strm.db`（容器标准化路径）
- **连接池**: HikariCP + 优化的 SQLite 配置
- **事务管理**: Spring Boot 事务管理 + 回滚支持

### API 结构

**主要端点**:
- `/api/auth/*` - 认证服务（登录、注册、登出、Token 刷新）- `SignController`
- `/api/openlist-config` - OpenList 服务器配置 CRUD - `OpenlistConfigController`
- `/api/task-config` - STRM 任务管理（调度、执行）- `TaskConfigController`
- `/api/system-config` - 系统配置和设置管理 - `SystemConfigController`
- `/api/logs` - 日志查询和管理 - `LogController`
- `/api/data-report` - 数据报表和统计 - `DataReportController`
- `/api/version` - 应用版本信息和更新检查 - `VersionController`
- `/ws/*` - WebSocket 实时通信端点

**API 特性**:
- OpenAPI 3.0 文档 + Swagger UI
- 全局异常处理 + 结构化错误响应
- Bean Validation 请求验证
- 速率限制 + CORS 安全配置
- 结构化 JSON 日志输出

## 开发环境搭建

### 快速开始
```bash
# 克隆仓库
git clone https://github.com/Guzheng3/ostrm.git
cd ostrm

# 初始化开发环境
./dev-docker.sh install

# 启动开发模式（热重载）
./dev-docker.sh start-dev

# 健康检查
./dev-docker.sh health
```

### 开发模式
```bash
# 生产模式（标准构建）
./dev-docker.sh start

# 开发模式（热重载）
./dev-docker.sh start-dev

# 实时日志
./dev-docker.sh logs-f

# 进入容器 Shell 调试
./dev-docker.sh exec
```

### 构建和部署
```bash
# 构建生产镜像
./dev-docker.sh build

# 强制重建（无缓存）
./dev-docker.sh rebuild --no-cache

# 清理所有容器、镜像、卷
./dev-docker.sh clean-all

# 从头完全重建
./dev-docker-rebuild.sh  # Linux/macOS
dev-docker-rebuild.bat    # Windows
```

### 开发环境 URL
- **前端开发服务器**: http://localhost:3000
- **后端 API 服务器**: http://localhost:8080
- **主应用**: http://localhost:3111
- **API 文档**: http://localhost:3111/swagger-ui.html
- **健康检查**: http://localhost:3111/actuator/health

## 开发规范

### 前端开发

**架构规范**:
- 基于 Nuxt 3.18.0 + Vue 3.4.0，采用 SPA 模式（`ssr: false`）
- 源码目录 `app/`，遵循 Feature-Sliced Design 改良版架构
- 严格使用 Composition API + `<script setup>` 语法 + TypeScript 支持
- 使用 Zod 进行运行时数据校验

**目录结构**:
- `app/core/` - 核心层：API 封装、UI 组件、工具函数、常量、类型定义
- `app/modules/` - 业务模块层：auth、dashboard、settings、task、logs、shared
- `app/layouts/` - 布局组件
- `app/middleware/` - 路由中间件
- `app/plugins/` - Nuxt 插件

**组件命名规范**:
- 组件文件: PascalCase，模块前缀（如 `AuthLoginForm.vue`、`UserProfileCard.vue`）
- 组合式函数: camelCase，use 前缀（如 `useAuth()`、`useUserList()`）
- 服务文件: camelCase，Service 后缀（如 `user.service.ts`、`auth.service.ts`）
- Store 文件: camelCase，.store.ts 后缀（如 `auth.store.ts`）

**API 服务层**:
- 服务层使用纯函数式设计，不依赖 Vue 上下文
- 使用 Zod Schema 进行运行时校验
- 组合式函数包装 Service，添加 UI 层交互
- API 客户端配置在 `app/core/api/client.ts`

**状态管理**:
- 使用 Pinia 2.1.7 进行全局状态管理
- 只读数据使用 `shallowRef` 优化性能
- 每个 Store 必须实现 `reset()` 方法
- 状态仓库放在 `modules/*/stores/` 目录

**错误处理**:
- 定义 `ApiError` 异常类，统一错误格式
- 401 Token 过期自动刷新（互斥锁模式）
- 422 验证错误透传给组件处理

**性能优化**:
- 只读数据使用 `shallowRef` 而非 `ref`
- 大型列表使用 `vue-virtual-scroller` 虚拟滚动
- 组件懒加载使用 `defineAsyncComponent`
- 大型列表使用 `v-memo` 优化渲染

**路由和中间件**:
- 页面组件放在 `modules/*/pages/` 目录
- 使用 `definePageMeta()` 配置中间件
- 受保护页面使用 `auth` 中间件
- 登录/注册页使用 `guest` 中间件

**TypeScript 严格模式**:
- 启用 `strict: true` 及相关严格检查规则
- 禁用 `any` 类型，使用具体类型或 `unknown`
- 使用 `noUncheckedIndexedAccess` 增强数组访问安全

**依赖管理**:
- 新增 `zod` 用于运行时数据校验
- 通过 `nuxt.config.ts` 配置自动导入路径

### 后端开发

**架构规范**:
- 严格遵循 **分层架构**: Controller → Service → Mapper → Entity
- 使用 Spring Boot 3.3.9 + Java 21，Gradle Kotlin DSL 构建
- 所有 REST API 使用 `@RestController` 注解，遵循 RESTful 规范

**表现层 (Controller)**:
- 7 个控制器放在 `controller/` 目录
- 使用 `@RestController` + `@RequestMapping` 定义路由
- 添加 OpenAPI 3.0 注解（`@Operation`, `@ApiResponse` 等）用于文档生成
- 使用 `@Valid` 进行请求参数验证
- 控制器只负责请求/响应处理，业务逻辑委托给 Service 层

**业务层 (Service)**:
- 20 个服务类放在 `service/` 目录，使用 `@Service` 注解
- 核心服务包括:
  - `TaskExecutionService` - 任务执行核心逻辑
  - `StrmFileService` - STRM 文件生成
  - `MediaScrapingService` - 媒体刮削
  - `OpenlistApiService` - OpenList API 集成
  - `TmdbApiService` - TMDB API 集成
- 使用 `@Transactional` 管理事务边界
- 业务逻辑与数据访问分离

**责任链模式 (Handler)**:
- 16 个文件处理器放在 `handler/` 目录
- 所有处理器实现 `FileProcessorHandler` 接口
- 使用 `@Order` 注解定义执行顺序（10, 20, 30...）
- 通过 `FileProcessingContext` 传递上下文和配置
- 处理器链执行器: `FileProcessorChain`
- 配置读取示例:
  ```java
  Object value = context.getAttribute("keepSubtitleFiles");
  boolean enabled = Boolean.TRUE.equals(value);
  ```

**数据层 (Mapper & Entity)**:
- 使用 MyBatis 3.0.4 进行 ORM 映射
- 2 个 Mapper 接口放在 `mapper/` 目录，使用 `@Mapper` 注解
- XML 映射文件放在 `resources/mapper/` 目录
- 2 个实体类放在 `entity/` 目录，对应数据库表

**数据传输对象 (DTO)**:
- 18 个 DTO 放在 `dto/` 目录，按功能分组:
  - `dto/sign/` - 认证相关 DTO
  - `dto/task/` - 任务相关 DTO
  - `dto/tmdb/` - TMDB API DTO
  - 等等
- 使用 Bean Validation 注解（`@NotNull`, `@NotBlank`, `@Valid` 等）

**配置管理 (Config)**:
- 18 个配置类放在 `config/` 目录，使用 `@Configuration` 注解
- 安全配置独立放在 `config/security/` 子目录:
  - `WebSecurityConfig` - Spring Security 配置
  - `Jwt` - JWT 工具类
  - `JwtAuthenticationFilter` - JWT 过滤器
- 其他重要配置:
  - `QuartzConfig` - Quartz 调度配置
  - `CacheConfig` - Caffeine 缓存配置
  - `MyBatisConfig` - MyBatis 配置

**定时任务 (Job)**:
- 5 个 Quartz Job 放在 `job/` 目录
- 实现 `Job` 接口，使用 `@DisallowConcurrentExecution` 防并发
- 主要任务:
  - `TaskConfigJob` - 主任务执行
  - `VersionCheckJob` - 版本检查
  - `LogCleanupJob` - 日志清理
  - `DataBackupJob` - 数据备份
  - `EmailJob` - 邮件发送

**异常处理**:
- 使用 `@ControllerAdvice` 实现全局异常处理
- 自定义异常类放在 `exception/` 目录
- 返回结构化错误响应，统一格式

**安全实践**:
- 使用 Spring Security + JWT 进行认证授权
- 密码使用 BCrypt 加密
- 配置 CORS 安全策略
- 敏感信息通过环境变量配置（如 `JWT_SECRET`）

**代码质量**:
- 使用 Spotless 7.0.2 自动格式化代码（Google Java Format）
- 使用 PMD 7.9.0 进行静态代码分析
- 使用 JaCoCo 0.8.12 生成测试覆盖率报告
- 运行 `./gradlew spotlessApply` 格式化代码
- 运行 `./gradlew pmdMain` 检查代码质量

### 数据库开发
1. 使用 Flyway 规范创建迁移文件 `V{version}__{description}.sql`
2. 放置到 `backend/src/main/resources/db/migration/` 目录
3. 重启前使用 `./gradlew flywayMigrate` 测试迁移
4. 使用适当的索引和外键约束
5. 遵循 SQLite 性能和并发最佳实践

### 测试策略

**重要提示**：除非用户明确要求，否则开发完成后不要运行自动化测试。需要测试时，仅使用 Docker 容器构建脚本进行验证。

**需要测试时的操作**:
```bash
# 使用 Docker 构建脚本进行容器测试
./dev-docker.sh build

# 验证容器启动成功
./dev-docker.sh start

# 健康检查验证部署
./dev-docker.sh health

# 测试后清理
./dev-docker.sh clean-all
```

**可用的测试工具**（仅在明确要求时使用）:
- **后端**: JUnit 5 + Spring Boot Test + TestContainers
- **前端**: Vitest + Vue Test Utils 组件测试
- **代码质量**: PMD + Spotless（后端），ESLint + Prettier（前端）
- **覆盖率**: `./gradlew jacocoTestReport`（后端），`npm run test:coverage`（前端）
- **安全**: OWASP 依赖检查和安全扫描
- **性能**: 负载测试和性能分析

**测试理念**:
- 优先使用基于容器的集成测试，而非单元测试
- 使用 Docker 构建过程作为主要验证方法
- 关注功能验证，而非代码覆盖率指标
- 通过浏览器交互进行手动测试

### Git 工作流
- 使用特性分支工作流，命名需具有描述性
- 使用语义化提交信息（`feat:`, `fix:`, `docs:` 等）
- 所有代码变更创建 Pull Request，附带适当描述
- 确保所有质量检查通过后再合并
- 使用语义化版本（`v*.*.*` 格式）发布

### 环境配置
- 使用 `.env.docker.example` 作为本地开发模板
- 不要提交敏感配置到仓库
- 为开发（`dev`）、测试（`test`）、生产（`prod`）使用不同配置
- 为每个环境配置适当的日志级别
- 所有外部服务连接使用环境变量

## CI/CD 和容器部署

### 自动化构建和发布

**GitHub Actions 工作流**:
- **触发条件**: 标签推送（`v*.*.*` 发布版，`beta-v*.*.*` 测试版）
- **多平台**: 支持 linux/amd64 和 linux/arm64 架构
- **安全**: 启用 Docker 溯源和 SBOM 生成
- **仓库**: 自动推送到 Docker Hub 仓库

**发布流程**:
1. 创建版本标签: `git tag v1.2.0`
2. 推送标签: `git push origin v1.2.0`
3. 自动触发 GitHub Actions 工作流
4. 构建并推送多平台 Docker 镜像
5. 自动创建 GitHub Release 和资源
6. 更新最新更改的文档

```bash
# 使用最新镜像快速启动
docker-compose up -d

# 开发模式（热重载）
./dev-docker.sh start-dev

# 清理重建（移除所有容器、镜像、卷）
./dev-docker-rebuild.sh        # Linux/macOS
dev-docker-rebuild.bat         # Windows

# 手动重建命令
docker-compose down --rmi all --volumes
docker-compose build
docker-compose up -d

# 访问应用
http://localhost:3111
```

### 多平台架构支持

**支持的架构**:
- **linux/amd64**: 标准 x86_64 服务器和桌面
- **linux/arm64**: ARM64 服务器（AWS Graviton, Raspberry Pi 4+）

**Docker Buildx 集成**:
- 使用 QEMU 模拟进行原生多平台构建
- 单命令同时构建所有架构
- 优化层缓存以加快构建速度
- 自动创建多架构清单

**拉取镜像**:
```bash
# 拉取特定架构镜像
docker pull --platform linux/amd64 hienao6/ostrm:latest
docker pull --platform linux/arm64 hienao6/ostrm:latest

# 拉取多架构镜像（Docker 自动选择合适平台）
docker pull hienao6/ostrm:latest
```

自定义路径配置使用环境变量：

1. 复制环境配置模板:
```bash
cp .env.docker.example .env
```

2. 编辑 `.env` 文件自定义路径:
```bash
# Docker 卷的主机路径
LOG_PATH_HOST=./logs           # 日志文件主机路径
CONFIG_PATH_HOST=./data/config # 配置文件主机路径
DB_PATH_HOST=./data/db         # 数据库文件主机路径
STRM_PATH_HOST=./strm          # STRM 文件主机路径
```

**卷映射**:
- `${LOG_PATH_HOST}:/maindata/log` - 应用日志
- `${CONFIG_PATH_HOST}:/maindata/config` - 配置文件
- `${DB_PATH_HOST}:/maindata/db` - 数据库文件
- `${STRM_PATH_HOST}:/app/backend/strm` - 生成的 STRM 文件输出

**标准化路径结构**:
```
容器内部路径                    主机路径（默认）
/maindata/log/                 → ./logs/
/maindata/config/              → ./data/config/
/maindata/db/                  → ./data/db/
/app/backend/strm/             → ./strm/
```

### Docker 重建脚本 (`dev-docker-rebuild.sh`)
- 完全移除现有容器、网络、镜像和卷
- 配置 npm 仓库为中国镜像以提高连接性
- 使用 Docker Buildx 从头重建所有镜像
- 在分离模式下启动容器
- 自动应用标准化路径配置

### Docker 调试脚本
用于排查容器问题:
```bash
# 综合容器调试和设置（Linux/macOS/Git Bash）
./docker-debug.sh

# 功能特性:
# - 检查 Docker 守护进程状态和 Docker Buildx 配置
# - 从 .env.docker.example 创建/验证 .env 文件
# - 使用标准化结构创建必要的数据目录
# - 验证 Flyway 迁移文件和数据库模式
# - 提供数据库清理和重置选项
# - 使用 --no-cache 构建镜像以进行干净构建
# - 启动容器时使用正确的卷挂载和健康检查
# - 自动应用标准化路径配置
```

**跨平台 Docker 脚本**: 所有 Docker 脚本在 Windows 下有对应的 `.bat` 文件，但开发环境推荐使用 Git Bash 运行 `dev-docker.sh`。

### 直接 Docker 命令
```bash
# 构建多平台镜像
docker buildx build -t ostrm:latest --platform linux/amd64,linux/arm64 .

# 运行容器（单平台）
docker run -d \
  --name ostrm \
  -p 3111:80 \
  -v ./data/config:/maindata/config \
  -v ./data/db:/maindata/db \
  -v ./logs:/maindata/log \
  -v ./strm:/app/backend/strm \
  ostrm:latest
```

### 路径标准化优势

1. **一致性**: 所有组件使用标准化内部路径
2. **向后兼容**: 现有部署无需更改即可继续工作
3. **灵活性**: 环境变量允许自定义主机路径配置
4. **可维护性**: 集中式路径管理减少配置错误
5. **跨平台**: 在不同主机操作系统上一致工作
6. **容器编排**: 优化 Docker Compose 和 Kubernetes 部署

## 最近的架构更新

### CI/CD 优化（最近 5 次提交）
- **简化 Runner 配置**: 从复杂的矩阵策略改为标准 ubuntu-latest Runner
- **原生 Docker Buildx**: 切换到 Docker Buildx 原生多平台构建以获得更好性能
- **Gradle 版本管理**: 降级到 Gradle 8.12.1 以提高稳定性并修复 PMD 配置问题
- **构建性能**: 通过优化缓存策略提高构建时间
- **基础镜像简化**: 移除 noble 变体，使用标准 Ubuntu 22.04 保持一致性

### 开发体验改进
- **增强开发脚本**: 改进了 `dev-docker.sh`，提供更好的健康检查和错误处理
- **热重载稳定性**: 修复了容器文件同步问题，确保可靠开发
- **代码质量工具**: 集成了 PMD、Spotless 和全面的测试框架
- **多平台开发**: 完全支持 ARM64 和 AMD64 开发环境

## 重要说明

- **测试策略**: 除非用户明确要求，优先使用 Docker 容器构建而非自动化单元测试
- **Quartz 配置**: 由于 SQLite 兼容性，使用 RAM 存储（RAMJobStore）而非数据库持久化
- **认证**: JWT 令牌配合自动刷新机制和可配置过期时间
- **CORS**: 为开发和生产环境配置了适当安全头的 CORS
- **文件生成**: STRM 文件生成在 `/app/backend/strm` 目录，支持批量处理
- **AI 集成**: 可选的 AI 刮削功能，支持可配置的媒体元数据 Provider
- **路径管理**: 标准化路径确保跨部署环境的一致行为和向后兼容
- **多平台支持**: 原生支持 x86_64 和 ARM64 架构，自动选择镜像
- **性能优化**: 多级缓存、异步处理和数据库连接池优化性能
- **安全**: 全面的安全措施，包括依赖扫描、输入验证和安全配置管理

## 文件处理器链

系统采用责任链模式处理视频文件相关资源：

| Order | 处理器 | 功能 |
|-------|--------|------|
| 10 | FileDiscoveryHandler | 文件发现 |
| 20 | FileFilterHandler | 文件过滤 |
| 30 | StrmGenerationHandler | STRM 文件生成 |
| 40 | NfoDownloadHandler | NFO 文件下载 |
| 41 | ImageDownloadHandler | 图片文件下载（海报、背景图、缩略图） |
| 42 | SubtitleCopyHandler | 字幕文件复制 |
| 50 | MediaScrapingHandler | 媒体刮削 |
| 60 | OrphanCleanupHandler | 孤立文件清理 |

### 配置传递机制

处理器通过 `FileProcessingContext` 的属性传递配置：

```java
// 读取配置
private boolean isKeepSubtitleEnabled(FileProcessingContext context) {
    Object value = context.getAttribute("keepSubtitleFiles");
    return Boolean.TRUE.equals(value);
}

private boolean isImageScrapingEnabled(FileProcessingContext context) {
    Object value = context.getAttribute("useExistingScrapingInfo");
    return Boolean.TRUE.equals(value);
}
```

### URL 编码处理

系统使用智能 URL 编码处理中文路径和特殊字符：

```java
// UrlEncoder.java - 智能 URL 编码
public static String encodeUrlSmart(String url) {
    // 仅对路径部分编码，保留协议和主机
    // 处理中文、空格等特殊字符
}
```
