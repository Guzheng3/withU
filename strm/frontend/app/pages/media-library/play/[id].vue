<template>
  <div class="min-h-screen px-4 sm:px-6 py-6">
    <div class="max-w-6xl mx-auto">
      <div class="flex items-center justify-between mb-5">
        <button
          @click="router.push(`/media-library/${mediaId}`)"
          class="inline-flex items-center gap-2 text-gray-500 hover:text-pink-500 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          返回
        </button>
        <h1 class="text-lg font-semibold truncate text-gray-800">{{ media?.title || '正在加载...' }}</h1>
      </div>

      <div v-if="loading" class="animate-pulse aspect-video rounded-2xl bg-pink-100/70 flex items-center justify-center">
        <p class="text-gray-500">正在解析播放地址...</p>
      </div>

      <div v-else-if="error" class="rounded-2xl bg-white border border-pink-100 p-10 text-center shadow-sm">
        <div class="w-14 h-14 mx-auto rounded-full bg-pink-50 flex items-center justify-center text-pink-400">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <p class="mt-4 text-gray-600">{{ error }}</p>
        <button
          @click="initPlayback"
          class="mt-6 px-5 py-2.5 rounded-xl bg-gradient-to-r from-pink-500 to-pink-400 hover:from-pink-400 hover:to-pink-300 text-white font-semibold shadow-lg shadow-pink-200 transition-all active:scale-95"
        >
          重试
        </button>
      </div>

      <ClientOnly v-else>
        <div class="rounded-2xl overflow-hidden ring-1 ring-pink-100 shadow-xl shadow-pink-100/60 bg-black">
          <ArtPlayerPlayer v-if="playbackUrl" :url="playbackUrl" :title="media?.title" />
        </div>
        <template #fallback>
          <div class="animate-pulse aspect-video rounded-2xl bg-pink-100/70" />
        </template>
      </ClientOnly>

      <div v-if="media && !error" class="mt-6 text-sm text-gray-600 leading-relaxed animate-slide-up">
        <p>{{ media.overview || '暂无简介' }}</p>
      </div>
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

const mediaId = Number(route.params.id)
const episodeId = route.query.episodeId ? Number(route.query.episodeId) : null
const sourceId = route.query.sourceId ? Number(route.query.sourceId) : null
const media = ref(null)
const playbackUrl = ref('')
const loading = ref(true)
const error = ref('')

const initPlayback = async () => {
  loading.value = true
  error.value = ''
  playbackUrl.value = ''
  try {
    const detail = await fetchMediaDetail(mediaId)
    media.value = detail
    if (episodeId) {
      const ep = detail.episodes?.find((e) => e.id === episodeId)
      if (ep) {
        const playback = await fetchPlaybackUrl(ep.id, sourceId)
        playbackUrl.value = playback.url
      } else {
        throw new Error('未找到选集')
      }
    } else {
      const playback = await fetchPlaybackUrl(mediaId, sourceId)
      playbackUrl.value = playback.url
    }
  } catch (e) {
    error.value = e.message || '解析播放地址失败'
  } finally {
    loading.value = false
  }
}

onMounted(initPlayback)
</script>
