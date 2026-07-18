<script setup lang="ts">
import { formatBytes } from '@/utils/formatBytes'
import type { ProjectFile } from '@/types'

defineProps<{ file: ProjectFile }>()
const emit = defineEmits<{ close: [] }>()

function formatDate(timestamp: number): string {
  return new Date(timestamp).toLocaleString()
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="emit('close')">
    <div class="w-80 rounded-lg border border-slate-700 bg-slate-900 p-5 text-sm text-slate-200 shadow-xl">
      <h2 class="mb-3 text-sm font-semibold text-slate-100">Properties</h2>
      <dl class="space-y-2">
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Name</dt>
          <dd class="truncate">{{ file.name }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Kind</dt>
          <dd class="capitalize">{{ file.kind }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Size</dt>
          <dd>{{ formatBytes(file.size) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Created</dt>
          <dd>{{ formatDate(file.createdAt) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Updated</dt>
          <dd>{{ formatDate(file.updatedAt) }}</dd>
        </div>
      </dl>
      <button
        type="button"
        class="mt-4 w-full rounded bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700"
        @click="emit('close')"
      >
        Close
      </button>
    </div>
  </div>
</template>
