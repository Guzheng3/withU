<template>
  <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
    <div class="mb-3 flex items-center justify-between">
      <h4 class="text-sm font-medium text-gray-700">OpenList 目录浏览</h4>
      <button
        type="button"
        class="text-xs text-blue-600 hover:text-blue-700 font-medium"
        :disabled="loading"
        @click="loadRoot"
      >
        {{ loading ? '加载中...' : '刷新根目录' }}
      </button>
    </div>

    <div
      v-if="error"
      class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600"
    >
      <span class="font-medium">无法打开该目录：</span>{{ error }}
      <span class="mt-0.5 block text-red-500/80">该目录可能无权限访问或存储后端异常，可通过上方路径返回上一级。</span>
    </div>

    <div class="max-h-72 min-h-[8rem] flex flex-col rounded-lg border border-gray-200 bg-white">
      <div class="flex-1 overflow-y-auto p-2">
        <div v-if="loading && !rootLoaded" class="flex justify-center py-8">
          <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-blue-500 border-t-transparent"></div>
        </div>

        <div v-else-if="items.length === 0 && !loading" class="py-8 text-center text-sm text-gray-400">
          暂无目录内容
        </div>

        <div v-else class="space-y-1">
          <div
            v-for="item in items"
            :key="item.path"
            class="group flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-pink-50 cursor-pointer"
            :class="isSelected(item.path) ? 'bg-blue-100' : ''"
            @click="selectItem(item)"
            @dblclick="openItem(item)"
            @contextmenu.prevent="!selectMode && showContextMenu($event, item)"
          >
            <svg
              v-if="item.type === 'folder'"
              class="h-4 w-4 shrink-0 text-amber-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
            </svg>
            <svg
              v-else
              class="h-4 w-4 shrink-0 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <span class="truncate text-sm text-gray-700">{{ item.name }}</span>
            <span v-if="item.type === 'file'" class="shrink-0 text-xs text-gray-400">{{ formatSize(item.size) }}</span>
            <span v-if="!selectMode && currentPath !== rootPath" class="ml-auto flex shrink-0 items-center gap-1">
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-600 opacity-0 transition-opacity hover:bg-purple-200 group-hover:opacity-100"
                :disabled="recognizing"
                :title="'识别：' + item.name"
                @click.stop="recognizeItem(item)"
              >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"></path>
                </svg>
                识别
              </button>
              <svg
                v-if="item.type === 'folder'"
                class="h-3.5 w-3.5 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </span>
            <svg
              v-else-if="item.type === 'folder'"
              class="ml-auto h-3.5 w-3.5 shrink-0 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 根目录识别操作区（内容为空时仍可识别根目录） -->
    <div v-if="!selectMode && currentPath === rootPath" class="border-t border-gray-100 bg-gray-50 px-3 py-2">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg bg-purple-100 px-3 py-1.5 text-xs font-medium text-purple-600 hover:bg-purple-200 transition-colors"
        :disabled="recognizing || recognizingRoot"
        @click="recognizeRootFolders"
      >
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"></path>
        </svg>
        {{ recognizingRoot ? '识别中...' : '识别根目录' }}
      </button>
      <p class="mt-1 text-[11px] text-gray-400">自动识别媒体类型，可识别根目录下各文件夹或悬停文件夹/文件显示识别按钮</p>
    </div>

    <!-- 右键菜单（渲染到 body，避免被容器遮挡） -->
    <Teleport to="body">
      <div
        v-if="contextMenu"
        class="fixed z-[9999] min-w-[11rem] overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-2xl"
        :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
        @click.stop
      >
        <div class="border-b border-gray-100 px-3 py-1.5">
          <div class="truncate text-xs font-medium text-gray-700">{{ contextMenu.item.name }}</div>
          <div class="text-[10px] text-gray-400">{{ contextMenu.item.type === 'folder' ? '文件夹' : '文件' }}</div>
        </div>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-pink-50"
          @click="addTaskFromContext"
        >
          <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
          添加任务
        </button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-pink-50"
          @click="recognizeFromContext"
        >
          <svg class="h-4 w-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"></path>
          </svg>
          识别
        </button>
        <button
          v-if="contextMenu.item.type === 'folder'"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-pink-50"
          @click="openItem(contextMenu.item)"
        >
          <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
          </svg>
          打开文件夹
        </button>
      </div>
    </Teleport>

    <!-- 面包屑导航 -->
    <div class="mt-2 flex flex-wrap items-center gap-1 text-xs text-gray-500">
      <button
        type="button"
        class="rounded px-1.5 py-0.5 hover:bg-gray-100 hover:text-blue-600"
        @click="goToPath(rootPath)"
      >
        根目录
      </button>
      <template v-for="(seg, idx) in breadcrumbs" :key="idx">
        <svg class="h-3 w-3 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <button
          type="button"
          class="rounded px-1.5 py-0.5 font-medium hover:bg-gray-100"
          :class="idx === breadcrumbs.length - 1 ? 'text-gray-800' : 'text-gray-500 hover:text-blue-600'"
          @click="goToPath(seg.path)"
        >
          {{ seg.name }}
        </button>
      </template>
    </div>
    <p v-if="currentPath !== rootPath" class="mt-1 text-[11px] text-gray-400">
      双击文件夹进入，点击上方路径可返回上一级
    </p>

    <!-- 识别错误 -->
    <div v-if="recognizeError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600">
      {{ recognizeError }}
    </div>

    <!-- 识别结果：海报墙 + JSON -->
    <div v-if="recognizeResult" class="mt-4 border-t border-gray-200 pt-3">
      <div class="mb-3 flex items-center justify-between">
        <h4 class="text-sm font-medium text-gray-700">
          识别结果
          <span v-if="matchedResults.length > 1" class="ml-1 text-xs text-gray-400">
            {{ matchedResults.length }} 部作品
          </span>
          <span v-if="!hasMatchedResult && recognizeResult.videoFileCount != null" class="ml-1 text-xs text-gray-400">
            · 共 {{ recognizeResult.videoFileCount }} 个媒体文件
          </span>
        </h4>
        <button
          type="button"
          class="text-xs text-blue-600 hover:text-blue-700 font-medium"
          @click="showRawJson = !showRawJson"
        >
          {{ showRawJson ? '收起 JSON' : '查看 JSON' }}
        </button>
      </div>

      <!-- 匹配成功：海报墙 -->
      <div v-if="hasMatchedResult" class="space-y-5">
        <div
          v-for="(result, idx) in matchedResults"
          :key="result.tmdbId ?? idx"
          class="flex gap-5 rounded-xl border border-gray-100 bg-white p-4"
        >
          <img
            v-if="result.posterUrl"
            :src="result.posterUrl"
            :alt="result.title"
            class="h-56 w-40 shrink-0 rounded-xl bg-gray-100 object-cover shadow-lg"
            style="object-position: center top"
          >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="badge-success">{{ mediaTypeLabel(result.mediaType) }}</span>
              <span class="text-xs text-gray-400">TMDB #{{ result.tmdbId }}</span>
              <span v-if="matchedResults.length > 1" class="text-xs text-gray-400">{{ idx + 1 }}</span>
            </div>
            <h3 class="mt-2 text-xl font-semibold text-gray-800">{{ result.title }}</h3>
            <p v-if="result.originalTitle && result.originalTitle !== result.title" class="mt-1 text-sm text-gray-500">
              {{ result.originalTitle }}
            </p>
            <div class="mt-2 flex gap-4 text-sm text-gray-500">
              <span v-if="result.year">{{ result.year }}</span>
              <span v-if="result.voteAverage">评分 {{ Number(result.voteAverage).toFixed(1) }}</span>
              <span>{{ result.videoFileCount }} 个媒体文件</span>
            </div>
            <p class="mt-3 line-clamp-4 text-sm leading-6 text-gray-600">
              {{ result.overview || '暂无简介' }}
            </p>
          </div>
        </div>
      </div>

      <!-- 人工核验区：未匹配/低置信度条目 -->
      <div v-if="unmatchedResults.length > 0" class="mt-5 space-y-3">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-semibold text-amber-600">
            需人工核验（{{ unmatchedResults.length }}）
          </h4>
          <span v-if="hasMatchedResult" class="text-xs text-gray-400">
            以下文件未能可靠识别，请到手动刮削确认
          </span>
        </div>
        <div
          v-for="(result, idx) in unmatchedResults"
          :key="`unmatched-${idx}`"
          class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/60 p-3"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="badge-neutral text-xs">{{ mediaTypeLabel(result.mediaType) }}</span>
              <span class="text-xs text-amber-600/80">未匹配</span>
              <span class="text-xs text-gray-400">{{ result.videoFileCount }} 个媒体文件</span>
            </div>
            <p class="mt-1 truncate text-sm text-gray-700">
              识别标题：
              <span class="font-medium">{{ result.searchTitle || '未知' }}</span>
              <span v-if="result.searchYear">（{{ result.searchYear }}）</span>
            </p>
            <p class="mt-0.5 text-xs text-amber-600/70">
              {{ result.matchMessage || '无法可靠识别该文件' }}
            </p>
          </div>
          <button
            v-if="manualScrapingTaskId"
            type="button"
            class="btn-primary shrink-0 px-3 py-1.5 text-xs"
            @click="goManualScraping"
          >
            去人工核验
          </button>
        </div>
      </div>

      <!-- 全部未匹配（无人工核验明细时兜底展示） -->
      <div
        v-if="!hasMatchedResult && unmatchedResults.length === 0"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700"
      >
        {{ firstResult?.matchMessage || '未匹配到媒体信息' }}
        <div v-if="firstResult?.searchTitle" class="mt-1 text-xs text-amber-600/80">
          识别标题：{{ firstResult.searchTitle }}<span v-if="firstResult.searchYear">（{{ firstResult.searchYear }}）</span>
          · 共 {{ recognizeResult.videoFileCount }} 个媒体文件
        </div>
      </div>

      <!-- 原始 JSON -->
      <pre v-if="showRawJson" class="mt-3 max-h-72 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100 font-mono">
{{ rawJson }}</pre>
    </div>

    <!-- 根目录批量识别结果 -->
    <div v-if="rootRecognizeResults.length > 0" class="mt-4 border-t border-gray-200 pt-3">
      <div class="mb-3 flex items-center justify-between">
        <h4 class="text-sm font-medium text-gray-700">根目录识别结果（{{ rootRecognizeResults.length }}）</h4>
        <span v-if="recognizingRoot" class="text-xs text-purple-600">
          {{ rootRecognizeProgress }} / {{ rootRecognizeTotal }}
        </span>
      </div>
      <div class="space-y-3">
        <div
          v-for="entry in rootRecognizeResults"
          :key="entry.folderPath"
          class="rounded-xl border border-gray-200 bg-white p-3"
        >
          <div class="mb-2 flex items-center justify-between">
            <span class="truncate text-sm font-medium text-gray-700">{{ entry.folderName }}</span>
            <span v-if="entry.error" class="ml-2 shrink-0 text-xs text-red-600">{{ entry.error }}</span>
          </div>
          <div v-if="entry.error" class="text-xs text-gray-400">该文件夹识别失败</div>
          <div v-else-if="entry.result && entry.result.results && entry.result.results.some((r) => r.matched)" class="space-y-3">
            <div
              v-for="(res, idx) in entry.result.results.filter((r) => r.matched)"
              :key="res.tmdbId ?? idx"
              class="flex gap-4"
            >
              <img
                v-if="res.posterUrl"
                :src="res.posterUrl"
                :alt="res.title"
                class="h-40 w-28 shrink-0 rounded-lg bg-gray-100 object-cover shadow"
                style="object-position: center top"
              >
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="badge-success">{{ mediaTypeLabel(res.mediaType) }}</span>
                  <span class="text-xs text-gray-400">TMDB #{{ res.tmdbId }}</span>
                </div>
                <h5 class="mt-1 truncate text-base font-semibold text-gray-800">{{ res.title }}</h5>
                <p v-if="res.originalTitle && res.originalTitle !== res.title" class="mt-0.5 truncate text-sm text-gray-500">
                  {{ res.originalTitle }}
                </p>
                <div class="mt-1 flex gap-4 text-sm text-gray-500">
                  <span v-if="res.year">{{ res.year }}</span>
                  <span v-if="res.voteAverage">评分 {{ Number(res.voteAverage).toFixed(1) }}</span>
                  <span>{{ res.videoFileCount }} 个媒体文件</span>
                </div>
              </div>
            </div>
          </div>
          <div v-else-if="entry.result" class="text-sm text-amber-600">
            {{ entry.result.results?.[0]?.matchMessage || '未匹配到媒体信息' }}
            <div v-if="entry.result.results?.[0]?.searchTitle" class="mt-0.5 text-xs text-amber-600/80">
              识别标题：{{ entry.result.results[0].searchTitle }}<span v-if="entry.result.results[0].searchYear">（{{ entry.result.results[0].searchYear }}）</span>
              · 共 {{ entry.result.videoFileCount }} 个媒体文件
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { authenticatedApiCall } from '~/core/api/client'
import logger from '~/core/utils/logger'

const props = defineProps({
  configId: {
    type: [String, Number],
    required: true
  },
  selectMode: {
    type: Boolean,
    default: false
  },
  manualScrapingTaskId: {
    type: [String, Number],
    default: null
  }
})

const emit = defineEmits(['select', 'add-task'])

const router = useRouter()

const goManualScraping = () => {
  if (!props.manualScrapingTaskId) return
  router.push(`/manual-scraping/${props.manualScrapingTaskId}`)
}

const items = ref([])
const currentPath = ref('/')
const rootPath = ref('/')
const loading = ref(false)
const rootLoaded = ref(false)
const error = ref('')
const recognizing = ref(false)
const recognizeError = ref('')
const recognizeResult = ref(null)
const showRawJson = ref(false)
const rawJson = computed(() => JSON.stringify(recognizeResult.value, null, 2))
const recognizeResultsList = computed(() => recognizeResult.value?.results || [])
const matchedResults = computed(() => recognizeResultsList.value.filter((r) => r.matched))
const hasMatchedResult = computed(() => matchedResults.value.length > 0)
const firstResult = computed(() => recognizeResultsList.value[0] || null)
const unmatchedResults = computed(() => recognizeResultsList.value.filter((r) => !r.matched))
const contextMenu = ref(null)
const selectedPath = ref('')
const recognizingRoot = ref(false)
const rootRecognizeResults = ref([])
const rootRecognizeProgress = ref(0)
const rootRecognizeTotal = ref(0)

const loadDirectory = async (path) => {
  loading.value = true
  error.value = ''
  try {
    const response = await authenticatedApiCall(`/openlist-config/${props.configId}/browse`, {
      method: 'POST',
      body: { path }
    })
    if (response.code !== 200) throw new Error(response.message || '目录读取失败')
    currentPath.value = path
    items.value = response.data || []
  } catch (e) {
    logger.error('浏览目录失败:', e)
    error.value = e.message || '目录读取失败'
  } finally {
    loading.value = false
  }
}

const loadRoot = () => {
  rootLoaded.value = true
  loadDirectory(rootPath.value)
}

const selectItem = (item) => {
  if (props.selectMode) {
    emit('select', item)
    return
  }
  selectedPath.value = item.path
  emit('select', item)
}

const openItem = (item) => {
  if (item.type !== 'folder') return
  loadDirectory(item.path)
}

const showContextMenu = (event, item) => {
  contextMenu.value = { x: event.clientX, y: event.clientY, item }
}

const closeContextMenu = () => {
  contextMenu.value = null
}

const recognizeItem = (item) => {
  if (item.type === 'folder') {
    recognizePath({ path: item.path, recursive: false })
  } else {
    recognizePath({ path: item.path, singleFile: item.path })
  }
}

const addTaskFromContext = () => {
  const item = contextMenu.value?.item
  closeContextMenu()
  if (!item) return
  if (item.type === 'folder') {
    emit('add-task', item)
  } else {
    const path = (item.path || '').includes('/') ? item.path.replace(/\/[^/]*$/, '') : currentPath.value
    const folder = { ...item, type: 'folder', path }
    emit('add-task', folder)
  }
}

const recognizeFromContext = () => {
  const item = contextMenu.value?.item
  closeContextMenu()
  if (!item) return
  recognizeItem(item)
}

const goUp = () => {
  const segments = currentPath.value.split('/').filter(Boolean)
  segments.pop()
  const parent = '/' + segments.join('/')
  loadDirectory(parent || rootPath.value)
}

const breadcrumbs = computed(() => {
  const current = currentPath.value || rootPath.value
  const segments = current.split('/').filter(Boolean)
  let acc = rootPath.value.endsWith('/') ? rootPath.value.replace(/\/$/, '') : rootPath.value
  if (acc === '') acc = ''
  return segments.map((seg) => {
    acc = acc + '/' + seg
    return { name: seg, path: acc }
  })
})

const goToPath = (path) => {
  if (path === currentPath.value) return
  loadDirectory(path)
}

const isSelected = (path) => path === selectedPath.value

const autoDetectTypes = (path) => {
  const p = (path || '').toLowerCase()
  const hasAnime = /动漫|动画|anime|卡通|番剧/.test(p)
  const hasTv = /电视剧|剧集|tv|drama|season|综艺|真人秀/.test(p)
  const hasMovie = /电影|movie|cinema|院线|剧场版/.test(p)
  if (hasAnime) return ['anime', 'tv']
  if (hasTv) return ['tv', 'movie']
  if (hasMovie) return ['movie', 'tv']
  return ['tv', 'movie']
}

const recognizePath = async (opts) => {
  const { path, recursive = true, singleFile = null } = typeof opts === 'string' ? { path: opts } : opts
  recognizing.value = true
  recognizeError.value = ''
  recognizeResult.value = null
  showRawJson.value = false
  const types = autoDetectTypes(path)
  let lastMatched = null
  let lastUnmatched = null
  try {
    for (const t of types) {
      const response = await authenticatedApiCall(`/openlist-config/${props.configId}/recognize`, {
        method: 'POST',
        body: { path, libraryType: t, recursive, singleFile }
      })
      if (response.code !== 200) {
        lastUnmatched = response
        continue
      }
      const result = response.data
      const anyMatched = result && result.results && result.results.some((r) => r.matched)
      if (anyMatched) {
        lastMatched = result
        break
      }
      lastUnmatched = response
    }
    if (lastMatched) {
      recognizeResult.value = lastMatched
    } else if (lastUnmatched) {
      const data = lastUnmatched.data
      if (data && ((data.results && data.results.length) || data.videoFileCount != null)) {
        recognizeResult.value = data
      } else {
        throw new Error(lastUnmatched.message || '识别失败')
      }
    } else {
      throw new Error('识别失败')
    }
  } catch (e) {
    logger.error('识别目录失败:', e)
    recognizeError.value = e.message || '识别失败'
  } finally {
    recognizing.value = false
  }
}

const recognizeSingleFolder = async (path) => {
  const types = autoDetectTypes(path)
  let lastMatched = null
  let lastUnmatched = null
  for (const t of types) {
    const response = await authenticatedApiCall(`/openlist-config/${props.configId}/recognize`, {
      method: 'POST',
      body: { path, libraryType: t }
    })
    if (response.code !== 200) {
      lastUnmatched = response
      continue
    }
    const result = response.data
    const anyMatched = result && result.results && result.results.some((r) => r.matched)
    if (anyMatched) {
      return result
    }
    lastUnmatched = response
  }
  if (lastUnmatched) {
    const data = lastUnmatched.data
    if (data && ((data.results && data.results.length) || data.videoFileCount != null)) {
      return data
    }
    throw new Error(lastUnmatched.message || '识别失败')
  }
  throw new Error('识别失败')
}

const recognizeRootFolders = async () => {
  const folders = items.value.filter((item) => item.type === 'folder')
  if (folders.length === 0) {
    recognizeError.value = '根目录下没有可识别的文件夹'
    return
  }
  recognizingRoot.value = true
  recognizeError.value = ''
  recognizeResult.value = null
  rootRecognizeResults.value = []
  rootRecognizeTotal.value = folders.length
  rootRecognizeProgress.value = 0
  try {
    for (const folder of folders) {
      try {
        const result = await recognizeSingleFolder(folder.path)
        rootRecognizeResults.value.push({ folderPath: folder.path, folderName: folder.name, result })
      } catch (e) {
        logger.error('识别文件夹失败:', folder.path, e)
        rootRecognizeResults.value.push({
          folderPath: folder.path,
          folderName: folder.name,
          error: e.message || '识别失败'
        })
      }
      rootRecognizeProgress.value += 1
    }
  } finally {
    recognizingRoot.value = false
  }
}

const recognize = recognizePath

const formatSize = (size) => {
  if (!size && size !== 0) return ''
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let value = size
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  return `${value.toFixed(unit > 1 ? 1 : 0)} ${units[unit]}`
}

const mediaTypeLabel = (type) => (type === 'movie' ? '电影' : type === 'tv' ? '电视剧' : '动画')

const onGlobalClick = () => {
  closeContextMenu()
}

onMounted(() => {
  loadRoot()
  window.addEventListener('click', onGlobalClick)
})

onUnmounted(() => {
  window.removeEventListener('click', onGlobalClick)
})
</script>
