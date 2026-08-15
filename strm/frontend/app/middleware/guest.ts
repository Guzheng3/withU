/**
 * 访客中间件 - 仅允许未登录用户访问
 * 
 * @author hienao
 * @date 2026-01-31
 */

import { useAuthStore } from '~/core/stores/auth'

export default defineNuxtRouteMiddleware((_to) => {
  // 只在客户端执行检查
  if (!import.meta.client) {
    return
  }

  const authStore = useAuthStore()

  // 恢复认证状态
  authStore.restoreAuth()

  // 如果已登录，跳转到首页
  if (authStore.isAuthenticated) {
    return navigateTo('/', { replace: true })
  }
})
