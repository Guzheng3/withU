<template>
  <div class="min-h-screen">
    <!-- 页头 -->
    <div class="relative overflow-hidden bg-gradient-to-br from-[#FDF2F8] via-[#F4F9FF] to-[#EFFBF4]">
      <div
        class="absolute inset-0 pointer-events-none"
        style="background-image: radial-gradient(circle at 15% 20%, rgba(236,72,153,.14), transparent 45%), radial-gradient(circle at 85% 15%, rgba(56,189,248,.16), transparent 45%), radial-gradient(circle at 60% 85%, rgba(52,211,153,.12), transparent 40%)"
      />
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-8 md:pt-14 md:pb-12">
        <div class="animate-slide-up">
          <p class="text-xs font-semibold text-pink-500 uppercase tracking-[0.25em] mb-2">Media Library</p>
          <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-gray-800">影视媒体库</h1>
          <p class="mt-2 text-gray-500 max-w-2xl">浏览、搜索并播放已同步的影视内容</p>

          <div class="mt-6 flex flex-col md:flex-row md:items-center gap-3 max-w-3xl">
            <div class="relative flex-1">
              <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="keyword"
                type="text"
                placeholder="搜索电影或剧集..."
                class="w-full pl-11 pr-4 py-3 rounded-2xl bg-white border border-pink-100 text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:border-pink-400 focus:ring-2 focus:ring-pink-200 transition-all"
              />
            </div>

            <div class="flex items-center gap-2">
              <button
                v-for="t in typeOptions"
                :key="t.value"
                @click="setType(t.value)"
                class="px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97]"
                :class="activeType === t.value
                  ? 'bg-gradient-to-r from-pink-500 to-pink-400 text-white shadow-lg shadow-pink-200'
                  : 'bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500'"
              >
                {{ t.label }}
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-pink-200 to-transparent" />
    </div>

    <!-- 内容 -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div
        v-if="taskFilterActive"
        class="mb-6 flex items-center justify-between gap-3 rounded-2xl bg-white border border-pink-100 px-4 py-3 shadow-sm animate-fade-in"
      >
        <p class="text-sm text-gray-600">
          正在查看任务 <span class="font-semibold text-pink-600">#{{ taskId }}</span> 生成的媒体文件（STRM）
        </p>
        <button
          @click="clearTaskFilter"
          class="text-sm text-gray-500 hover:text-pink-600 transition-colors flex items-center gap-1"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          清除筛选
        </button>
      </div>

      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        <div v-for="n in 12" :key="n" class="animate-pulse">
          <div class="aspect-[2/3] rounded-2xl bg-pink-100/70" />
          <div class="h-3 mt-3 rounded bg-pink-100/60 w-3/4" />
          <div class="h-2 mt-2 rounded bg-pink-50 w-1/2" />
        </div>
      </div>

      <div
        v-else-if="items.length === 0"
        class="py-24 text-center animate-fade-in"
      >
        <div class="w-16 h-16 mx-auto rounded-full bg-pink-50 flex items-center justify-center text-pink-400">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
        </div>
        <h3 class="mt-4 text-lg font-semibold text-gray-700">暂无媒体内容</h3>
        <p class="mt-1 text-sm text-gray-400">运行任务生成 STRM 文件后，媒体将显示在这里</p>
        <button
          @click="loadLibrary"
          class="mt-6 px-5 py-2.5 rounded-xl bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500 transition-all"
        >
          重新加载
        </button>
      </div>

      <div v-else>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
          <MediaCard
            v-for="(media, i) in items"
            :key="media.id"
            :media="media"
            :style="{ animationDelay: `${Math.min(i, 11) * 40}ms` }"
            class="animate-scale-in"
          />
        </div>

        <div v-if="total > pageSize" class="mt-10 flex items-center justify-center gap-3">
          <button
            :disabled="page <= 1"
            @click="changePage(page - 1)"
            class="px-4 py-2.5 rounded-xl bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
          >
            上一页
          </button>
          <span class="text-sm text-gray-500">
            {{ page }} / {{ totalPages }}
          </span>
          <button
            :disabled="page >= totalPages"
            @click="changePage(page + 1)"
            class="px-4 py-2.5 rounded-xl bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
          >
            下一页
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import {
  fetchMediaLibrary
} from '~/modules/media-library/services/mediaLibraryApi'

definePageMeta({
  middleware: 'auth'
})

const keyword = ref('')
const activeType = ref('all')
const page = ref(1)
const pageSize = ref(24)
const loading = ref(true)
const items = ref([])
const total = ref(0)

const route = useRoute()
const taskId = computed(() => (route.query.taskId ? Number(route.query.taskId) : null))
const taskFilterActive = computed(() => taskId.value != null)

const typeOptions = [
  { value: 'all', label: '全部' },
  { value: 'movie', label: '电影' },
  { value: 'tv', label: '剧集' }
]

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)))

const clearTaskFilter = () => {
  navigateTo({ path: '/media-library', query: { ...route.query, taskId: undefined } })
}

let searchTimer = null

const loadLibrary = async () => {
  loading.value = true
  try {
    const mediaType = activeType.value === 'all' ? null : activeType.value
    const result = await fetchMediaLibrary({
      taskId: taskId.value,
      keyword: keyword.value,
      mediaType,
      page: page.value,
      pageSize: pageSize.value
    })
    items.value = result.items || []
    total.value = result.total || 0
  } catch (e) {
    console.error('加载媒体库失败:', e)
    items.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

const setType = (t) => {
  if (activeType.value === t) return
  activeType.value = t
  page.value = 1
  loadLibrary()
}

const changePage = (p) => {
  if (p < 1) return
  page.value = p
  loadLibrary()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(keyword, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadLibrary()
  }, 300)
})

onMounted(loadLibrary)
</script>
