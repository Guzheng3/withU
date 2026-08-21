<template>
  <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="animate-fade-in">
      <!-- 页面标题 -->
      <div class="text-center mb-8">
        <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </div>
        <h1 class="text-3xl font-bold gradient-text mb-2">API 测试</h1>
        <p class="text-gray-500">测试 TMDB / 豆瓣元数据接口，查看原始 JSON 与可视化信息</p>
      </div>

      <!-- 选项卡 -->
      <div class="flex gap-2 mb-6">
        <button
          type="button"
          @click="tab = 'tmdb'"
          class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
          :class="tab === 'tmdb' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
        >
          TMDB
        </button>
        <button
          type="button"
          @click="tab = 'douban'"
          class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
          :class="tab === 'douban' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
        >
          豆瓣
        </button>
      </div>

      <!-- TMDB 测试 -->
      <template v-if="tab === 'tmdb'">
      <!-- 搜索表单 -->
      <div class="card mb-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm text-gray-700 mb-2">媒体类型</label>
            <div class="flex gap-2">
              <button
                type="button"
                @click="type = 'movie'"
                class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                :class="type === 'movie' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
              >
                电影 (Movie)
              </button>
              <button
                type="button"
                @click="type = 'tv'"
                class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                :class="type === 'tv' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
              >
                电视剧 (TV)
              </button>
              <button
                type="button"
                @click="type = 'auto'"
                class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                :class="type === 'auto' ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
              >
                自动 (Auto)
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-2">剧名 / 搜索关键词</label>
            <div class="flex gap-2">
              <input
                v-model="query"
                type="text"
                class="input-field flex-1"
                placeholder="例如：盗梦空间、Breaking Bad"
                @keyup.enter="doSearch"
              />
              <button
                type="button"
                @click="doSearch"
                :disabled="loading"
                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-sm font-medium hover:opacity-90 disabled:opacity-50 transition-all shadow-lg shadow-indigo-200"
              >
                {{ loading ? '搜索中...' : '搜索' }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-2">TMDB 编号（直接按编号查询）</label>
            <div class="flex gap-2">
              <input
                v-model="tmdbId"
                type="number"
                class="input-field flex-1"
                placeholder="例如：27205（盗梦空间）、1396（绝命毒师）"
                @keyup.enter="loadDetailByInput"
              />
              <button
                type="button"
                @click="loadDetailByInput"
                :disabled="loading"
                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-teal-500 to-emerald-500 text-white text-sm font-medium hover:opacity-90 disabled:opacity-50 transition-all shadow-lg shadow-teal-200"
              >
                查询编号
              </button>
            </div>
          </div>

          <div v-if="error" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm">
            {{ error }}
          </div>
        </div>
      </div>

      <!-- 搜索结果 -->
      <div v-if="searchResults && searchResults.length > 0 && !detail" class="card mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">搜索结果（{{ searchResults.length }}）</h3>
          <span class="text-xs text-gray-400">点击卡片查看详情</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in searchResults"
            :key="item.id"
            class="flex gap-3 p-3 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-indigo-300 hover:shadow-md transition-all"
            @click="loadDetail(item.id, item.mediaType || item.searchType)"
          >
            <div class="w-16 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
              <img v-if="item.posterUrl" :src="item.posterUrl" :alt="item.title" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
              </div>
            </div>
            <div class="min-w-0">
              <div class="font-medium text-gray-800 truncate">{{ item.title }}</div>
              <div class="text-xs text-gray-500 mt-0.5 truncate">{{ item.originalTitle || '' }}</div>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs text-gray-400">{{ item.year || '未知年份' }}</span>
                <span class="inline-flex items-center gap-0.5 text-xs text-amber-600">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                  </svg>
                  {{ item.voteAverage?.toFixed(1) }}
                </span>
              </div>
              <span class="inline-flex mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium"
                :class="(item.mediaType || item.searchType) === 'tv' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700'">
                {{ (item.mediaType || item.searchType) === 'tv' ? '电视剧' : '电影' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 详情展示 -->
      <div v-if="detail" class="space-y-6">
        <!-- 可视化信息 -->
        <div class="card">
          <div class="flex items-start gap-5">
            <!-- 海报 -->
            <div class="w-44 flex-shrink-0 rounded-xl overflow-hidden bg-gray-100 shadow-lg">
              <img v-if="detail.posterUrl" :src="detail.posterUrl" :alt="detail.title" class="w-full object-cover" />
              <div v-else class="w-full aspect-[2/3] flex items-center justify-center text-gray-300">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
              </div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-2xl font-bold text-gray-900">{{ detail.title }}</h2>
                <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-medium"
                  :class="detail.type === 'tv' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700'">
                  {{ detail.type === 'tv' ? '电视剧' : '电影' }}
                </span>
              </div>
              <div class="text-sm text-gray-500 mt-1">{{ detail.originalTitle }} · {{ detail.year }} · TMDB ID: {{ detail.id }}</div>

              <div class="flex flex-wrap gap-4 mt-3 text-sm">
                <div class="flex items-center gap-1 text-amber-600 font-medium">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                  </svg>
                  {{ detail.voteAverage?.toFixed(1) }}
                  <span class="text-gray-400 font-normal">({{ detail.voteCount }} 票)</span>
                </div>
                <span v-if="detail.genres" class="text-gray-600">{{ detail.genres }}</span>
                <span v-if="detail.status" class="text-gray-500">{{ detail.status }}</span>
                <span v-if="detail.runtime" class="text-gray-500">{{ formatRuntime(detail.runtime) }}</span>
                <span v-if="detail.numberOfSeasons" class="text-gray-500">{{ detail.numberOfSeasons }} 季 / {{ detail.numberOfEpisodes }} 集</span>
              </div>

              <p v-if="detail.tagline" class="text-gray-400 italic mt-2 text-sm">{{ detail.tagline }}</p>
              <p class="text-gray-600 mt-2 text-sm leading-relaxed">{{ detail.overview }}</p>
            </div>
          </div>

          <!-- 操作 -->
          <div class="flex gap-2 mt-5 border-t border-gray-100 pt-4">
            <button type="button" @click="backToList" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-600 text-sm hover:bg-gray-50 transition-colors">
              返回搜索结果
            </button>
            <button type="button" @click="toggleRaw" class="px-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-gray-600 text-sm hover:bg-gray-100 transition-colors">
              {{ showRaw ? '隐藏原始 JSON' : '查看原始 JSON' }}
            </button>
          </div>

          <!-- 原始 JSON -->
          <div v-if="showRaw" class="mt-4">
            <div class="rounded-xl overflow-hidden border border-gray-700 bg-gray-900">
              <div class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700">
                <span class="text-xs text-gray-400 font-mono">原始 JSON 响应</span>
                <button type="button" @click="copyRaw" class="text-xs text-gray-400 hover:text-white transition-colors">
                  复制
                </button>
              </div>
              <pre class="p-4 text-xs text-green-400 font-mono overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap break-words">{{ rawJsonText }}</pre>
            </div>
          </div>
        </div>

        <!-- 演员列表 -->
        <div v-if="cast.length > 0" class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            演员表（{{ cast.length }}）
          </h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <div v-for="actor in cast" :key="actor.id" class="flex flex-col items-center text-center p-3 rounded-xl bg-gray-50 border border-gray-100">
              <div class="w-20 h-20 rounded-full overflow-hidden bg-gray-200 mb-2 ring-2 ring-white shadow">
                <img v-if="actor.profileUrl" :src="actor.profileUrl" :alt="actor.name" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300 text-gray-400">
                  <span class="text-xl font-semibold">{{ (actor.name || '?').charAt(0) }}</span>
                </div>
              </div>
              <div class="font-medium text-gray-800 text-sm leading-tight">{{ actor.name }}</div>
              <div class="text-xs text-gray-500 mt-0.5">饰演</div>
              <div class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ actor.character || '未知角色' }}</div>
            </div>
          </div>
        </div>
      </div>
      </template>

      <!-- 豆瓣测试 -->
      <template v-if="tab === 'douban'">
        <!-- 搜索表单 -->
        <div class="card mb-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-700 mb-2">操作</label>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  @click="doubanAction = 'search'"
                  class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                  :class="doubanAction === 'search' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                >
                  搜索
                </button>
                <button
                  type="button"
                  @click="doubanAction = 'movie-detail'"
                  class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                  :class="doubanAction === 'movie-detail' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                >
                  电影详情
                </button>
                <button
                  type="button"
                  @click="doubanAction = 'tv-detail'"
                  class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors"
                  :class="doubanAction === 'tv-detail' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                >
                  剧集详情
                </button>
              </div>
            </div>

            <div v-if="doubanAction === 'search'">
              <label class="block text-sm text-gray-700 mb-2">关键词</label>
              <div class="flex gap-2">
                <input
                  v-model="doubanQuery"
                  type="text"
                  class="input-field flex-1"
                  placeholder="例如：流浪地球、漫长的季节"
                  @keyup.enter="doDoubanSearch"
                />
                <button
                  type="button"
                  @click="doDoubanSearch"
                  :disabled="doubanLoading"
                  class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-green-500 to-emerald-500 text-white text-sm font-medium hover:opacity-90 disabled:opacity-50 transition-all shadow-lg shadow-green-200"
                >
                  {{ doubanLoading ? '搜索中...' : '搜索' }}
                </button>
              </div>
            </div>

            <div v-else>
              <label class="block text-sm text-gray-700 mb-2">豆瓣 ID</label>
              <div class="flex gap-2">
                <input
                  v-model="doubanId"
                  type="text"
                  class="input-field flex-1"
                  placeholder="例如：26266893"
                  @keyup.enter="doDoubanDetail"
                />
                <button
                  type="button"
                  @click="doDoubanDetail"
                  :disabled="doubanLoading"
                  class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-green-500 to-emerald-500 text-white text-sm font-medium hover:opacity-90 disabled:opacity-50 transition-all shadow-lg shadow-green-200"
                >
                  {{ doubanLoading ? '查询中...' : '查询详情' }}
                </button>
              </div>
            </div>

            <div v-if="doubanError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm">
              {{ doubanError }}
            </div>
          </div>
        </div>

        <!-- 豆瓣搜索结果 -->
        <div v-if="doubanSearchResults.length > 0 && !doubanDetail" class="card mb-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">搜索结果（{{ doubanSearchResults.length }}）</h3>
            <span class="text-xs text-gray-400">点击条目查看详情</span>
          </div>
          <div class="space-y-2">
            <div
              v-for="item in doubanSearchResults"
              :key="item.target.id"
              class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-green-300 hover:shadow-md transition-all"
              @click="loadDoubanDetail(item)"
            >
              <img v-if="doubanProxySrc(item.target.cover_url)" :src="doubanProxySrc(item.target.cover_url)" :alt="item.target.title" class="h-16 w-12 flex-shrink-0 rounded bg-gray-100 object-cover" @error="$event.target.style.visibility='hidden'" />
              <div v-else class="h-16 w-12 flex-shrink-0 rounded bg-gray-100 flex items-center justify-center text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <div class="font-medium text-gray-800 truncate">{{ item.target.title }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ item.type_name }}<template v-if="item.target.year"> · {{ item.target.year }}</template></div>
                <div class="text-xs text-gray-400 truncate">{{ item.target.card_subtitle }}</div>
              </div>
              <span v-if="item.target.rating?.value" class="shrink-0 text-xs text-amber-600">★ {{ item.target.rating.value }}</span>
            </div>
          </div>
        </div>

        <!-- 豆瓣详情展示 -->
        <div v-if="doubanDetail" class="space-y-6">
          <div class="card">
            <div class="flex items-start gap-5">
              <div class="w-44 flex-shrink-0 rounded-xl overflow-hidden bg-gray-100 shadow-lg">
                <img v-if="doubanProxySrc(doubanDetail.poster)" :src="doubanProxySrc(doubanDetail.poster)" :alt="doubanDetail.title" class="w-full object-cover" @error="$event.target.style.visibility='hidden'" />
                <div v-else class="w-full aspect-[2/3] flex items-center justify-center text-gray-300">
                  <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                  <h2 class="text-2xl font-bold text-gray-900">{{ doubanDetail.title }}</h2>
                  <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-medium bg-green-100 text-green-700">
                    {{ doubanDetail.mediaType === 'tv' ? '电视剧' : '电影' }}
                  </span>
                </div>
                <div class="text-sm text-gray-500 mt-1">
                  {{ doubanDetail.originalTitle }}<template v-if="doubanDetail.year"> · {{ doubanDetail.year }}</template><template v-if="doubanDetail.id"> · 豆瓣 ID: {{ doubanDetail.id }}</template>
                </div>
                <div class="flex flex-wrap gap-4 mt-3 text-sm">
                  <div v-if="doubanDetail.voteAverage" class="flex items-center gap-1 text-amber-600 font-medium">
                    ★ {{ doubanDetail.voteAverage }}
                  </div>
                  <span v-if="doubanDetail.genres" class="text-gray-600">{{ doubanDetail.genres }}</span>
                  <span v-if="doubanDetail.countries" class="text-gray-500">{{ doubanDetail.countries }}</span>
                  <span v-if="doubanDetail.durations" class="text-gray-500">{{ doubanDetail.durations }}</span>
                </div>
                <p v-if="doubanDetail.overview" class="text-gray-600 mt-2 text-sm leading-relaxed">{{ doubanDetail.overview }}</p>
              </div>
            </div>
            <div class="flex gap-2 mt-5 border-t border-gray-100 pt-4">
              <button type="button" @click="doubanDetail = null" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-600 text-sm hover:bg-gray-50 transition-colors">
                返回搜索结果
              </button>
              <button type="button" @click="toggleDoubanRaw" class="px-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-gray-600 text-sm hover:bg-gray-100 transition-colors">
                {{ showDoubanRaw ? '隐藏原始 JSON' : '查看原始 JSON' }}
              </button>
            </div>
            <div v-if="showDoubanRaw" class="mt-4">
              <div class="rounded-xl overflow-hidden border border-gray-700 bg-gray-900">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700">
                  <span class="text-xs text-gray-400 font-mono">原始 JSON 响应</span>
                </div>
                <pre class="p-4 text-xs text-green-400 font-mono overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap break-words">{{ doubanRawJson }}</pre>
              </div>
            </div>
          </div>

          <!-- 演员列表 -->
          <div v-if="doubanCast.length > 0" class="card">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">演员表（{{ doubanCast.length }}）</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
              <div v-for="actor in doubanCast" :key="actor.id" class="flex flex-col items-center text-center p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div class="w-20 h-20 rounded-full overflow-hidden bg-gray-200 mb-2 ring-2 ring-white shadow">
                  <img v-if="doubanAvatar(actor)" :src="doubanAvatar(actor)" :alt="actor.name" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300 text-gray-400">
                    <span class="text-xl font-semibold">{{ (actor.name || '?').charAt(0) }}</span>
                  </div>
                </div>
                <div class="font-medium text-gray-800 text-sm leading-tight">{{ actor.name }}</div>
                <div class="text-xs text-gray-500 mt-0.5">饰演</div>
                <div class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ actor.role || '未知角色' }}</div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { getApiBaseUrl } from '~/core/utils/api'

const tab = ref('tmdb')

// 豆瓣测试状态
const doubanAction = ref('search')
const doubanQuery = ref('')
const doubanId = ref('')
const doubanLoading = ref(false)
const doubanError = ref('')
const doubanSearchResults = ref([])
const doubanDetail = ref(null)
const doubanCast = ref([])
const doubanRawJson = ref('')
const showDoubanRaw = ref(false)

const query = ref('')
const tmdbId = ref('')
const type = ref('movie')
const loading = ref(false)
const error = ref('')
const searchResults = ref([])
const detail = ref(null)
const showRaw = ref(false)
const rawJsonText = ref('')

const cast = computed(() => detail.value?.cast || [])

const doSearch = async () => {
  const q = query.value.trim()
  if (!q) {
    error.value = '请输入搜索关键词'
    return
  }
  await runSearch(q)
}

const runSearch = async (q) => {
  loading.value = true
  error.value = ''
  detail.value = null
  try {
    const { apiCall } = await import('~/core/api/client')
    const types = type.value === 'auto' ? ['movie', 'tv'] : [type.value]
    const allResults = []
    let totalResults = 0
    for (const t of types) {
      const res = await apiCall(`/test/tmdb/search?query=${encodeURIComponent(q)}&type=${t}`)
      if (res?.code === 200) {
        const list = res.data?.results || []
        list.forEach((r) => allResults.push({ ...r, searchType: t }))
        totalResults += res.data?.totalResults || 0
      }
    }
    searchResults.value = allResults
    if (searchResults.value.length === 0) {
      error.value = '未找到匹配结果'
    }
  } catch (e) {
    error.value = e?.message || '搜索请求失败'
    searchResults.value = []
  } finally {
    loading.value = false
  }
}

const loadDetailByInput = async () => {
  const id = tmdbId.value.trim()
  if (!id) {
    error.value = '请输入 TMDB 编号'
    return
  }
  await loadDetail(id)
}

const loadDetail = async (id, explicitType = null) => {
  loading.value = true
  error.value = ''
  const useType = explicitType || (type.value === 'auto' ? 'movie' : type.value)
  try {
    const { apiCall } = await import('~/core/api/client')
    const res = await apiCall(`/test/tmdb/detail?type=${useType}&id=${id}`)
    if (res?.code !== 200) {
      error.value = res?.message || '查询详情失败'
      return
    }
    detail.value = res.data
    searchResults.value = []
    showRaw.value = false
    // 原始 JSON：合并详情与演员表原始数据
    rawJsonText.value = JSON.stringify(res.data, null, 2)
  } catch (e) {
    error.value = e?.message || '查询详情失败'
  } finally {
    loading.value = false
  }
}

const backToList = () => {
  detail.value = null
}

const toggleRaw = () => {
  showRaw.value = !showRaw.value
}

const copyRaw = async () => {
  try {
    await navigator.clipboard.writeText(rawJsonText.value)
  } catch (e) {
    // 忽略
  }
}

const formatRuntime = (min) => {
  if (!min) return ''
  const h = Math.floor(min / 60)
  const m = min % 60
  return h > 0 ? `${h}小时${m > 0 ? m + '分钟' : ''}` : `${min}分钟`
}

// ---- 豆瓣 API 测试 ----
const doDoubanSearch = async () => {
  const q = doubanQuery.value.trim()
  if (!q) {
    doubanError.value = '请输入搜索关键词'
    return
  }
  await runDouban({ action: 'search', query: q })
}

const doDoubanDetail = async () => {
  const id = doubanId.value.trim()
  if (!id) {
    doubanError.value = '请输入豆瓣 ID'
    return
  }
  await runDouban({ action: doubanAction.value, id })
}

const loadDoubanDetail = async (item) => {
  const target = item.target || {}
  const isTv = item.type_name === '电视剧'
  await runDouban({ action: isTv ? 'tv-detail' : 'movie-detail', id: target.id })
}

const runDouban = async (params) => {
  doubanLoading.value = true
  doubanError.value = ''
  try {
    const { apiCall } = await import('~/core/api/client')
    const qs = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') qs.set(k, v) })
    const res = await apiCall(`/test/douban?${qs.toString()}`)
    if (res?.code !== 200 || !res.data) {
      doubanError.value = res?.message || '豆瓣请求失败'
      return
    }
    const result = res.data
    if (result.success !== true || result.statusCode < 200 || result.statusCode >= 300) {
      doubanError.value = result.error || `豆瓣返回状态码 ${result.statusCode}`
      return
    }
    const parsed = result.parsed || {}
    if (params.action === 'search') {
      const items = (parsed.items || []).filter((i) => i.type_name === '电影' || i.type_name === '电视剧').slice(0, 20)
      doubanSearchResults.value = items
      doubanDetail.value = null
      doubanCast.value = []
      doubanRawJson.value = result.rawResponse || ''
      if (items.length === 0) doubanError.value = '未找到匹配结果'
    } else {
      doubanDetail.value = mapDoubanDetail(parsed)
      doubanSearchResults.value = []
      doubanRawJson.value = result.rawResponse || ''
      showDoubanRaw.value = false
      await loadDoubanCelebrities(parsed)
    }
  } catch (e) {
    doubanError.value = e?.message || '豆瓣请求失败'
  } finally {
    doubanLoading.value = false
  }
}

const loadDoubanCelebrities = async (parsed) => {
  const isTv = parsed.media_type === 'tv' || parsed.type === 'tv'
  const id = parsed.id
  if (!id) return
  try {
    const { apiCall } = await import('~/core/api/client')
    const action = isTv ? 'tv-celebrities' : 'movie-celebrities'
    const res = await apiCall(`/test/douban?action=${action}&id=${id}`)
    const celebs = res?.data?.parsed
    const actors = (celebs?.actors || []).slice(0, 20)
    doubanCast.value = actors.map((a) => ({
      id: a.id,
      name: a.name || '',
      role: a.role || '未知角色',
      avatar: a.avatars?.large || a.avatar || ''
    }))
  } catch (e) {
    doubanCast.value = []
  }
}

const mapDoubanDetail = (p) => {
  const genres = (p.genres || []).map((g) => (typeof g === 'string' ? g : g.name)).join(' / ')
  const countries = (p.countries || []).join(' / ')
  const durations = (p.durations || []).join(' / ')
  const rating = p.rating && typeof p.rating === 'object' ? p.rating.value : p.rating
  return {
    id: p.id,
    title: p.title || p.name || '',
    originalTitle: p.original_title || p.originalTitle || '',
    year: p.year || (Array.isArray(p.pubdate) && p.pubdate[0] ? String(p.pubdate[0]).match(/\d{4}/)?.[0] : ''),
    mediaType: p.media_type || p.type || '',
    poster: p.cover_url || p.cover?.url || '',
    voteAverage: rating != null ? String(rating) : '',
    genres,
    countries,
    durations,
    overview: p.intro || p.summary || p.overview || ''
  }
}

const doubanProxySrc = (url) => {
  if (!url) return ''
  if (/doubanio\.com/i.test(url)) return `${getApiBaseUrl()}/test/douban/image?url=${encodeURIComponent(url)}`
  return url
}

const doubanAvatar = (actor) => doubanProxySrc(actor.avatar || '')

const toggleDoubanRaw = () => {
  showDoubanRaw.value = !showDoubanRaw.value
}
</script>

<style scoped>
.gradient-text {
  background: linear-gradient(to right, #6366F1, #8B5CF6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
