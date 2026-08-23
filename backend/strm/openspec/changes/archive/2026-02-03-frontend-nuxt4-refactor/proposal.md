## Why

当前前端项目使用传统的 Nuxt 3 目录结构，所有文件都放在根目录（pages、components、stores、utils 等），缺乏清晰的模块划分和架构规范。随着项目复杂度增加，代码难以维护和扩展。

## What Changes

- 迁移到 Feature-Sliced Design 改良版目录结构
- 创建 `app/` 目录，将源码迁移到其下
- 新增 `core/` 核心层（API 封装、UI 组件、工具函数、常量、类型）
- 新增 `modules/` 业务模块层（auth、dashboard、settings、task 等）
- 更新 `nuxt.config.ts` 配置，适配新的目录结构
- 更新 `tsconfig.json` 启用严格类型检查
- 添加 Zod 运行时校验依赖
- 更新 CLAUDE.md 补充前端架构和代码规范文档

## Capabilities

### New Capabilities
- `frontend-core-layer`: 核心层基础设施（API 客户端、拦截器、类型定义、工具函数）
- `frontend-module-structure`: 业务模块目录结构和组件规范
- `frontend-code-standards`: 代码规范（命名、API 服务、状态管理、错误处理）

### Modified Capabilities
- `frontend-architecture`: 更新现有前端架构文档，整合 Nuxt 4 SPA 架构规范

## Impact

- 前端目录结构重大调整
- 新增 2 个核心模块（core、modules）
- 迁移现有页面、组件、 stores、utils 到新结构
- 更新 CLAUDE.md
