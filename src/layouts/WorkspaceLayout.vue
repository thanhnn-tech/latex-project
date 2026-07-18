<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import ActivityBar from '@/components/workspace/ActivityBar.vue'

const MIN_PREVIEW_WIDTH = 320
const DEFAULT_PREVIEW_WIDTH = 416
const STORAGE_KEY = 'workspace.previewWidth'

function maxPreviewWidth(): number {
  // Always leave the editor at least 400px, regardless of window size.
  return Math.max(MIN_PREVIEW_WIDTH, window.innerWidth - 400)
}

function loadStoredWidth(): number {
  const stored = Number(localStorage.getItem(STORAGE_KEY))
  return stored > 0 ? stored : DEFAULT_PREVIEW_WIDTH
}

const previewWidth = ref(Math.min(loadStoredWidth(), maxPreviewWidth()))
const isResizingPreview = ref(false)

let dragStartX = 0
let dragStartWidth = 0

function startPreviewResize(event: MouseEvent): void {
  isResizingPreview.value = true
  dragStartX = event.clientX
  dragStartWidth = previewWidth.value
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  window.addEventListener('mousemove', handlePreviewResize)
  window.addEventListener('mouseup', stopPreviewResize)
  event.preventDefault()
}

function handlePreviewResize(event: MouseEvent): void {
  // The preview pane sits on the right, so dragging left (negative clientX
  // delta) should grow it — hence the sign flip here.
  const next = dragStartWidth + (dragStartX - event.clientX)
  previewWidth.value = Math.min(maxPreviewWidth(), Math.max(MIN_PREVIEW_WIDTH, next))
}

function stopPreviewResize(): void {
  isResizingPreview.value = false
  document.body.style.cursor = ''
  document.body.style.userSelect = ''
  window.removeEventListener('mousemove', handlePreviewResize)
  window.removeEventListener('mouseup', stopPreviewResize)
  localStorage.setItem(STORAGE_KEY, String(previewWidth.value))
}

function resetPreviewWidth(): void {
  previewWidth.value = Math.min(DEFAULT_PREVIEW_WIDTH, maxPreviewWidth())
  localStorage.setItem(STORAGE_KEY, String(previewWidth.value))
}

function handleWindowResize(): void {
  previewWidth.value = Math.min(previewWidth.value, maxPreviewWidth())
}

onMounted(() => {
  window.addEventListener('resize', handleWindowResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', handlePreviewResize)
  window.removeEventListener('mouseup', stopPreviewResize)
  window.removeEventListener('resize', handleWindowResize)
})
</script>

<template>
  <div class="flex h-screen flex-col bg-slate-950 text-slate-100">
    <header
      class="flex h-11 shrink-0 items-center justify-between border-b border-slate-800 bg-slate-950 px-3"
    >
      <slot name="header" />
    </header>
    <div class="flex flex-1 flex-col overflow-hidden">
      <div class="flex flex-1 overflow-hidden">
        <ActivityBar active-id="explorer" />
        <aside class="w-64 shrink-0 overflow-y-auto border-r border-slate-800 bg-slate-900">
          <slot name="sidebar" />
        </aside>
        <section class="flex min-w-0 flex-1 flex-col overflow-hidden">
          <slot name="tabs" />
          <div class="min-h-0 flex-1">
            <slot name="editor" />
          </div>
        </section>

        <div
          class="group relative w-px shrink-0 cursor-col-resize bg-slate-800"
          :class="{ 'bg-violet-600': isResizingPreview }"
          title="Drag to resize · double-click to reset"
          @mousedown="startPreviewResize"
          @dblclick="resetPreviewWidth"
        >
          <div
            class="absolute inset-y-0 -left-1.5 -right-1.5 group-hover:bg-violet-600/40"
            :class="{ 'bg-violet-600/40': isResizingPreview }"
          />
        </div>

        <aside
          class="shrink-0 overflow-y-auto bg-slate-900"
          :style="{ width: previewWidth + 'px' }"
        >
          <slot name="preview" />
        </aside>
      </div>
      <div class="h-48 shrink-0 border-t border-slate-800">
        <slot name="bottom" />
      </div>
    </div>
  </div>
</template>
