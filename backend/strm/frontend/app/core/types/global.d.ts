/**
 * 全局 TypeScript 类型定义
 */

import type { ApiError } from '~/core/api/interceptors/error'

/**
 * 运行时应用配置
 */
interface AppConfig {
  API_BASE_URL: string
  APP_ENV: 'development' | 'production' | 'test'
  FEATURE_FLAGS: Record<string, boolean>
  MAX_UPLOAD_SIZE: number
}

declare global {
  interface Window {
    __APP_CONFIG__?: AppConfig
  }

  namespace NodeJS {
    interface Process {
      env: Record<string, string | undefined>
    }
  }
}

/**
 * 扩展 NuxtApp
 */
declare module '#app' {
  interface NuxtApp {
    $toast: {
      success(message: string): void
      error(message: string): void
      warning(message: string): void
      info(message: string): void
    }
  }
}

/**
 * 组件实例类型
 */
declare module 'vue' {
  interface ComponentCustomProperties {
    $toast: {
      success(message: string): void
      error(message: string): void
      warning(message: string): void
      info(message: string): void
    }
  }
}

/**
 * API 错误类型声明
 */
declare global {
  class ApiError extends Error {
    code: string
    status: number
    data?: any
    constructor(message: string, code: string, status: number, data?: any)
  }
}

export {}
