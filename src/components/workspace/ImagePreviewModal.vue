<script setup lang="ts">
import { ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { useWorkspaceStore } from '@/stores/workspace.store'
import { formatBytes } from '@/utils/formatBytes'

const store = useWorkspaceStore()
const dimensions = ref<{ width: number; height: number } | null>(null)

watch(() => store.previewFileId, (id) => {
  dimensions.value = null
  if (!id) return
  const url = store.rawFileUrl(id)
  if (!url) return
  const img = new Image()
  img.onload = () => {
    dimensions.value = { width: img.naturalWidth, height: img.naturalHeight }
  }
  img.src = url
}, { immediate: true })

function formatDate(timestamp: number): string {
  return new Date(timestamp).toLocaleString()
}

function close(): void {
  store.closeImagePreview()
}
</script>

<template>
  <div
    v-if="store.previewFile"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-8"
    @click.self="close"
  >
    <div
      class="flex max-h-full w-full max-w-2xl flex-col gap-4 overflow-hidden rounded-lg border border-slate-700 bg-slate-900 p-4 shadow-xl"
    >
      <div class="flex items-center justify-between gap-3">
        <h2 class="truncate text-sm font-semibold text-slate-100">{{ store.previewFile.name }}</h2>
        <button type="button" class="shrink-0 text-slate-400 hover:text-slate-200" @click="close">
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto rounded bg-slate-950 p-2">
        <img
          :src="store.rawFileUrl(store.previewFile.id) ?? ''"
          :alt="store.previewFile.name"
          class="max-h-[60vh] max-w-full object-contain"
        />
      </div>

      <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-slate-400">
        <div class="flex justify-between">
          <dt>Width</dt>
          <dd>{{ dimensions ? dimensions.width + 'px' : '—' }}</dd>
        </div>
        <div class="flex justify-between">
          <dt>Height</dt>
          <dd>{{ dimensions ? dimensions.height + 'px' : '—' }}</dd>
        </div>
        <div class="flex justify-between">
          <dt>Size</dt>
          <dd>{{ formatBytes(store.previewFile.size) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt>Format</dt>
          <dd class="uppercase">{{ store.previewFile.name.split('.').pop() }}</dd>
        </div>
        <div class="col-span-2 flex justify-between">
          <dt>Created</dt>
          <dd>{{ formatDate(store.previewFile.createdAt) }}</dd>
        </div>
      </dl>
    </div>
  </div>
</template>
