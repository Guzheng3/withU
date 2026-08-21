<template>
  <div>
    <div
      class="group flex items-center gap-2 rounded-lg border px-2 py-1.5 transition-colors"
      :class="selected
        ? 'border-blue-300 bg-blue-100'
        : 'border-transparent hover:border-pink-200 hover:bg-pink-50'"
      :style="{ marginLeft: `${depth * 16}px` }"
    >
      <button
        type="button"
        class="flex min-w-0 flex-1 items-center gap-2 text-left"
        @click="$emit('select', node)"
      >
        <svg
          v-if="!loading"
          class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform"
          :class="{ 'rotate-90': expanded }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          @click.stop="toggleExpanded"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <svg v-else class="h-3.5 w-3.5 shrink-0 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <svg class="h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
        </svg>
        <span class="truncate text-sm text-gray-700">{{ node.name }}</span>
      </button>
      <span
        class="shrink-0 rounded-full px-2 py-0.5 text-[11px]"
        :class="node.childrenLoaded && node.videoFileCount
          ? 'bg-blue-100 text-blue-600'
          : 'bg-gray-50 text-gray-400'"
      >
        {{ node.childrenLoaded ? `${node.videoFileCount} 个本层媒体` : '未加载' }}
      </span>
    </div>

    <div v-if="expanded">
      <ManualScrapingTreeNode
        v-for="child in node.children"
        :key="child.path"
        :node="child"
        :depth="depth + 1"
        :selected-path="selectedPath"
        @select="$emit('select', $event)"
        @load-children="$emit('load-children', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  node: {
    type: Object,
    required: true
  },
  depth: {
    type: Number,
    default: 0
  },
  selectedPath: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['select', 'load-children'])

const expanded = ref(props.depth === 0)
const loading = computed(() => Boolean(props.node.loading))
const selected = computed(() => props.selectedPath === props.node.path)

const toggleExpanded = () => {
  expanded.value = !expanded.value
  if (expanded.value && !props.node.childrenLoaded && !props.node.loading) {
    emit('load-children', props.node)
  }
}
</script>
