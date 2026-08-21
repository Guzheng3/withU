/**
 * Token 自动刷新服务
 * 定时检查 Token 状态，在需要时自动刷新
 * 
 * @author hienao
 * @date 2026-01-31
 */

import { shouldRefreshToken } from '~/core/utils/token'

/**
 * Token 刷新服务状态
 */
export interface TokenRefreshServiceStatus {
  isInitialized: boolean
  isRunning: boolean
  checkIntervalMs: number
}

// 刷新检查间隔（毫秒）- 每小时检查一次
const CHECK_INTERVAL = 60 * 60 * 1000

// 定时器ID
let refreshTimer: ReturnType<typeof setInterval> | null = null

// 是否已初始化
let isInitialized = false

/**
 * 检查并刷新 Token
 * @returns 是否执行了刷新
 */
export async function checkAndRefreshToken(): Promise<boolean> {
  if (!import.meta.client) {
    return false
  }

  try {
    const { useAuthStore } = await import('~/core/stores/auth')
    const authStore = useAuthStore()

    // 检查是否已登录
    if (!authStore.isAuthenticated) {
      console.log('[TokenRefresh] 用户未登录，跳过刷新检查')
      return false
    }

    const token = authStore.getToken
    if (!token) {
      return false
    }

    // 检查是否需要刷新
    if (shouldRefreshToken(token)) {
      console.log('[TokenRefresh] 检测到Token需要刷新，正在刷新...')
      const success = await authStore.refreshToken()
      if (success) {
        console.log('[TokenRefresh] Token刷新成功')
      } else {
        console.warn('[TokenRefresh] Token刷新失败')
      }
      return success
    }

    console.log('[TokenRefresh] Token状态良好，无需刷新')
    return false
  } catch (error) {
    console.error('[TokenRefresh] 检查Token时发生错误:', error)
    return false
  }
}

/**
 * 初始化 Token 刷新服务
 * 应在应用启动时调用（如 app.vue 的 onMounted）
 */
export function initTokenRefreshService(): void {
  if (!import.meta.client) {
    return
  }

  if (isInitialized) {
    console.log('[TokenRefresh] 服务已初始化，跳过重复初始化')
    return
  }

  console.log('[TokenRefresh] 初始化Token自动刷新服务')

  // 立即执行一次检查
  checkAndRefreshToken()

  // 设置定时检查
  refreshTimer = setInterval(() => {
    checkAndRefreshToken()
  }, CHECK_INTERVAL)

  isInitialized = true
  console.log(`[TokenRefresh] 服务已启动，检查间隔: ${CHECK_INTERVAL / 1000 / 60} 分钟`)
}

/**
 * 停止 Token 刷新服务
 * 应在用户登出时调用
 */
export function stopTokenRefreshService(): void {
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
    console.log('[TokenRefresh] 服务已停止')
  }
  isInitialized = false
}

/**
 * 重启 Token 刷新服务
 * 可用于登录后重新启动服务
 */
export function restartTokenRefreshService(): void {
  stopTokenRefreshService()
  initTokenRefreshService()
}

/**
 * 获取服务状态
 * @returns 服务状态
 */
export function getTokenRefreshServiceStatus(): TokenRefreshServiceStatus {
  return {
    isInitialized,
    isRunning: !!refreshTimer,
    checkIntervalMs: CHECK_INTERVAL
  }
}
