<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="text-center">
      <h1 class="text-6xl font-bold text-gray-900 mb-4">{{ error?.statusCode || 500 }}</h1>
      <p class="text-xl text-gray-600 mb-8">{{ error?.message || '服务器错误' }}</p>
      <button
        @click="handleError"
        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        返回首页
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  error: {
    statusCode: number
    message: string
  }
}>()

const handleError = () => {
  clearError({ redirect: '/' })
}
</script>
