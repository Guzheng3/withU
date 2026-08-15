# Frontend Code Standards

## Description

前端代码规范体系，包括命名规范、API 服务层规范、状态管理规范、错误处理规范和性能优化规范。

## Requirements

### 1. Naming Conventions

| Type | Convention | Examples |
|------|-----------|----------|
| Component files | PascalCase with module prefix | `AuthLoginForm.vue`, `UserProfileCard.vue`, `BaseButton.vue` |
| Composables | camelCase with use prefix | `useAuth()`, `useUserList()`, `useFormValidator()` |
| Service files | camelCase with Service suffix | `user.service.ts`, `auth.service.ts` |
| Store files | camelCase with .store.ts suffix | `auth.store.ts`, `user.store.ts` |
| Utility files | camelCase | `validators.ts`, `formatters.ts`, `helpers.ts` |
| Directories | kebab-case | `components/`, `services/`, `utils/` |

### 2. API Service Layer

```typescript
// app/modules/user/services/user.service.ts
import { z } from 'zod'
import { $api } from '~/core/api/client'

// 1. Zod Schema 定义（运行时校验）
const UserSchema = z.object({
  id: z.string().uuid(),
  name: z.string().min(1).max(50),
  email: z.string().email()
})

export type User = z.infer<typeof UserSchema>

// 2. Service 类（纯函数式，不依赖 Vue 上下文）
export const UserService = {
  async getUsers(params: { page: number; limit: number }) {
    const response = await $api('/api/users', { query: params })
    return z.object({
      data: z.array(UserSchema),
      total: z.number()
    }).parse(response)
  }
}
```

### 3. State Management

```typescript
// app/modules/user/stores/user.store.ts
import { defineStore } from 'pinia'
import { shallowRef } from 'vue'
import { UserService } from '../services/user.service'

export const useUserStore = defineStore('user', () => {
  // 只读数据使用 shallowRef
  const userList = shallowRef<User[]>([])
  const loading = ref(false)

  // 必须实现 reset 方法
  const reset = () => {
    userList.value = []
    loading.value = false
  }

  return { userList, loading, reset }
})
```

### 4. Error Handling

```typescript
// app/core/api/interceptors/error.ts
export class ApiError extends Error {
  constructor(
    message: string,
    public code: string,
    public status: number,
    public data?: any
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

// 使用方式
try {
  await UserService.getUsers(params)
} catch (error) {
  if (error instanceof ApiError && error.code === 'VALIDATION_ERROR') {
    // 处理验证错误
  }
}
```

### 5. Performance Optimization

1. **响应式优化**
   ```typescript
   // 只读数据使用 shallowRef
   const largeDataset = shallowRef<any[]>([])
   ```

2. **组件懒加载**
   ```typescript
   const HeavyChart = defineAsyncComponent(() =>
     import('../components/HeavyChart.vue')
   )
   ```

3. **大型列表优化**
   ```vue
   <tr v-for="item in list" :key="item.id" v-memo="[item.id, item.status]">
   ```

### 6. TypeScript Strict Mode

```json
// tsconfig.json
{
  "compilerOptions": {
    "strict": true,
    "noImplicitAny": true,
    "strictNullChecks": true,
    "noUncheckedIndexedAccess": true
  }
}
```

## Files to Update

- `frontend/tsconfig.json`
- `frontend/package.json` (添加 zod 依赖)
- `CLAUDE.md` (补充前端架构和代码规范)
