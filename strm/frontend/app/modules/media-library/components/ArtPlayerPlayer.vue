<template>
  <div ref="container" class="artplayer-container" />
</template>

<script setup>
import { ref, shallowRef, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  url: {
    type: String,
    required: true
  },
  title: {
    type: String,
    default: ''
  }
})

const container = ref(null)
const art = shallowRef(null)

const initPlayer = async () => {
  const { default: Artplayer } = await import('artplayer')
  const isIOS =
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  const nativeFsEnabled =
    typeof document.fullscreenEnabled === 'undefined'
      ? true
      : document.fullscreenEnabled
  art.value = new Artplayer({
    container: container.value,
    url: props.url,
    title: props.title,
    theme: '#ec4899',
    lang: 'zh-cn',
    volume: 0.8,
    autoplay: true,
    autoMini: true,
    playbackRate: true,
    aspectRatio: true,
    screenshot: true,
    setting: true,
    hotkey: true,
    pip: true,
    mutex: true,
    backdrop: true,
    fullscreen: nativeFsEnabled || isIOS,
    fullscreenWeb: true,
    miniProgressBar: true,
    playsInline: true,
    lock: true,
    gesture: true,
    fastForward: true,
    autoPlayback: true,
    autoOrientation: true,
    airplay: true
  })
  art.value.on('fullscreenError', () => {
    if (!art.value.fullscreen && !art.value.fullscreenWeb) {
      art.value.fullscreenWeb = true
    }
  })
}

onMounted(initPlayer)

onBeforeUnmount(() => {
  if (art.value) {
    art.value.destroy(false)
    art.value = null
  }
})
</script>

<style scoped>
.artplayer-container {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #000;
  border-radius: 1rem;
  overflow: hidden;
}
</style>
