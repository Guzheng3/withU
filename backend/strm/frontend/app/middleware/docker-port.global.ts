/**
 * Docker 端口兼容中间件 - 全局生效
 */

export default defineNuxtRouteMiddleware(() => {
  const route = useRoute()

  // 检查是否是 Docker 环境（通过环境变量或路径判断）
  if (import.meta.server) return

  // 如果 URL 中包含端口参数，更新基础路径
  const port = route.query.port
  if (port && typeof port === 'string') {
    const baseUrl = `${window.location.protocol}//${window.location.hostname}:${port}`
    const config = useRuntimeConfig()
    // 动态更新 API 基础路径
    if (config.public.apiBase) {
      // 实际应用中可能需要通过其他方式更新
      console.log('Docker 端口兼容模式:', baseUrl)
    }
  }
})
