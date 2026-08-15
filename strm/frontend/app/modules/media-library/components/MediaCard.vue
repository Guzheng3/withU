<template>
  <NuxtLink
    :to="`/media-library/${media.id}`"
    class="media-card group block rounded-2xl overflow-hidden bg-white border border-pink-100 shadow-sm hover:shadow-xl hover:shadow-pink-100 hover:-translate-y-1.5 transition-all duration-300"
  >
    <div class="relative aspect-[2/3] w-full overflow-hidden bg-pink-50">
      <img
        v-if="media.posterUrl"
        :src="media.posterUrl"
        :alt="media.title"
        loading="lazy"
        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
      />
      <div
        v-else
        class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-pink-100 via-sky-50 to-emerald-50 text-pink-300 gap-2"
      >
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <span class="text-xs px-3 text-center line-clamp-2 text-gray-500">{{ media.title }}</span>
      </div>

      <!-- 悬停遮罩与播放按钮 -->
      <div
        class="absolute inset-0 bg-gradient-to-t from-pink-900/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"
      />
      <div
        class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300"
      >
        <div class="w-14 h-14 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-lg scale-75 group-hover:scale-100 transition-transform duration-300">
          <svg class="w-7 h-7 text-pink-500 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
      </div>

      <div
        v-if="media.voteAverage"
        class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-white/85 backdrop-blur-sm text-amber-600 text-xs font-bold shadow-sm flex items-center gap-1"
      >
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
        {{ media.voteAverage.toFixed(1) }}
      </div>

      <span
        class="absolute bottom-2 left-2 px-2 py-0.5 rounded-md bg-white/85 backdrop-blur-sm text-pink-600 text-[10px] font-bold uppercase tracking-wider shadow-sm"
      >
        {{ media.mediaType === 'tv' ? '剧集' : '电影' }}
      </span>
    </div>

    <div class="p-3">
      <h3 class="text-sm font-semibold text-gray-800 line-clamp-1 leading-snug group-hover:text-pink-600 transition-colors">
        {{ media.title }}
      </h3>
      <p class="mt-0.5 text-xs text-gray-400 flex items-center gap-1.5">
        <span v-if="media.releaseYear">{{ media.releaseYear }}</span>
        <span v-if="media.episodeCount && media.episodeCount > 1" class="px-1.5 py-0.5 rounded bg-pink-50 text-pink-600 text-[10px] font-semibold">
          {{ media.episodeCount }} 集
        </span>
        <span v-if="media.originalTitle && media.originalTitle !== media.title" class="truncate">
          {{ media.originalTitle }}
        </span>
      </p>
    </div>
  </NuxtLink>
</template>

<script setup>
const props = defineProps({
  media: {
    type: Object,
    required: true
  }
})
</script>
