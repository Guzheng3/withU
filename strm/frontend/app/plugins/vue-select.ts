/**
 * Vue Select 插件配置
 * 用于替换原生 select 元素，支持自定义下拉选项样式
 */

import { defineNuxtPlugin } from '#app'
import VueSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.component('v-select', VueSelect)
})
