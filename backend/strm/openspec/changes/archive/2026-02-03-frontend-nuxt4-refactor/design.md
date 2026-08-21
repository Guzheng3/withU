## Context

当前前端项目结构：
```
frontend/
├── pages/           # 页面组件
├── components/      # 可复用组件
├── stores/          # Pinia 状态管理
├── utils/           # 工具函数
├── plugins/         # Nuxt 插件
├── middleware/      # 路由中间件
└── assets/          # 静态资源
```

现有问题：
1. 所有文件混在一起，缺乏模块边界
2. 缺少 API 服务层规范
3. 缺少 Zod 运行时校验
4. TypeScript 配置不严格
5. 缺少完整的代码规范文档

## Goals / Non-Goals

**Goals:**
- 迁移到 Feature-Sliced Design 改良版目录结构
- 清晰划分 core（基础设施）和 modules（业务模块）
- 建立完整的代码规范体系
- 添加 Zod 运行时校验
- 更新 CLAUDE.md 文档

**Non-Goals:**
- 不修改现有组件的业务逻辑
- 不添加新的功能特性
- 不改变 UI/UX 设计

## Decisions

1. **目录结构**: 采用 Feature-Sliced Design 改良版，将源码移到 `app/` 目录
2. **API 层**: 使用 Zod 进行运行时校验，创建 Service 类 + Composables 包装器模式
3. **状态管理**: Pinia Store 使用 shallowRef 优化只读数据，添加 reset 方法
4. **错误处理**: 建立 ApiError 异常类，统一拦截器处理
5. **类型安全**: 启用 TypeScript 严格模式，禁用 any 类型

## Risks / Trade-offs

- [风险] 目录迁移可能影响生产部署 → 提前测试 Docker 构建
- [风险] 迁移过程可能引入 bug → 保持业务逻辑不变，只调整结构
