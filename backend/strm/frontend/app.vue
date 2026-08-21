<!--
  Ostrm - Stream Management System
  Copyright (C) 2024 Ostrm Project

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
  <NuxtLayout>
    <NuxtRouteAnnouncer />
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { initTokenRefreshService, stopTokenRefreshService } from '~/core/utils/tokenRefresh.js'
import { useAuthStore } from '~/core/stores/auth.js'

// 在应用挂载时初始化
onMounted(() => {
  if (import.meta.client) {
    const authStore = useAuthStore()
    
    // 恢复认证状态
    authStore.restoreAuth()
    
    // 如果已登录，启动 Token 刷新服务
    if (authStore.isAuthenticated) {
      initTokenRefreshService()
    }
  }
})

// 在应用卸载时清理
onUnmounted(() => {
  stopTokenRefreshService()
})
</script>

