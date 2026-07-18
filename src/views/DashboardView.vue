<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectsStore } from '@/stores/projects.store'
import ProjectCard from '@/components/dashboard/ProjectCard.vue'
import CreateProjectPanel from '@/components/dashboard/CreateProjectPanel.vue'
import FileUploader from '@/components/upload/FileUploader.vue'
import type { UploadedFile } from '@/composables/useFileUpload'

const router = useRouter()
const projectsStore = useProjectsStore()
const isBusy = ref(false)
const errorMessage = ref<string | null>(null)

onMounted(() => {
  void projectsStore.refresh()
})

async function openProject(id: string): Promise<void> {
  await router.push({ name: 'workspace', params: { projectId: id } })
}

async function handleDelete(id: string): Promise<void> {
  await projectsStore.deleteProject(id)
}

async function handleCreateBlank(name: string): Promise<void> {
  isBusy.value = true
  try {
    const id = await projectsStore.createBlankProject(name)
    await openProject(id)
  } finally {
    isBusy.value = false
  }
}

async function handleCreateFromTemplate(templateId: string, name: string): Promise<void> {
  isBusy.value = true
  try {
    const id = await projectsStore.createFromTemplate(templateId, name)
    await openProject(id)
  } finally {
    isBusy.value = false
  }
}

async function handleUploaded(file: UploadedFile): Promise<void> {
  isBusy.value = true
  errorMessage.value = null
  try {
    const id = file.fileName.toLowerCase().endsWith('.zip')
      ? await projectsStore.createFromZip(file.file)
      : await projectsStore.createFromUpload(file.fileName, file.content ?? '')
    await openProject(id)
  } catch (err) {
    errorMessage.value = err instanceof Error ? err.message : 'Import failed'
  } finally {
    isBusy.value = false
  }
}
</script>

<template>
  <main class="min-h-full bg-slate-950 px-6 py-10 text-slate-100">
    <div class="mx-auto flex max-w-6xl flex-col gap-8">
      <header class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold">OpenLaTeX Workspace</h1>
        <p class="text-sm text-slate-500">
          A modern LaTeX IDE — write, manage, and really compile your documents.
        </p>
      </header>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
        <section class="flex flex-col gap-4">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">Recent Projects</h2>
            <input
              v-model="projectsStore.searchQuery"
              type="text"
              placeholder="Search projects…"
              class="w-56 rounded-md border border-slate-800 bg-slate-900 px-3 py-1.5 text-sm text-slate-100 placeholder-slate-600 outline-none focus:border-violet-500"
            />
          </div>

          <div v-if="projectsStore.filteredSummaries.length > 0" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <ProjectCard
              v-for="summary in projectsStore.filteredSummaries"
              :key="summary.id"
              :summary="summary"
              @open="openProject"
              @delete="handleDelete"
            />
          </div>
          <p v-else class="rounded-lg border border-dashed border-slate-800 p-8 text-center text-sm text-slate-500">
            {{
              projectsStore.searchQuery.trim()
                ? 'No projects match your search.'
                : 'No projects yet — create one or import a file to get started.'
            }}
          </p>
        </section>

        <aside class="flex flex-col gap-6">
          <CreateProjectPanel @create-blank="handleCreateBlank" @create-from-template="handleCreateFromTemplate" />

          <div class="flex flex-col gap-2">
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">Import Project</h2>
            <FileUploader @uploaded="handleUploaded" />
            <p v-if="isBusy" class="text-xs text-slate-500">Working…</p>
            <p v-if="errorMessage" class="text-xs text-red-400">{{ errorMessage }}</p>
          </div>
        </aside>
      </div>
    </div>
  </main>
</template>
