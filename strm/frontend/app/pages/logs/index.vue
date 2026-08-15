<!--
  withUstrm - Stream Management System
  Copyright (C) 2024 withUstrm Project

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <https://www.gnu.org/licenses/>.
-->

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
          <p class="text-xs font-semibold text-pink-500 uppercase tracking-[0.25em] mb-2">System Logs</p>
          <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-gray-800">系统日志</h1>
          <p class="mt-2 text-gray-500 max-w-2xl">查看后端与前端运行日志，排查问题</p>

          <div class="mt-6 flex flex-col md:flex-row md:items-center gap-3 max-w-3xl">
            <div class="flex items-center gap-2 rounded-2xl bg-white border border-pink-100 p-1.5 shadow-sm">
              <button
                v-for="t in typeOptions"
                :key="t.value"
                @click="switchType(t.value)"
                class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97]"
                :class="activeType === t.value
                  ? 'bg-gradient-to-r from-pink-500 to-pink-400 text-white shadow-md shadow-pink-200'
                  : 'text-gray-600 hover:bg-pink-50 hover:text-pink-500'"
              >
                {{ t.label }}
              </button>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="loadLogs(true)"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-blue-400 text-white text-sm font-medium shadow-md shadow-blue-200 hover:opacity-95 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>重新加载</span>
              </button>

              <button
                @click="downloadLog"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white text-gray-600 border border-pink-100 hover:bg-pink-50 hover:text-pink-500 text-sm font-medium transition-all"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>下载</span>
              </button>

              <button
                @click="confirmDelete"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white text-red-500 border border-red-100 hover:bg-red-50 text-sm font-medium transition-all"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span>删除日志</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-pink-200 to-transparent" />
    </div>

    <!-- 内容 -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- 统计卡片 -->
      <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-white border border-pink-100 px-4 py-4 shadow-sm">
          <p class="text-xs text-gray-400">总行数</p>
          <p class="mt-1 text-2xl font-bold text-gray-800">{{ stats.totalLines ?? 0 }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-red-100 px-4 py-4 shadow-sm">
          <p class="text-xs text-red-400">错误</p>
          <p class="mt-1 text-2xl font-bold text-red-500">{{ stats.errorCount ?? 0 }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-amber-100 px-4 py-4 shadow-sm">
          <p class="text-xs text-amber-500">警告</p>
          <p class="mt-1 text-2xl font-bold text-amber-500">{{ stats.warnCount ?? 0 }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-pink-100 px-4 py-4 shadow-sm">
          <p class="text-xs text-gray-400">文件大小</p>
          <p class="mt-1 text-xl font-bold text-gray-700 truncate">{{ formatSize(stats.fileSize) }}</p>
        </div>
      </div>

      <!-- 日志内容 -->
      <div class="rounded-2xl bg-white border border-pink-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-pink-50 bg-gradient-to-r from-pink-50/60 to-blue-50/60">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400" />
            <span class="text-sm font-medium text-gray-600">{{ activeLabel }}</span>
            <span class="text-xs text-gray-400">最后更新 {{ lastUpdated }}</span>
          </div>
          <span class="text-xs text-gray-400 px-3 py-1 rounded-lg bg-white/70">共 {{ lines.length }} 行</span>
        </div>

        <div v-if="loading" class="p-6 space-y-2">
          <div v-for="n in 8" :key="n" class="h-4 rounded bg-pink-50 animate-pulse" :style="{ width: `${60 + (n * 7) % 30}%` }" />
        </div>

        <div v-else-if="lines.length === 0" class="py-16 text-center">
          <div class="w-14 h-14 mx-auto rounded-full bg-pink-50 flex items-center justify-center text-pink-300">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p class="mt-4 text-gray-500">暂无日志内容</p>
        </div>

        <div v-else class="max-h-[62vh] overflow-auto bg-gray-950">
          <pre class="px-5 py-4 text-xs leading-relaxed font-mono text-gray-100 whitespace-pre-wrap break-all"><template v-for="(line, i) in lines" :key="i"><span :class="lineClass(line)">{{ line }}</span>
</template></pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiCall } from '~/core/utils/api'

definePageMeta({
  middleware: 'auth'
})

const typeOptions = [
  { value: 'backend', label: '后端日志' },
  { value: 'frontend', label: '前端日志' }
]

const activeType = ref('backend')
const lines = ref([])
const stats = ref(null)
const loading = ref(false)
const lastUpdated = ref('—')
const showDeleteConfirm = ref(false)

const activeLabel = computed(() => typeOptions.find(t => t.value === activeType.value)?.label || activeType.value)

const lineClass = (line) => {
  const l = line.toLowerCase()
  if (l.includes('error') || l.includes('exception') || l.includes(' 严重')) return 'text-red-400'
  if (l.includes('warn')) return 'text-amber-400'
  return 'text-gray-200'
}

const formatSize = (bytes) => {
  if (bytes == null) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`
}

const loadLogs = async (showSpinner = false) => {
  if (showSpinner) loading.value = true
  try {
    const [logRes, statsRes] = await Promise.all([
      apiCall(`/logs/${activeType.value}?lines=1000`),
      apiCall(`/logs/${activeType.value}/stats`)
    ])
    lines.value = logRes?.data || []
    stats.value = statsRes?.data || null
    lastUpdated.value = new Date().toLocaleTimeString('zh-CN', { hour12: false })
  } catch (e) {
    console.error('加载日志失败:', e)
    lines.value = []
    stats.value = null
  } finally {
    loading.value = false
  }
}

const switchType = (type) => {
  if (activeType.value === type) return
  activeType.value = type
  lines.value = []
  stats.value = null
  loadLogs(true)
}

const downloadLog = () => {
  const url = `/api/logs/${activeType.value}/download`
  window.location.href = url
}

const confirmDelete = async () => {
  if (!window.confirm('确定要清空当前日志文件吗？此操作不可恢复。')) return
  try {
    await apiCall(`/logs/${activeType.value}`, { method: 'DELETE' })
    lines.value = []
    stats.value = null
    lastUpdated.value = new Date().toLocaleTimeString('zh-CN', { hour12: false })
  } catch (e) {
    console.error('删除日志失败:', e)
  }
}

onMounted(() => loadLogs(true))
</script>
