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
  <div class="min-h-screen flex flex-col">
    <AppHeader
      @logout="handleLogout"
      @change-password="handleChangePassword"
      @open-settings="handleOpenSettings"
      @open-logs="handleOpenLogs"
      @open-tmdb-test="handleOpenTmdbTest"
    />
    <main class="flex-1 w-full">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/core/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const handleLogout = async () => {
  try {
    const { authenticatedApiCall } = await import('~/core/api/client')
    await authenticatedApiCall('/auth/sign-out', { method: 'POST' })
  } catch (error) {
    // 忽略错误
  } finally {
    const { stopTokenRefreshService } = await import('~/core/utils/tokenRefresh.js')
    stopTokenRefreshService()
    authStore.clearAuth()
    // 内嵌模式：返回 withU 后台首页
    window.location.href = '/'
  }
}

const handleChangePassword = () => {
  router.push('/auth/change-password')
}

const handleOpenSettings = () => {
  router.push('/settings')
}

const handleOpenLogs = () => {
  router.push('/logs')
}

const handleOpenTmdbTest = () => {
  router.push('/tmdb-test')
}
</script>
