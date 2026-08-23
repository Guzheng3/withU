/**
 * 分页响应类型
 */
export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
  totalPages?: number
}

/**
 * 基础响应类型
 */
export interface BaseResponse<T> {
  data: T
  message?: string
  code?: number
}

/**
 * 错误响应类型
 */
export interface ErrorResponse {
  message: string
  code: string
  errors?: Record<string, string[]>
}

/**
 * 查询参数类型
 */
export interface QueryParams {
  page?: number
  limit?: number
  search?: string
  sort?: string
  order?: 'asc' | 'desc'
}

/**
 * API 状态枚举
 */
export enum ApiStatus {
  IDLE = 'idle',
  LOADING = 'loading',
  SUCCESS = 'success',
  ERROR = 'error'
}
