/**
 * 辅助函数集合
 * 
 * @author hienao
 * @date 2026-01-31
 */

/**
 * 深拷贝
 */
export const deepClone = <T>(obj: T): T => {
  if (obj === null || typeof obj !== 'object') return obj
  if (obj instanceof Date) return new Date(obj.getTime()) as unknown as T
  if (obj instanceof Array) return obj.map(item => deepClone(item)) as unknown as T
  if (obj instanceof Object) {
    const copy = Object.create(Object.getPrototypeOf(obj))
    for (const key of Object.keys(obj)) {
      ;(copy as Record<string, unknown>)[key] = deepClone((obj as Record<string, unknown>)[key])
    }
    return copy as T
  }
  throw new Error('Unable to copy object')
}

/**
 * 对象剔除指定属性
 */
export const omit = <T extends object, K extends keyof T>(
  obj: T,
  keys: K[]
): Omit<T, K> => {
  const result = { ...obj }
  keys.forEach(key => delete result[key])
  return result
}

/**
 * 对象只保留指定属性
 */
export const pick = <T extends object, K extends keyof T>(
  obj: T,
  keys: K[]
): Pick<T, K> => {
  const result = {} as Pick<T, K>
  keys.forEach(key => {
    if (key in obj) {
      result[key] = obj[key]
    }
  })
  return result
}

/**
 * 数据清洗：移除字符串两端空白
 */
export const trimValues = <T extends Record<string, unknown>>(
  obj: T
): Record<string, unknown> => {
  const cleaned: Record<string, unknown> = {}
  for (const [key, value] of Object.entries(obj)) {
    if (typeof value === 'string') {
      cleaned[key] = value.trim()
    } else if (value !== undefined) {
      cleaned[key] = value
    }
  }
  return cleaned
}

/**
 * 防抖函数
 */
export const debounce = <T extends (...args: unknown[]) => unknown>(
  fn: T,
  delay: number
): ((...args: Parameters<T>) => void) => {
  let timer: ReturnType<typeof setTimeout> | null = null
  return (...args: Parameters<T>) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}

/**
 * 节流函数
 */
export const throttle = <T extends (...args: unknown[]) => unknown>(
  fn: T,
  limit: number
): ((...args: Parameters<T>) => void) => {
  let inThrottle = false
  return (...args: Parameters<T>) => {
    if (!inThrottle) {
      fn(...args)
      inThrottle = true
      setTimeout(() => (inThrottle = false), limit)
    }
  }
}

/**
 * 生成唯一 ID
 */
export const generateId = (): string => {
  return `${Date.now()}-${Math.random().toString(36).substring(2, 11)}`
}

/**
 * 等待指定时间
 */
export const sleep = (ms: number): Promise<void> => {
  return new Promise(resolve => setTimeout(resolve, ms))
}

/**
 * 尝试执行函数，失败返回默认值
 */
export const tryCatch = <T>(
  fn: () => T,
  fallback: T
): T => {
  try {
    return fn()
  } catch {
    return fallback
  }
}

/**
 * 判断值是否为空
 */
export const isEmpty = (value: unknown): boolean => {
  if (value === null || value === undefined) return true
  if (typeof value === 'string') return value.trim() === ''
  if (Array.isArray(value)) return value.length === 0
  if (typeof value === 'object') return Object.keys(value).length === 0
  return false
}

/**
 * 安全的 JSON 解析
 */
export const safeJsonParse = <T>(
  json: string,
  fallback: T
): T => {
  try {
    return JSON.parse(json) as T
  } catch {
    return fallback
  }
}

/**
 * 格式化 Key 为小写下划线风格
 */
export const toSnakeCase = (str: string): string => {
  return str
    .replace(/([a-z])([A-Z])/g, '$1_$2')
    .replace(/[\s-]+/g, '_')
    .toLowerCase()
}

/**
 * 格式化 Key 为小驼峰风格
 */
export const toCamelCase = (str: string): string => {
  return str
    .toLowerCase()
    .replace(/[-_]+(.)/g, (_, chr) => chr.toUpperCase())
}

/**
 * 数组去重
 */
export const unique = <T>(arr: T[], key?: keyof T): T[] => {
  if (!key) return [...new Set(arr)]
  const seen = new Set()
  return arr.filter(item => {
    const k = item[key]
    if (seen.has(k)) return false
    seen.add(k)
    return true
  })
}

/**
 * 数组分组
 */
export const groupBy = <T>(arr: T[], key: keyof T): Record<string, T[]> => {
  return arr.reduce((groups, item) => {
    const groupKey = String(item[key])
    if (!groups[groupKey]) groups[groupKey] = []
    groups[groupKey].push(item)
    return groups
  }, {} as Record<string, T[]>)
}

// 注意：以下函数已迁移到单独的 TypeScript 模块，请使用对应模块的导出：
// - isValidToken: 使用 ~/core/utils/token
// - logger: 使用 ~/core/utils/logger
// - apiCall: 使用 ~/core/utils/api

