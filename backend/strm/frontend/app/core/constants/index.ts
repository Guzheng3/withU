/**
 * 全局常量定义
 */

/** 应用名称 */
export const APP_NAME = 'withUstrm'

/** API 基础路径 */
export const API_BASE_PATH = '/api'

/** Token 存储 key */
export const TOKEN_KEY = 'token'

/** 用户信息存储 key */
export const USER_INFO_KEY = 'userInfo'

/** 主题存储 key */
export const THEME_KEY = 'theme'

/** 本地存储前缀 */
export const STORAGE_PREFIX = 'withUstrm_'

/** 默认分页大小 */
export const DEFAULT_PAGE_SIZE = 20

/** 最大分页大小 */
export const MAX_PAGE_SIZE = 100

/** 请求超时时间 (ms) */
export const REQUEST_TIMEOUT = 30000

/** Token 刷新提前时间 (ms) */
export const TOKEN_REFRESH_BEFORE = 5 * 60 * 1000

/** 日期格式 */
export const DATE_FORMAT = 'YYYY-MM-DD'
export const DATE_TIME_FORMAT = 'YYYY-MM-DD HH:mm:ss'
export const TIME_FORMAT = 'HH:mm:ss'

/** 文件大小限制 */
export const MAX_FILE_SIZE = 10 * 1024 * 1024 // 10MB

/** 支持的图片格式 */
export const SUPPORTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']

/** 支持的视频格式 */
export const SUPPORTED_VIDEO_TYPES = ['video/mp4', 'video/x-matroska', 'video/webm']

/** 路由名称常量 */
export const RouteNames = {
  HOME: 'index',
  LOGIN: 'login',
  REGISTER: 'register',
  CHANGE_PASSWORD: 'change-password',
  SETTINGS: 'settings',
  TASK_DETAIL: 'task-management-id',
  LOGS: 'logs'
} as const

/** 状态常量 */
export const Status = {
  IDLE: 'idle',
  LOADING: 'loading',
  SUCCESS: 'success',
  ERROR: 'error'
} as const

/** 任务状态 */
export const TaskStatus = {
  PENDING: 'pending',
  RUNNING: 'running',
  COMPLETED: 'completed',
  FAILED: 'failed',
  CANCELLED: 'cancelled'
} as const

/** 消息类型 */
export const MessageType = {
  INFO: 'info',
  SUCCESS: 'success',
  WARNING: 'warning',
  ERROR: 'error'
} as const
