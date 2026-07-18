<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { useWorkspaceStore } from '@/stores/workspace.store'

const store = useWorkspaceStore()

function closeTab(event: Event, fileId: string): void {
  event.stopPropagation()
  store.closeTab(fileId)
}
</script>

<template>
  <div class="flex h-9 shrink-0 items-stretch overflow-x-auto border-b border-slate-800 bg-slate-950">
    <button
      v-for="tab in store.openTabs"
      :key="tab.id"
      type="button"
      class="group flex shrink-0 items-center gap-2 border-r border-slate-800 px-3 text-xs transition-colors"
      :class="
        tab.id === store.activeTabId
          ? 'bg-slate-900 text-slate-100'
          : 'text-slate-500 hover:bg-slate-900/60 hover:text-slate-300'
      "
      @click="store.setActiveTab(tab.id)"
    >
      <span class="max-w-[10rem] truncate">{{ tab.name }}</span>
      <XMarkIcon
        class="h-3.5 w-3.5 shrink-0 rounded opacity-0 hover:bg-slate-700 group-hover:opacity-100"
        @click="closeTab($event, tab.id)"
      />
    </button>
    <div v-if="store.openTabs.length === 0" class="flex items-center px-3 text-xs text-slate-600">
      No files open
    </div>
  </div>
</template>
