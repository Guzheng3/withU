/**
 * 格式化工具函数
 */

/**
 * 日期格式化
 */
export const formatDate = (
  date: string | number | Date,
  format: string = 'YYYY-MM-DD'
): string => {
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const hour = String(d.getHours()).padStart(2, '0')
  const minute = String(d.getMinutes()).padStart(2, '0')
  const second = String(d.getSeconds()).padStart(2, '0')

  return format
    .replace('YYYY', String(year))
    .replace('MM', month)
    .replace('DD', day)
    .replace('HH', hour)
    .replace('mm', minute)
    .replace('ss', second)
}

/**
 * 日期时间格式化
 */
export const formatDateTime = (date: string | number | Date): string => {
  return formatDate(date, 'YYYY-MM-DD HH:mm:ss')
}

/**
 * 相对时间格式化（如：3分钟前）
 */
export const formatRelativeTime = (date: string | number | Date): string => {
  const now = new Date()
  const d = new Date(date)
  const diff = now.getTime() - d.getTime()

  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)
  const weeks = Math.floor(days / 7)
  const months = Math.floor(days / 30)
  const years = Math.floor(days / 365)

  if (years > 0) return `${years}年前`
  if (months > 0) return `${months}个月前`
  if (weeks > 0) return `${weeks}周前`
  if (days > 0) return `${days}天前`
  if (hours > 0) return `${hours}小时前`
  if (minutes > 0) return `${minutes}分钟前`
  return '刚刚'
}

/**
 * 文件大小格式化
 */
export const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const k = 1024
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${units[i]}`
}

/**
 * 数字格式化（千分位）
 */
export const formatNumber = (num: number): string => {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

/**
 * 百分比格式化
 */
export const formatPercent = (
  value: number,
  decimals: number = 0
): string => {
  return `${(value * 100).toFixed(decimals)}%`
}

/**
 * 字节格式化（带单位）
 */
export const formatBytes = (bytes: number): string => {
  return formatFileSize(bytes)
}

/**
 * 字符串截断
 */
export const truncate = (str: string, length: number, suffix: string = '...'): string => {
  if (str.length <= length) return str
  return str.slice(0, length) + suffix
}

/**
 * 脱敏处理（手机号）
 */
export const maskPhone = (phone: string): string => {
  if (!phone || phone.length < 11) return phone
  return phone.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

/**
 * 脱敏处理（邮箱）
 */
export const maskEmail = (email: string): string => {
  if (!email || !email.includes('@')) return email
  const atIndex = email.indexOf('@')
  if (atIndex <= 2) return email
  const name = email.substring(0, atIndex)
  const domain = email.substring(atIndex + 1)
  return `${name[0]}${'*'.repeat(name.length - 2)}${name.slice(-1)}@${domain}`
}

/**
 * 状态文本映射
 */
export const mapStatusText = (
  status: string,
  mapping: Record<string, string>
): string => {
  return mapping[status] || status
}
