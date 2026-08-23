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
  <footer class="footer-glass">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- 左侧信息 -->
        <div class="flex items-center gap-2 text-sm text-gray-400">
          <span class="font-semibold gradient-text">withUstrm</span>
          <span class="hidden sm:inline">·</span>
          <span class="hidden sm:inline">© {{ currentYear }}</span>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
const currentYear = new Date().getFullYear()
</script>

<style scoped>
.gradient-text {
  background: linear-gradient(to right, #EC4899, #F472B6, #60A5FA);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
</style>
