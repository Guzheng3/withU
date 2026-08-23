# Frontend Module Structure

## Description

业务模块层按功能领域划分，每个模块自包含完整的 MVC 架构。

## Directory Structure

```
app/
└── modules/
    ├── auth/                   # 认证授权模块
    │   ├── components/         # 模块专属组件
    │   │   ├── AuthLoginForm.vue
    │   │   └── AuthRegisterForm.vue
    │   ├── composables/        # 业务逻辑
    │   │   ├── useAuth.ts
    │   │   └── usePermission.ts
    │   ├── pages/              # 路由页面
    │   │   ├── login.vue
    │   │   ├── register.vue
    │   │   └── change-password.vue
    │   ├── services/           # API 服务层
    │   │   └── auth.service.ts
    │   └── stores/             # Pinia 状态管理
    │       └── auth.store.ts
    │
    ├── dashboard/              # 仪表盘模块
    │   ├── components/
    │   ├── composables/
    │   ├── pages/
    │   │   └── index.vue
    │   └── stores/
    │
    ├── settings/               # 设置模块
    │   ├── components/
    │   ├── composables/
    │   ├── pages/
    │   │   └── index.vue
    │   └── stores/
    │
    ├── task/                   # 任务管理模块
    │   ├── components/
    │   ├── composables/
    │   ├── pages/
    │   │   ├── index.vue
    │   │   └── [id].vue
    │   └── stores/
    │
    └── logs/                   # 日志模块
        ├── components/
        ├── composables/
        ├── pages/
        │   └── index.vue
        └── stores/
```

## Requirements

### Module Structure

1. **命名规范**
   - 组件文件: PascalCase，模块前缀
   - 服务文件: camelCase，Service 后缀
   - Store 文件: camelCase，.store.ts 后缀
   - Composables: camelCase，use 前缀

2. **文件组织**
   - 每个模块包含 components、composables、pages、services、stores
   - 页面组件放在 pages/ 目录
   - API 服务与页面分离

### Component Convention

```vue
<!-- 文件命名: AuthLoginForm.vue -->
<script setup lang="ts">
// 组件逻辑
</script>

<template>
  <!-- 模板内容 -->
</template>
```

### Service Layer

```typescript
// 文件命名: auth.service.ts
import { z } from 'zod'
import { $api } from '~/core/api/client'

// Zod Schema 定义
const LoginSchema = z.object({
  username: z.string().min(1),
  password: z.string().min(6)
})

// Service 类（纯函数式）
export const AuthService = {
  async login(data: z.infer<typeof LoginSchema>) {
    const response = await $api('/api/auth/login', {
      method: 'POST',
      body: data
    })
    return response
  }
}
```

## Migration Mapping

| Original Path | New Path |
|--------------|----------|
| `pages/login.vue` | `modules/auth/pages/login.vue` |
| `pages/register.vue` | `modules/auth/pages/register.vue` |
| `pages/change-password.vue` | `modules/auth/pages/change-password.vue` |
| `pages/index.vue` | `modules/dashboard/pages/index.vue` |
| `pages/settings.vue` | `modules/settings/pages/index.vue` |
| `pages/logs.vue` | `modules/logs/pages/index.vue` |
| `pages/task-management/[id].vue` | `modules/task/pages/[id].vue` |
| `stores/auth.js` | `modules/auth/stores/auth.store.ts` |
| `stores/version.js` | `modules/settings/stores/version.store.ts` |
| `components/AppHeader.vue` | `modules/shared/components/AppHeader.vue` |
