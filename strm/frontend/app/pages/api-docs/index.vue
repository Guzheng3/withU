<template>
  <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="animate-fade-in">
      <!-- 页面标题 -->
      <div class="text-center mb-8">
        <div class="w-14 h-14 bg-gradient-to-br from-sky-500 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <h1 class="text-3xl font-bold gradient-text mb-2">外部媒体库接口文档</h1>
        <p class="text-gray-500">Emby 风格的外部接口，供第三方播放器 / 客户端接入媒体库导航与播放</p>
      </div>

      <div class="space-y-6">
        <!-- 启用方式 -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            启用方式
          </h3>
          <ol class="space-y-2 text-sm text-gray-600 list-decimal list-inside">
            <li>进入「系统设置」→「外部媒体库接口」</li>
            <li>勾选「启用外部媒体库接口」</li>
            <li>填写或点击「生成随机 Key」生成 API Key</li>
            <li>点击「保存设置」</li>
          </ol>
          <p class="mt-3 text-xs text-gray-500">未启用时，所有 <code class="text-sky-700">/api/external/**</code> 接口返回 <code class="text-red-600">401</code>。</p>
        </div>

        <!-- 认证方式 -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            认证方式
          </h3>
          <p class="text-sm text-gray-600 mb-3">所有外部接口都需要携带 API Key，支持两种传法（二选一）：</p>
          <div class="space-y-3">
            <div>
              <p class="text-xs font-medium text-gray-500 mb-1">请求头方式</p>
              <pre class="bg-gray-900 text-gray-100 text-sm rounded-xl p-4 overflow-x-auto"><code>X-API-Key: &lt;your-api-key&gt;</code></pre>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500 mb-1">查询参数方式</p>
              <pre class="bg-gray-900 text-gray-100 text-sm rounded-xl p-4 overflow-x-auto"><code>GET /api/external/info?apiKey=&lt;your-api-key&gt;</code></pre>
            </div>
          </div>
        </div>

        <!-- 基础地址 -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10" />
            </svg>
            基础地址
          </h3>
          <pre class="bg-gray-900 text-gray-100 text-sm rounded-xl p-4 overflow-x-auto"><code>http://&lt;host&gt;:&lt;port&gt;/api/external</code></pre>
        </div>

        <!-- 接口列表 -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
            </svg>
            接口列表
          </h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-gray-600">
              <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                  <th class="py-2 pr-4 font-medium">方法</th>
                  <th class="py-2 pr-4 font-medium">路径</th>
                  <th class="py-2 font-medium">用途</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ep in endpoints" :key="ep.path" class="border-b border-gray-50">
                  <td class="py-2.5 pr-4"><span :class="methodBadge(ep.method)">{{ ep.method }}</span></td>
                  <td class="py-2.5 pr-4 font-mono text-xs">{{ ep.path }}</td>
                  <td class="py-2.5 text-gray-500">{{ ep.desc }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 错误码 -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            错误码
          </h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-gray-600">
              <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                  <th class="py-2 pr-4 font-medium">HTTP 状态</th>
                  <th class="py-2 font-medium">说明</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in errorCodes" :key="e.code" class="border-b border-gray-50">
                  <td class="py-2.5 pr-4"><code class="text-red-600">{{ e.code }}</code></td>
                  <td class="py-2.5 text-gray-500">{{ e.desc }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

const endpoints = [
  { method: 'GET', path: '/api/external/info', desc: '服务信息（名称、版本、支持类型）' },
  { method: 'GET', path: '/api/external/health', desc: '健康检查' },
  { method: 'GET', path: '/api/external/media', desc: '分页查询媒体列表' },
  { method: 'GET', path: '/api/external/media/{id}', desc: '获取媒体详情（含剧集列表）' },
  { method: 'GET', path: '/api/external/counts', desc: '媒体类型计数' },
  { method: 'GET', path: '/api/external/stream/{id}', desc: '解析媒体播放地址（302 重定向）' },
  { method: 'GET', path: '/api/external/episode/{episodeId}/stream', desc: '解析剧集播放地址（302 重定向）' }
]

const errorCodes = [
  { code: 401, desc: '接口未启用 / API Key 缺失或不匹配' },
  { code: 404, desc: '媒体 ID 不存在' },
  { code: 502, desc: 'OpenList 配置不存在、已停用或播放地址解析失败' }
]

const methodBadge = (method) => {
  return method === 'GET'
    ? 'inline-block px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-xs font-semibold'
    : 'inline-block px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-xs font-semibold'
}
</script>

<style scoped>
.gradient-text {
  background: linear-gradient(to right, #0EA5E9, #6366F1, #8B5CF6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
</style>
