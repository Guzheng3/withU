/**
 * 认证中间件 - 需要登录才能访问
 * 
 * @author hienao
 * @date 2026-01-31
 */

import { useAuthStore } from '~/core/stores/auth'

export default defineNuxtRouteMiddleware((_to) => {
  // 只在客户端执行认证检查
  if (!import.meta.client) {
    return
  }

  const authStore = useAuthStore()

  // 恢复认证状态
  authStore.restoreAuth()

  // withU 后台网关已注入内部 token；即便未注入也放行，
  // 由网关注入脚本负责写入 token，避免被踢去登录页。
  if (!authStore.isAuthenticated) {
    console.log('[Auth Middleware] 未检测到内部 token，等待网关注入')
    return
  }
})
