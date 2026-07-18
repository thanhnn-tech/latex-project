<script setup lang="ts">
import { computed, ref } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace.store'
import { buildFileTree, type FileTreeNode as TreeNode } from '@/utils/buildFileTree'
import { downloadFromUrl } from '@/utils/downloadBlob'
import { assetUploadAccept } from '@/utils/assetFileTypes'
import FileTreeNode from './FileTreeNode.vue'
import ContextMenu, { type ContextMenuItem } from '@/components/workspace/ContextMenu.vue'
import PropertiesModal from '@/components/workspace/PropertiesModal.vue'
import type { ProjectFile } from '@/types'

const store = useWorkspaceStore()
const expandedPaths = ref(new Set<string>())
const editingId = ref<string | null>(null)
const contextMenuState = ref<{ x: number; y: number; node: TreeNode } | null>(null)
const propertiesFile = ref<ProjectFile | null>(null)
const uploadInputRef = ref<HTMLInputElement | null>(null)
const uploadError = ref<string | null>(null)

const tree = computed(() => buildFileTree(store.files))

const contextMenuItems = computed<ContextMenuItem[]>(() => {
  const node = contextMenuState.value?.node
  if (!node?.file) {
    return [{ label: 'Copy Path', action: 'copy-path' }]
  }
  const items: ContextMenuItem[] = [
    { label: 'Rename', action: 'rename' },
    { label: 'Duplicate', action: 'duplicate' },
    { label: 'Copy Path', action: 'copy-path' },
  ]
  if (!node.isDirectory) {
    items.push({ label: 'Download', action: 'download' })
  }
  items.push({ label: 'Properties', action: 'properties' })
  items.push({ label: 'Delete', action: 'delete', danger: true })
  return items
})

function toggleFolder(path: string): void {
  if (expandedPaths.value.has(path)) {
    expandedPaths.value.delete(path)
  } else {
    expandedPaths.value.add(path)
  }
}

function selectFile(id: string): void {
  store.openTab(id)
}

function selectImage(id: string): void {
  store.openImagePreview(id)
}

function selectPdf(id: string): void {
  const url = store.rawFileUrl(id)
  if (url) window.open(url, '_blank', 'noopener')
}

function startRename(id: string, _currentName: string): void {
  if (!id) return
  editingId.value = id
}

function commitRename(id: string, newName: string): void {
  void store.renameFile(id, newName)
  editingId.value = null
}

function cancelRename(): void {
  editingId.value = null
}

function deleteFile(id: string): void {
  if (!id) return
  void store.removeFile(id)
}

function dropOntoFolder(draggedId: string, targetFolderPath: string): void {
  const file = store.files.find((candidate) => candidate.id === draggedId)
  if (!file) return
  const displayName = file.name.split('/').pop() ?? file.name
  const newPath = targetFolderPath + '/' + displayName
  if (newPath === file.name) return
  void store.moveFile(draggedId, newPath)
}

function openContextMenu(event: MouseEvent, node: TreeNode): void {
  contextMenuState.value = { x: event.clientX, y: event.clientY, node }
}

function closeContextMenu(): void {
  contextMenuState.value = null
}

function handleContextAction(action: string): void {
  const node = contextMenuState.value?.node
  contextMenuState.value = null
  if (!node) return

  switch (action) {
    case 'rename':
      if (node.file) editingId.value = node.file.id
      break
    case 'duplicate':
      if (node.file) void store.duplicateFile(node.file.id)
      break
    case 'delete':
      if (node.file) void store.removeFile(node.file.id)
      break
    case 'copy-path':
      void navigator.clipboard.writeText(node.path)
      break
    case 'download': {
      const url = node.file ? store.rawFileUrl(node.file.id) : null
      if (url) downloadFromUrl(url)
      break
    }
    case 'properties':
      propertiesFile.value = node.file
      break
  }
}

function existingRootNames(): Set<string> {
  return new Set(store.files.map((file) => file.name))
}

async function createNewFile(): Promise<void> {
  const existingNames = existingRootNames()
  let index = store.files.length + 1
  let name = 'untitled-' + index + '.tex'
  while (existingNames.has(name)) {
    index += 1
    name = 'untitled-' + index + '.tex'
  }
  await store.createFile(name, 'latex', '')
  editingId.value = store.activeTabId
}

async function createNewFolder(): Promise<void> {
  const existingNames = existingRootNames()
  let index = 1
  let name = 'New Folder'
  while (existingNames.has(name)) {
    index += 1
    name = 'New Folder ' + index
  }
  await store.createFolder(name)
}

function openUploadDialog(): void {
  uploadInputRef.value?.click()
}

async function handleUploadChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  uploadError.value = null
  try {
    await store.uploadFile(file)
  } catch (err) {
    uploadError.value = err instanceof Error ? err.message : 'Upload failed'
  }
}
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="flex items-center justify-between border-b border-slate-800 px-3 py-2">
      <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Explorer</span>
      <span class="flex gap-1">
        <button
          type="button"
          class="rounded px-2 py-1 text-xs font-medium text-violet-400 hover:bg-slate-800"
          @click="createNewFolder"
        >
          + Folder
        </button>
        <button
          type="button"
          class="rounded px-2 py-1 text-xs font-medium text-violet-400 hover:bg-slate-800"
          @click="createNewFile"
        >
          + New
        </button>
        <button
          type="button"
          class="rounded px-2 py-1 text-xs font-medium text-violet-400 hover:bg-slate-800"
          @click="openUploadDialog"
        >
          Upload
        </button>
        <input
          ref="uploadInputRef"
          type="file"
          class="hidden"
          :accept="assetUploadAccept()"
          @change="handleUploadChange"
        />
      </span>
    </div>

    <p v-if="uploadError" class="border-b border-slate-800 px-3 py-1.5 text-xs text-red-400">{{ uploadError }}</p>

    <div class="flex-1 overflow-y-auto py-1">
      <FileTreeNode
        v-for="node in tree"
        :key="node.path"
        :node="(node as TreeNode)"
        :depth="0"
        :expanded-paths="expandedPaths"
        :active-file-id="store.activeTabId"
        :editing-id="editingId"
        @toggle="toggleFolder"
        @select-file="selectFile"
        @select-image="selectImage"
        @select-pdf="selectPdf"
        @start-rename="startRename"
        @commit-rename="commitRename"
        @cancel-rename="cancelRename"
        @delete="deleteFile"
        @context-menu="openContextMenu"
        @drop-onto-folder="dropOntoFolder"
      />
      <p v-if="tree.length === 0" class="px-3 py-4 text-center text-xs text-slate-600">No files yet.</p>
    </div>

    <ContextMenu
      v-if="contextMenuState"
      :x="contextMenuState.x"
      :y="contextMenuState.y"
      :items="contextMenuItems"
      @select="handleContextAction"
      @close="closeContextMenu"
    />

    <PropertiesModal v-if="propertiesFile" :file="propertiesFile" @close="propertiesFile = null" />
  </div>
</template>
