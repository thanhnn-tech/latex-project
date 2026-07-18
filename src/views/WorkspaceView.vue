<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import WorkspaceLayout from '@/layouts/WorkspaceLayout.vue'
import FileExplorer from '@/components/editor/FileExplorer.vue'
import LatexEditor from '@/components/editor/LatexEditor.vue'
import PreviewPanel from '@/components/editor/PreviewPanel.vue'
import EditorTabs from '@/components/workspace/EditorTabs.vue'
import BottomPanel from '@/components/workspace/BottomPanel.vue'
import ImagePreviewModal from '@/components/workspace/ImagePreviewModal.vue'
import { useWorkspaceStore } from '@/stores/workspace.store'

const props = defineProps<{ projectId: string }>()
const router = useRouter()
const store = useWorkspaceStore()

async function load(projectId: string): Promise<void> {
  const found = await store.loadProject(projectId)
  if (!found) {
    await router.push({ name: 'dashboard' })
  }
}

onMounted(() => {
  void load(props.projectId)
})

watch(() => props.projectId, (projectId) => {
  void load(projectId)
})
</script>

<template>
  <WorkspaceLayout v-if="store.hasProject">
    <template #header>
      <div class="flex items-center gap-3">
        <RouterLink to="/" class="text-sm font-medium text-slate-500 hover:text-slate-300">
          &larr; Dashboard
        </RouterLink>
        <h1 class="text-sm font-semibold text-slate-100">{{ store.project?.name }}</h1>
      </div>
    </template>

    <template #sidebar>
      <FileExplorer />
    </template>

    <template #tabs>
      <EditorTabs />
    </template>

    <template #editor>
      <LatexEditor />
    </template>

    <template #preview>
      <PreviewPanel />
    </template>

    <template #bottom>
      <BottomPanel />
    </template>
  </WorkspaceLayout>

  <ImagePreviewModal />
</template>
