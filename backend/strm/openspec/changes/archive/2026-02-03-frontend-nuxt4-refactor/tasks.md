## 1. 创建核心层 (core)

- [ ] 1.1 创建 `app/core/api/client.ts` - 基础 ofetch 配置
- [ ] 1.2 创建 `app/core/api/interceptors/request.ts` - 请求拦截器
- [ ] 1.3 创建 `app/core/api/interceptors/error.ts` - 错误拦截器 + ApiError 类
- [ ] 1.4 创建 `app/core/api/types/api.types.ts` - API 类型定义
- [ ] 1.5 创建 `app/core/utils/validation/validators.ts` - 校验工具
- [ ] 1.6 创建 `app/core/utils/formatters/formatters.ts` - 格式化工具
- [ ] 1.7 创建 `app/core/utils/helpers/helpers.ts` - 辅助函数
- [ ] 1.8 创建 `app/core/constants/index.ts` - 全局常量
- [ ] 1.9 创建 `app/core/types/global.d.ts` - 全局类型定义

## 2. 创建业务模块 (modules)

- [ ] 2.1 创建 `app/modules/auth/` - 认证模块
  - [ ] 2.1.1 迁移 `pages/login.vue`
  - [ ] 2.1.2 迁移 `pages/register.vue`
  - [ ] 2.1.3 迁移 `pages/change-password.vue`
  - [ ] 2.1.4 迁移 `stores/auth.js` → `auth.store.ts`
  - [ ] 2.1.5 创建 `auth.service.ts`
  - [ ] 2.1.6 创建 `useAuth.ts` composable

- [ ] 2.2 创建 `app/modules/dashboard/` - 仪表盘模块
  - [ ] 2.2.1 迁移 `pages/index.vue`

- [ ] 2.3 创建 `app/modules/settings/` - 设置模块
  - [ ] 2.3.1 迁移 `pages/settings.vue`
  - [ ] 2.3.2 迁移 `stores/version.js` → `version.store.ts`

- [ ] 2.4 创建 `app/modules/task/` - 任务管理模块
  - [ ] 2.4.1 迁移 `pages/task-management/[id].vue` → `pages/[id].vue`

- [ ] 2.5 创建 `app/modules/logs/` - 日志模块
  - [ ] 2.5.1 迁移 `pages/logs.vue`

- [ ] 2.6 创建 `app/modules/shared/` - 共享模块
  - [ ] 2.6.1 迁移 `components/AppHeader.vue`

## 3. 更新配置文件

- [ ] 3.1 更新 `nuxt.config.ts` - 配置 srcDir、components、imports、pinia
- [ ] 3.2 更新 `tsconfig.json` - 启用严格模式
- [ ] 3.3 添加 `zod` 依赖到 `package.json`

## 4. 迁移插件和中间件

- [ ] 4.1 创建 `app/plugins/` 目录
  - [ ] 4.1.1 迁移 `plugins/auth.client.js` → `auth.client.ts`
  - [ ] 4.1.2 迁移 `plugins/docker-port-fix.client.js` → `docker-port-fix.client.ts`
  - [ ] 4.1.3 迁移 `plugins/logger.client.js` → `logger.client.ts`
  - [ ] 4.1.4 创建 `pinia.ts` - Pinia 配置

- [ ] 4.2 创建 `app/middleware/` 目录
  - [ ] 4.2.1 迁移 `middleware/auth.js` → `auth.ts`
  - [ ] 4.2.2 迁移 `middleware/guest.js` → `guest.ts`
  - [ ] 4.2.3 迁移 `middleware/docker-port.global.js` → `docker-port.global.ts`

## 5. 创建布局和根组件

- [ ] 5.1 创建 `app/layouts/` 目录和默认布局
- [ ] 5.2 创建 `app/app.vue`
- [ ] 5.3 创建 `app/error.vue`

## 6. 更新文档

- [ ] 6.1 更新 `frontend/ARCHITECTURE.md` - 整合新架构
- [ ] 6.2 更新项目根目录 `CLAUDE.md` - 补充前端架构和代码规范

## 7. 清理旧目录

- [ ] 7.1 验证迁移完成后删除旧 `pages/` 目录
- [ ] 7.2 验证迁移完成后删除旧 `components/` 目录
- [ ] 7.3 验证迁移完成后删除旧 `stores/` 目录
- [ ] 7.4 验证迁移完成后删除旧 `utils/` 目录
- [ ] 7.5 验证迁移完成后删除旧 `plugins/` 目录
- [ ] 7.6 验证迁移完成后删除旧 `middleware/` 目录

## 8. 测试验证

- [ ] 8.1 运行 `npm run dev` 验证开发环境
- [ ] 8.2 运行 `npm run build` 验证构建
- [ ] 8.3 测试所有页面功能正常
- [ ] 8.4 测试 Docker 构建正常
