import { authenticatedApiCall } from '~/core/api/client'

export interface MediaSummary {
  id: number
  title: string
  originalTitle?: string | null
  releaseYear?: string | null
  posterUrl?: string | null
  backdropUrl?: string | null
  voteAverage?: number | null
  mediaType: string
  tmdbId?: number | null
  scrapeStatus?: string | null
  taskId: number
  episodeCount?: number
}

export interface MediaPageResult {
  total: number
  page: number
  pageSize: number
  items: MediaSummary[]
}

export interface MediaSource {
  id: number
  sourceFileName: string
  sourcePath: string
  strmPath?: string | null
  resolution?: string | null
  rank: number
}

export interface MediaEpisode {
  id: number
  sourceFileName: string
  sourcePath: string
  episodeNo: number
  sources?: MediaSource[]
}

export interface MediaDetail {
  id: number
  taskId: number
  sourcePath: string
  strmPath: string
  sourceFileName: string
  mediaType: string
  tmdbId?: number | null
  title: string
  originalTitle?: string | null
  releaseYear?: string | null
  overview?: string | null
  posterUrl?: string | null
  backdropUrl?: string | null
  voteAverage?: number | null
  scrapeStatus?: string | null
  createdAt?: string | null
  updatedAt?: string | null
  totalEpisodes?: number | null
  episodes?: MediaEpisode[]
}

export interface PlaybackResult {
  id: number
  title: string
  url: string
  mediaType: string
}

export interface MediaTaskOption {
  id: number
  taskName: string
}

export interface MediaQuery {
  taskId?: number | null
  mediaType?: string | null
  keyword?: string
  page?: number
  pageSize?: number
}

export async function fetchMediaLibrary(
  query: MediaQuery = {}
): Promise<{ items: MediaSummary[]; total: number; page: number; pageSize: number }> {
  const params: Record<string, string> = {}
  if (query.taskId != null) params.taskId = String(query.taskId)
  if (query.mediaType) params.mediaType = query.mediaType
  if (query.keyword) params.keyword = query.keyword
  params.page = String(query.page || 1)
  params.pageSize = String(query.pageSize || 24)
  const qs = new URLSearchParams(params).toString()
  const res = await authenticatedApiCall(`/media-library?${qs}`, { method: 'GET' })
  if (res.code !== 200) throw new Error(res.message || '加载媒体库失败')
  return res.data
}

export async function fetchMediaDetail(id: number): Promise<MediaDetail> {
  const res = await authenticatedApiCall(`/media-library/${id}`, { method: 'GET' })
  if (res.code !== 200) throw new Error(res.message || '加载媒体详情失败')
  return res.data
}

export async function fetchPlaybackUrl(id: number, sourceId?: number | null): Promise<PlaybackResult> {
  const qs = sourceId ? `?sourceId=${sourceId}` : ''
  const res = await authenticatedApiCall(`/media-library/${id}/play${qs}`, { method: 'GET' })
  if (res.code !== 200) throw new Error(res.message || '解析播放地址失败')
  return res.data
}

export async function fetchMediaTasks(): Promise<MediaTaskOption[]> {
  const res = await authenticatedApiCall('/media-library/tasks', { method: 'GET' })
  if (res.code !== 200) throw new Error(res.message || '加载任务列表失败')
  return res.data
}
