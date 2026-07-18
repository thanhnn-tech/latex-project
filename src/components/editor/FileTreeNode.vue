<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { FolderIcon, FolderOpenIcon } from '@heroicons/vue/24/outline'
import { fileKindPresentation } from '@/utils/fileKind'
import { IMAGE_PATH_DRAG_TYPE } from '@/utils/dragTypes'
import type { FileTreeNode as TreeNode } from '@/utils/buildFileTree'

const props = defineProps<{
  node: TreeNode
  depth: number
  expandedPaths: Set<string>
  activeFileId: string | null
  editingId: string | null
}>()

const emit = defineEmits<{
  toggle: [path: string]
  selectFile: [fileId: string]
  selectImage: [fileId: string]
  selectPdf: [fileId: string]
  startRename: [id: string, currentName: string]
  commitRename: [id: string, newName: string]
  cancelRename: []
  delete: [id: string]
  contextMenu: [event: MouseEvent, node: TreeNode]
  dropOntoFolder: [draggedId: string, targetFolderPath: string]
  dragImageStart: [event: DragEvent, path: string]
}>()

const localEditingName = ref(props.node.displayName)
let renameInputEl: HTMLInputElement | null = null

function captureRenameInput(el: unknown): void {
  renameInputEl = el instanceof HTMLInputElement ? el : null
}

watch(() => props.editingId, (id) => {
  if (id !== null && id === props.node.file?.id) {
    localEditingName.value = props.node.displayName
    void nextTick(() => {
      renameInputEl?.focus()
      renameInputEl?.select()
    })
  }
})

const isExpanded = computed(() => props.expandedPaths.has(props.node.path))
const isEditing = computed(() => props.editingId !== null && props.editingId === props.node.file?.id)
const kind = computed(() => (props.node.isDirectory ? 'directory' : (props.node.file?.kind ?? 'text')))
const presentation = computed(() => fileKindPresentation(kind.value))
const isImage = computed(() => props.node.file?.kind === 'image')

function handleRowClick(): void {
  if (isEditing.value) return

  if (props.node.isDirectory) {
    emit('toggle', props.node.path)
    return
  }

  const file = props.node.file
  if (!file) return

  if (file.kind === 'image') {
    emit('selectImage', file.id)
  } else if (file.kind === 'pdf') {
    emit('selectPdf', file.id)
  } else {
    emit('selectFile', file.id)
  }
}

function handleDragStart(event: DragEvent): void {
  if (!isImage.value || !event.dataTransfer) return
  event.dataTransfer.setData(IMAGE_PATH_DRAG_TYPE, props.node.path)
  emit('dragImageStart', event, props.node.path)
}

function handleFileDragStart(event: DragEvent): void {
  if (!props.node.file || !event.dataTransfer) return
  event.dataTransfer.setData('application/x-file-id', props.node.file.id)
}

function handleDrop(event: DragEvent): void {
  if (!props.node.isDirectory) return
  const draggedId = event.dataTransfer?.getData('application/x-file-id')
  if (draggedId) emit('dropOntoFolder', draggedId, props.node.path)
}

function commit(): void {
  const file = props.node.file
  if (!file) {
    emit('cancelRename')
    return
  }
  if (localEditingName.value.trim() && localEditingName.value.trim() !== props.node.displayName) {
    emit('commitRename', file.id, localEditingName.value.trim())
  } else {
    emit('cancelRename')
  }
}
</script>

<template>
  <div>
    <div
      class="group flex items-center gap-1.5 py-1 pr-2 text-sm"
      :style="{ paddingLeft: depth * 14 + 8 + 'px' }"
      :class="
        node.file?.id === activeFileId
          ? 'bg-violet-600/20 text-violet-300'
          : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
      "
      draggable="true"
      @click="handleRowClick"
      @contextmenu.prevent="emit('contextMenu', $event, node)"
      @dragstart="isImage ? handleDragStart($event) : handleFileDragStart($event)"
      @dragover.prevent
      @drop.prevent="handleDrop"
    >
      <component
        :is="node.isDirectory ? (isExpanded ? FolderOpenIcon : FolderIcon) : presentation.icon"
        class="h-4 w-4 shrink-0"
        :class="presentation.colorClass"
      />

      <input
        v-if="isEditing"
        :ref="captureRenameInput"
        v-model="localEditingName"
        class="w-full rounded border border-violet-500 bg-slate-950 px-1 py-0.5 text-xs text-slate-100"
        @keyup.enter="commit"
        @keyup.escape="emit('cancelRename')"
        @blur="commit"
        @click.stop
      />
      <span v-else class="flex-1 truncate">{{ node.displayName }}</span>

      <span v-if="!isEditing" class="hidden shrink-0 gap-1 group-hover:flex">
        <button
          type="button"
          class="text-xs text-slate-500 hover:text-violet-400"
          @click.stop="emit('startRename', node.file?.id ?? '', node.displayName)"
        >
          Rename
        </button>
        <button
          type="button"
          class="text-xs text-slate-500 hover:text-red-400"
          @click.stop="emit('delete', node.file?.id ?? '')"
        >
          Delete
        </button>
      </span>
    </div>

    <div v-if="node.isDirectory && isExpanded">
      <FileTreeNode
        v-for="child in node.children"
        :key="child.path"
        :node="child"
        :depth="depth + 1"
        :expanded-paths="expandedPaths"
        :active-file-id="activeFileId"
        :editing-id="editingId"
        @toggle="(path) => emit('toggle', path)"
        @select-file="(id) => emit('selectFile', id)"
        @select-image="(id) => emit('selectImage', id)"
        @select-pdf="(id) => emit('selectPdf', id)"
        @start-rename="(id, name) => emit('startRename', id, name)"
        @commit-rename="(id, name) => emit('commitRename', id, name)"
        @cancel-rename="emit('cancelRename')"
        @delete="(id) => emit('delete', id)"
        @context-menu="(event, targetNode) => emit('contextMenu', event, targetNode)"
        @drop-onto-folder="(draggedId, targetPath) => emit('dropOntoFolder', draggedId, targetPath)"
        @drag-image-start="(event, path) => emit('dragImageStart', event, path)"
      />
    </div>
  </div>
</template>
