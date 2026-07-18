<script setup lang="ts">
import { ref, watch } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace.store'
import { formatBytes } from '@/utils/formatBytes'
import { formatRelativeTime } from '@/utils/formatRelativeTime'

const store = useWorkspaceStore()
const activeTab = ref<'problems' | 'log' | 'history'>('problems')
const restoringId = ref<number | null>(null)
const confirmingId = ref<number | null>(null)

function jumpTo(file: string, line: number | null): void {
  if (line === null) return
  store.requestJump(file, line)
}

function requestRestore(versionId: number): void {
  confirmingId.value = versionId
}

function cancelRestore(): void {
  confirmingId.value = null
}

async function confirmRestore(versionId: number): Promise<void> {
  const file = store.activeFile
  if (!file) return
  restoringId.value = versionId
  try {
    await store.restoreFileVersion(file.id, versionId)
  } finally {
    restoringId.value = null
    confirmingId.value = null
  }
}

watch(
  [activeTab, () => store.activeFile?.id],
  ([tab, fileId]) => {
    if (tab === 'history' && fileId) void store.loadFileVersions(fileId)
  },
  { immediate: true },
)
</script>

<template>
  <div class="flex h-full flex-col bg-slate-950">
    <div class="flex shrink-0 items-center gap-1 border-b border-slate-800 px-2 py-1 text-xs">
      <button
        type="button"
        class="rounded px-2 py-1 font-medium"
        :class="activeTab === 'problems' ? 'bg-slate-800 text-slate-100' : 'text-slate-500 hover:text-slate-300'"
        @click="activeTab = 'problems'"
      >
        Problems ({{ store.problems.length }})
      </button>
      <button
        type="button"
        class="rounded px-2 py-1 font-medium"
        :class="activeTab === 'log' ? 'bg-slate-800 text-slate-100' : 'text-slate-500 hover:text-slate-300'"
        @click="activeTab = 'log'"
      >
        Compile Log
      </button>
      <button
        type="button"
        class="rounded px-2 py-1 font-medium"
        :class="activeTab === 'history' ? 'bg-slate-800 text-slate-100' : 'text-slate-500 hover:text-slate-300'"
        :disabled="!store.activeFile"
        @click="activeTab = 'history'"
      >
        History
      </button>
    </div>

    <div class="flex-1 overflow-y-auto">
      <ul v-if="activeTab === 'problems'">
        <li
          v-for="(problem, index) in store.problems"
          :key="index"
          class="flex cursor-pointer items-start gap-2 border-b border-slate-900 px-3 py-1.5 text-xs hover:bg-slate-900"
          @click="jumpTo(problem.file, problem.line)"
        >
          <span
            class="mt-0.5 shrink-0 rounded px-1 font-medium uppercase"
            :class="problem.severity === 'error' ? 'bg-red-500/20 text-red-400' : 'bg-amber-500/20 text-amber-400'"
          >
            {{ problem.severity }}
          </span>
          <span class="flex-1 text-slate-300">{{ problem.message }}</span>
          <span class="shrink-0 text-slate-500">
            {{ problem.file }}<template v-if="problem.line">:{{ problem.line }}</template>
          </span>
        </li>
        <li v-if="store.problems.length === 0" class="px-3 py-4 text-center text-xs text-slate-600">
          No problems.
        </li>
      </ul>

      <pre
        v-else-if="activeTab === 'log'"
        class="whitespace-pre-wrap px-3 py-2 font-mono text-xs text-slate-400"
      >{{ store.compileLog || 'No compile log yet.' }}</pre>

      <ul v-else>
        <li
          v-for="version in store.fileVersions"
          :key="version.id"
          class="flex items-center justify-between gap-3 border-b border-slate-900 px-3 py-2 text-xs"
        >
          <div class="flex flex-col">
            <span class="text-slate-300">{{ formatRelativeTime(version.createdAt) }}</span>
            <span class="text-slate-600">{{ formatBytes(version.size) }}</span>
          </div>

          <div v-if="confirmingId === version.id" class="flex items-center gap-1.5">
            <span class="text-amber-400">Restore this version?</span>
            <button
              type="button"
              class="rounded bg-amber-500/20 px-2 py-1 font-medium text-amber-300 hover:bg-amber-500/30 disabled:opacity-50"
              :disabled="restoringId === version.id"
              @click="confirmRestore(version.id)"
            >
              {{ restoringId === version.id ? 'Restoring…' : 'Confirm' }}
            </button>
            <button
              type="button"
              class="rounded px-2 py-1 text-slate-500 hover:text-slate-300"
              @click="cancelRestore"
            >
              Cancel
            </button>
          </div>
          <button
            v-else
            type="button"
            class="shrink-0 rounded border border-slate-700 px-2 py-1 font-medium text-slate-300 hover:bg-slate-800"
            @click="requestRestore(version.id)"
          >
            Restore
          </button>
        </li>
        <li v-if="store.fileVersions.length === 0" class="px-3 py-4 text-center text-xs text-slate-600">
          No earlier versions yet — checkpoints appear a few minutes into active editing.
        </li>
      </ul>
    </div>
  </div>
</template>
