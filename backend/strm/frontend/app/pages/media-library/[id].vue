<template>
  <div class="relative min-h-screen overflow-x-hidden" v-if="media">
    <!-- 背景：整页海报背景（fixed 铺满视口，不随滚动拉伸） -->
    <div class="fixed inset-0 z-0 overflow-hidden">
      <img
        v-if="media.backdropUrl"
        :src="media.backdropUrl"
        :alt="media.title"
        class="w-full h-full object-cover object-top"
      />
      <div
        v-else
        class="w-full h-full bg-gradient-to-br from-pink-100 via-sky-50 to-emerald-50"
      />
      <!-- 柔和氛围光晕 -->
      <div
        class="absolute inset-0 pointer-events-none"
        style="background-image: radial-gradient(circle at 12% 20%, rgba(236,72,153,.14), transparent 45%), radial-gradient(circle at 88% 12%, rgba(56,189,248,.16), transparent 45%), radial-gradient(circle at 50% 96%, rgba(244,114,182,.10), transparent 40%)"
      />
      <!-- 顶部压暗，保证标题可读 -->
      <div class="absolute inset-x-0 top-0 h-64 md:h-80 bg-gradient-to-b from-black/45 via-black/15 to-transparent" />
      <!-- 底部白色渐变，承接滚动后的内容区 -->
      <div class="absolute inset-x-0 bottom-0 h-[34rem] bg-gradient-to-t from-white via-white/85 to-transparent" />
    </div>

    <!-- 内容：正常排版，叠在海报背景上 -->
    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 md:pt-24 pb-16">
      <div class="flex flex-col md:flex-row gap-8">
        <div class="relative w-44 md:w-56 shrink-0 mx-auto md:mx-0 animate-scale-in group">
          <div class="absolute -inset-2 md:-inset-3 rounded-3xl bg-gradient-to-br from-pink-200/50 via-sky-100/40 to-sky-200/50 blur-xl opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
          <div class="relative aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl shadow-pink-200/70 ring-1 ring-white/70 bg-white">
            <img
              v-if="media.posterUrl"
              :src="media.posterUrl"
              :alt="media.title"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-pink-100 to-sky-50">
              <svg class="w-16 h-16 text-pink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>

        <div class="flex-1 text-center md:text-left animate-slide-up">
          <div class="flex items-center gap-2 justify-center md:justify-start">
            <span class="px-2.5 py-0.5 rounded-md bg-white/15 text-white text-xs font-semibold uppercase tracking-wider ring-1 ring-white/30 backdrop-blur-sm">
              {{ media.mediaType === 'tv' ? '剧集' : '电影' }}
            </span>
            <span v-if="media.releaseYear" class="text-white/80 text-sm">{{ media.releaseYear }}</span>
          </div>

          <h1 class="mt-3 text-3xl md:text-5xl font-bold tracking-tight text-white drop-shadow-md">{{ media.title }}</h1>
          <p
            v-if="media.originalTitle && media.originalTitle !== media.title"
            class="mt-1 text-white/70 text-sm md:text-base"
          >
            {{ media.originalTitle }}
          </p>

          <div class="mt-4 flex flex-wrap items-center gap-4 justify-center md:justify-start">
            <span v-if="media.voteAverage" class="flex items-center gap-1.5 text-amber-400">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <span class="font-semibold">{{ media.voteAverage.toFixed(1) }}</span>
            </span>

            <button
              @click="goPlay"
              class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-pink-500 to-pink-400 hover:from-pink-400 hover:to-pink-300 text-white font-semibold shadow-lg shadow-pink-200 active:scale-95 transition-all duration-200"
            >
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z" />
              </svg>
              立即播放
            </button>
          </div>
        </div>
      </div>

      <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-8 pb-16">
        <div class="md:col-span-2 space-y-8">
          <div class="rounded-3xl bg-white/70 backdrop-blur-md border border-white/60 p-5 md:p-8 shadow-sm space-y-8">
            <section v-if="media.overview" class="animate-slide-up">
              <h2 class="text-lg font-semibold text-gray-800 mb-2">简介</h2>
              <p class="text-gray-600 leading-relaxed">{{ media.overview }}</p>
            </section>
            <section v-if="media.episodes && media.episodes.length" class="animate-slide-up">
              <h2 class="text-lg font-semibold text-gray-800 mb-3">
                选集
                <span v-if="media.mediaType === 'movie'" class="text-sm font-normal text-gray-400">（正片）</span>
                <span v-else-if="media.totalEpisodes" class="text-sm font-normal text-gray-400">（共 {{ media.episodes.length }}/{{ media.totalEpisodes }} 集）</span>
                <span v-else class="text-sm font-normal text-gray-400">（共 {{ media.episodes.length }} 集）</span>
              </h2>
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="ep in media.episodes"
                  :key="ep.id"
                  class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-medium transition-all duration-200 active:scale-95"
                  :class="activeEpisodeId === ep.id
                    ? 'bg-gradient-to-r from-pink-500 to-pink-400 text-white shadow-lg shadow-pink-200'
                    : 'bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500'"
                  :title="ep.sourceFileName"
                >
                  <button @click="goPlayEpisode(ep)" class="focus:outline-none">
                    {{ media.mediaType === 'movie' ? '正片' : `第 ${ep.episodeNo} 集` }}
                  </button>
                  <span
                    v-if="ep.sources && ep.sources.length > 1"
                    class="text-[10px] px-1.5 py-0.5 rounded-md"
                    :class="activeEpisodeId === ep.id ? 'bg-white/20 text-white' : 'bg-pink-100 text-pink-600'"
                  >
                    {{ ep.sources.length }}源
                  </span>
                  <select
                    v-if="ep.sources && ep.sources.length > 1"
                    v-model="selectedSource[ep.episodeNo]"
                    @change="goPlaySource(ep)"
                    class="text-xs rounded-md px-1 py-0.5 border border-pink-200 bg-transparent focus:outline-none cursor-pointer"
                    :class="activeEpisodeId === ep.id ? 'text-white border-white/30' : 'text-gray-500'"
                  >
                    <option
                      v-for="src in ep.sources"
                      :key="src.id"
                      :value="src.id"
                    >
                      {{ src.resolution || '未知' }}
                    </option>
                  </select>
                </div>
              </div>
            </section>
            <section v-if="media.tmdbId" class="animate-slide-up">
              <h2 class="text-lg font-semibold text-gray-800 mb-3">演职员</h2>
              <div v-if="creditsLoading" class="space-y-2">
                <div v-for="n in 4" :key="n" class="animate-pulse h-10 rounded-lg bg-pink-100/70" />
              </div>
              <div v-else-if="cast.length" class="flex gap-3 overflow-x-auto pb-2">
                <div
                  v-for="person in cast"
                  :key="person.id"
                  class="w-24 shrink-0 text-center"
                >
                  <div class="w-24 h-32 rounded-xl overflow-hidden bg-pink-50 ring-1 ring-pink-100">
                    <img
                      v-if="person.profileUrl"
                      :src="person.profileUrl"
                      :alt="person.name"
                      class="w-full h-full object-cover"
                      loading="lazy"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-pink-300">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                  </div>
                  <p class="mt-2 text-xs text-gray-600 line-clamp-1">{{ person.name }}</p>
                  <p class="text-[10px] text-gray-400 line-clamp-1">{{ person.character }}</p>
                </div>
              </div>
              <p v-else class="text-sm text-gray-400">暂无演职员信息</p>
            </section>
          </div>
        </div>

        <aside class="space-y-4 text-sm">
          <div class="rounded-2xl bg-white border border-pink-100 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-pink-500 uppercase tracking-wider mb-3">来源信息</h3>
            <dl class="space-y-2.5">
              <div>
                <dt class="text-gray-400">文件名</dt>
                <dd class="text-gray-700 break-all mt-0.5">{{ media.sourceFileName }}</dd>
              </div>
              <div>
                <dt class="text-gray-400">源路径</dt>
                <dd class="text-gray-500 break-all mt-0.5 font-mono text-xs">{{ media.sourcePath }}</dd>
              </div>
              <div v-if="media.tmdbId">
                <dt class="text-gray-400">TMDB ID</dt>
                <dd class="text-gray-700 mt-0.5">{{ media.tmdbId }}</dd>
              </div>
            </dl>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <div v-else-if="error" class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="text-center animate-fade-in">
      <div class="w-14 h-14 mx-auto rounded-full bg-pink-50 flex items-center justify-center text-pink-400">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="mt-4 text-gray-500">{{ error }}</p>
      <button
        @click="router.back()"
        class="mt-6 px-5 py-2.5 rounded-xl bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500 transition-all"
      >
        返回媒体库
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchMediaDetail,
  fetchPlaybackUrl
} from '~/modules/media-library/services/mediaLibraryApi'

definePageMeta({
  middleware: 'auth'
})

const route = useRoute()
const router = useRouter()

const media = ref(null)
const error = ref('')
const creditsLoading = ref(false)
const cast = ref([])
const activeEpisodeId = ref(null)
const selectedSource = ref({})
const fromWithU = route.query.from === 'withu'

const openWithUPlayer = (episodeId = 0) => {
  const query = episodeId ? `&episode=${episodeId}` : ''
  window.location.href = `/watch_play.php?source=strm&id=${route.params.id}${query}`
}

const goPlayEpisode = (ep) => {
  activeEpisodeId.value = ep.id
  if (fromWithU) {
    openWithUPlayer(ep.id)
    return
  }
  // 未手动选源时走默认优先级（后端 4K 优先）；已选择则用所选来源
  const srcId = selectedSource.value[ep.episodeNo] || (ep.sources && ep.sources.length ? ep.sources[0].id : null)
  router.push(
    `/media-library/play/${route.params.id}?episodeId=${ep.id}${srcId ? `&sourceId=${srcId}` : ''}`
  )
}

const goPlaySource = (ep) => {
  const srcId = selectedSource.value[ep.episodeNo]
  activeEpisodeId.value = ep.id
  if (fromWithU) {
    openWithUPlayer(ep.id)
    return
  }
  router.push(
    `/media-library/play/${route.params.id}?episodeId=${ep.id}${srcId ? `&sourceId=${srcId}` : ''}`
  )
}

const load = async () => {
  try {
    media.value = await fetchMediaDetail(Number(route.params.id))
    // 每集默认选中最高分辨率来源（sources 已按分辨率降序）
    media.value.episodes?.forEach((ep) => {
      if (ep.sources && ep.sources.length) {
        selectedSource.value[ep.episodeNo] = ep.sources[0].id
      }
    })
    if (media.value.tmdbId) {
      loadCredits()
    }
  } catch (e) {
    error.value = e.message || '加载失败'
  }
}

const loadCredits = async () => {
  creditsLoading.value = true
  try {
    const { authenticatedApiCall } = await import('~/core/api/client')
    const res = await authenticatedApiCall(
      `/test/tmdb/detail?type=${media.value.mediaType === 'tv' ? 'tv' : 'movie'}&id=${media.value.tmdbId}`,
      { method: 'GET' }
    )
    if (res.code === 200 && res.data && res.data.cast) {
      cast.value = res.data.cast.slice(0, 10).map((p) => ({
        id: p.id,
        name: p.name,
        character: p.character,
        profileUrl: p.profileUrl || null
      }))
    }
  } catch (e) {
    // 演职员加载失败不阻塞页面
    cast.value = []
  } finally {
    creditsLoading.value = false
  }
}

const goPlay = async () => {
  try {
    if (media.value?.episodes?.length) {
      const first = media.value.episodes[0]
      activeEpisodeId.value = first.id
      if (fromWithU) {
        openWithUPlayer(first.id)
        return
      }
      router.push(`/media-library/play/${route.params.id}?episodeId=${first.id}`)
    } else {
      if (fromWithU) {
        openWithUPlayer()
        return
      }
      router.push(`/media-library/play/${route.params.id}`)
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(load)
</script>
