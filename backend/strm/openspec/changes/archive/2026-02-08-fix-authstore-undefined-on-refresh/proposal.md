## Why

日志页面 (`logs/index.vue`) 直接刷新时，会提示 `authStore is not defined` 错误，但从其他页面导航过去则正常。原因是 `onMounted` 钩子中调用了 `authStore.restoreAuth()`，但 `authStore` 变量从未被导入或定义。

## 问题分析

检查了所有使用 `authStore` 的页面：

| 文件 | 状态 |
|------|------|
| `logs/index.vue` | ❌ **缺少导入** |
| `pages/index.vue` | ✅ 正确 |
| `layouts/default.vue` | ✅ 正确 |
| `app.vue` | ✅ 正确 |
| `pages/auth/login.vue` | ✅ 正确 |
| `pages/auth/change-password.vue` | ✅ 正确 |
| `components/AppHeader.vue` | ✅ 正确 |

## What Changes

- 在 `logs/index.vue` 中添加 `useAuthStore` 的导入语句

## Capabilities

### New Capabilities
- 无（此修复不涉及新功能）

### Modified Capabilities
- 无（此修复不涉及需求变更，只是实现修复）

## Impact

- **受影响文件**: `frontend/app/pages/logs/index.vue`
- **修复内容**: 添加缺失的 `useAuthStore` 导入
