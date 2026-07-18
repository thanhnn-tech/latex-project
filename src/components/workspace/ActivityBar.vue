<script setup lang="ts">
import { markRaw } from 'vue'
import type { Component } from 'vue'
import {
  FolderIcon,
  Bars3BottomLeftIcon,
  MagnifyingGlassIcon,
  HashtagIcon,
  BookOpenIcon,
} from '@heroicons/vue/24/outline'

interface ActivityItem {
  id: string
  label: string
  icon: Component
  enabled: boolean
}

const items: ActivityItem[] = [
  { id: 'explorer', label: 'Explorer', icon: markRaw(FolderIcon), enabled: true },
  { id: 'outline', label: 'Outline', icon: markRaw(Bars3BottomLeftIcon), enabled: false },
  { id: 'search', label: 'Search', icon: markRaw(MagnifyingGlassIcon), enabled: false },
  { id: 'symbols', label: 'Symbols', icon: markRaw(HashtagIcon), enabled: false },
  { id: 'citations', label: 'Citations', icon: markRaw(BookOpenIcon), enabled: false },
]

const props = defineProps<{ activeId: string }>()
const emit = defineEmits<{ select: [id: string] }>()
</script>

<template>
  <nav class="flex w-12 shrink-0 flex-col items-center gap-1 border-r border-slate-800 bg-slate-950 py-2">
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      :disabled="!item.enabled"
      :title="item.enabled ? item.label : item.label + ' — coming in a later phase'"
      class="flex h-10 w-10 items-center justify-center rounded-md transition-colors"
      :class="
        item.enabled
          ? props.activeId === item.id
            ? 'bg-violet-600/20 text-violet-400'
            : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
          : 'cursor-not-allowed text-slate-700'
      "
      @click="item.enabled && emit('select', item.id)"
    >
      <component :is="item.icon" class="h-5 w-5" />
    </button>
  </nav>
</template>
