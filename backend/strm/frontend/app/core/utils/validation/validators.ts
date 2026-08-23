/**
 * 通用校验工具函数
 */

/**
 * 邮箱格式校验
 */
export const isEmail = (value: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(value)
}

/**
 * URL 格式校验
 */
export const isUrl = (value: string): boolean => {
  try {
    new URL(value)
    return true
  } catch {
    return false
  }
}

/**
 * 手机号格式校验（中国）
 */
export const isChinesePhone = (value: string): boolean => {
  const phoneRegex = /^1[3-9]\d{9}$/
  return phoneRegex.test(value)
}

/**
 * 身份证号校验（中国）
 */
export const isChineseIdCard = (value: string): boolean => {
  const idCardRegex = /(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/
  return idCardRegex.test(value)
}

/**
 * 字符串长度校验
 */
export const hasValidLength = (
  value: string,
  min: number,
  max: number
): boolean => {
  return value.length >= min && value.length <= max
}

/**
 * 数值范围校验
 */
export const isInRange = (
  value: number,
  min: number,
  max: number
): boolean => {
  return value >= min && value <= max
}

/**
 * 必填校验
 */
export const isRequired = (value: any): boolean => {
  if (value === null || value === undefined) {
    return false
  }
  if (typeof value === 'string' || Array.isArray(value)) {
    return value.length > 0
  }
  return true
}

/**
 * 数组唯一性校验
 */
export const isUnique = (array: any[], key?: string): boolean => {
  if (!key) {
    return new Set(array).size === array.length
  }
  const values = array.map(item => item[key])
  return new Set(values).size === values.length
}

/**
 * 表单校验结果类型
 */
export interface ValidationResult {
  isValid: boolean
  errors: Record<string, string>
}

/**
 * 创建表单校验器
 */
export const createValidator = <T extends Record<string, any>>(
  rules: Partial<Record<keyof T, (value: any, form: T) => string | null>>
) => {
  return (form: T): ValidationResult => {
    const errors: Record<string, string> = {}

    for (const [key, rule] of Object.entries(rules)) {
      if (rule) {
        const error = rule(form[key], form)
        if (error) {
          errors[key] = error
        }
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }
}

/**
 * 常用校验规则工厂
 */
export const Rules = {
  required: (message: string = '此项为必填') =>
    (value: any) => (isRequired(value) ? null : message),

  email: (message: string = '请输入有效的邮箱地址') =>
    (value: string) => (!value || isEmail(value) ? null : message),

  minLength: (min: number, message?: string) =>
    (value: string) =>
      (!value || value.length >= min ? null : message || `最少 ${min} 个字符`),

  maxLength: (max: number, message?: string) =>
    (value: string) =>
      (!value || value.length <= max ? null : message || `最多 ${max} 个字符`),

  pattern: (regex: RegExp, message: string) =>
    (value: string) => (!value || regex.test(value) ? null : message)
}
