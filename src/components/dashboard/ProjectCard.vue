<script setup lang="ts">
import type { ProjectSummary } from '@/types'

const props = defineProps<{ summary: ProjectSummary }>()
const emit = defineEmits<{ open: [id: string]; delete: [id: string] }>()

function formatDate(timestamp: number): string {
  return new Date(timestamp).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>

<template>
  <div
    class="group flex cursor-pointer flex-col gap-2 rounded-lg border border-slate-800 bg-slate-900 p-4 text-left transition-colors hover:border-violet-600"
    @click="emit('open', props.summary.id)"
  >
    <div class="flex items-start justify-between gap-2">
      <h3 class="truncate font-medium text-slate-100">{{ summary.name }}</h3>
      <button
        type="button"
        class="shrink-0 rounded px-1.5 py-0.5 text-xs text-slate-500 opacity-0 hover:bg-red-500/10 hover:text-red-400 group-hover:opacity-100"
        @click.stop="emit('delete', props.summary.id)"
      >
        Delete
      </button>
    </div>
    <p class="text-xs text-slate-500">
      {{ summary.fileCount }} file{{ summary.fileCount === 1 ? '' : 's' }} · Updated
      {{ formatDate(summary.updatedAt) }}
    </p>
  </div>
</template>
