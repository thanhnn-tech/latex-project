<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  MagnifyingGlassPlusIcon,
  MagnifyingGlassMinusIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'
import * as pdfjsLib from 'pdfjs-dist'
import type { PDFDocumentLoadingTask, PDFDocumentProxy, RenderTask } from 'pdfjs-dist'
import '@/utils/pdfWorker'
import { useWorkspaceStore } from '@/stores/workspace.store'
import { downloadBlob, downloadFromUrl } from '@/utils/downloadBlob'
import { apiUrl } from '@/services/api/httpClient'

const MIN_SCALE = 0.5
const MAX_SCALE = 2
const SCALE_STEP = 0.1

const store = useWorkspaceStore()
const canvasRef = ref<HTMLCanvasElement | null>(null)
const scale = ref(1)
const currentPage = ref(1)
const numPages = ref(0)
const isLoadingPdf = ref(false)
const pdfError = ref<string | null>(null)
const highlightBox = ref<{ top: number; left: number; width: number; height: number } | null>(null)

let loadingTask: PDFDocumentLoadingTask | null = null
let pdfDoc: PDFDocumentProxy | null = null
let renderTask: RenderTask | null = null
let highlightTimeoutId: ReturnType<typeof setTimeout> | null = null

function clearCanvas(): void {
  const canvas = canvasRef.value
  if (!canvas) return
  canvas.width = 0
  canvas.height = 0
}

async function renderCurrentPage(): Promise<void> {
  if (!pdfDoc || !canvasRef.value) return

  renderTask?.cancel()

  const page = await pdfDoc.getPage(currentPage.value)
  const viewport = page.getViewport({ scale: scale.value })
  const canvas = canvasRef.value
  const context = canvas.getContext('2d')
  if (!context) return

  canvas.width = viewport.width
  canvas.height = viewport.height

  renderTask = page.render({ canvasContext: context, viewport, canvas })
  try {
    await renderTask.promise
  } catch (err) {
    if (err instanceof Error && err.name !== 'RenderingCancelledException') {
      pdfError.value = err.message
    }
  }
}

function clearPdf(): void {
  void loadingTask?.destroy()
  loadingTask = null
  pdfDoc = null
  numPages.value = 0
  currentPage.value = 1
  pdfError.value = null
  clearCanvas()
}

async function loadPdf(): Promise<void> {
  const url = store.pdfUrl
  if (!url) return

  isLoadingPdf.value = true
  pdfError.value = null
  try {
    await loadingTask?.destroy()
    loadingTask = pdfjsLib.getDocument({ url })
    pdfDoc = await loadingTask.promise
    numPages.value = pdfDoc.numPages
    currentPage.value = 1
    await renderCurrentPage()
  } catch (err) {
    pdfError.value = err instanceof Error && err.message.includes('Unexpected server response')
      ? 'No compiled PDF available.'
      : err instanceof Error ? err.message : 'Failed to load PDF'
  } finally {
    isLoadingPdf.value = false
  }
}

function zoomIn(): void {
  scale.value = Math.min(MAX_SCALE, Math.round((scale.value + SCALE_STEP) * 100) / 100)
  void renderCurrentPage()
}

function zoomOut(): void {
  scale.value = Math.max(MIN_SCALE, Math.round((scale.value - SCALE_STEP) * 100) / 100)
  void renderCurrentPage()
}

function prevPage(): void {
  if (currentPage.value <= 1) return
  currentPage.value -= 1
  void renderCurrentPage()
}

function nextPage(): void {
  if (currentPage.value >= numPages.value) return
  currentPage.value += 1
  void renderCurrentPage()
}

function refresh(): void {
  void loadPdf()
}

/** SyncTeX click-to-jump: PDF click -> nearest source line, resolved server-side. */
function handleCanvasClick(event: MouseEvent): void {
  if (store.compileStatus !== 'success') return
  // synctex coordinates are unscaled PDF points (top-left origin), matching the
  // canvas's own un-transformed pixel space before the render `scale` is applied.
  const pdfX = event.offsetX / scale.value
  const pdfY = event.offsetY / scale.value
  void store.syncReverseToEditor(currentPage.value, pdfX, pdfY)
}

function flashHighlight(box: { x: number; y: number; width: number; height: number }): void {
  if (highlightTimeoutId) clearTimeout(highlightTimeoutId)
  highlightBox.value = {
    left: box.x * scale.value,
    top: box.y * scale.value,
    width: box.width * scale.value,
    height: box.height * scale.value,
  }
  highlightTimeoutId = setTimeout(() => {
    highlightBox.value = null
    highlightTimeoutId = null
  }, 1500)
}

async function compileNow(): Promise<void> {
  await store.compile()
}

function downloadActiveFile(): void {
  const file = store.activeFile
  if (!file) return
  downloadBlob(new Blob([file.content ?? ''], { type: 'text/plain' }), file.name)
}

function downloadProjectZip(): void {
  if (!store.project) return
  downloadFromUrl(apiUrl('/projects/' + store.project.id + '/download'))
}

watch(() => store.pdfVersion, () => {
  void loadPdf()
})

// A failed (or in-flight) recompile can leave the previously-successful PDF
// deleted server-side — latexmk doesn't regenerate main.pdf on a fatal error,
// so keep showing a stale preview or letting Refresh hit that URL just 404s.
watch(() => store.compileStatus, (status) => {
  if (status !== 'success') {
    clearPdf()
  }
})

watch(() => store.project?.id, () => {
  clearPdf()
  scale.value = 1
  if (store.compileStatus === 'success') {
    void loadPdf()
  }
})

watch(() => store.pdfSyncTarget, async (target) => {
  if (!target) return
  if (currentPage.value !== target.page) {
    currentPage.value = target.page
    await renderCurrentPage()
  }
  flashHighlight(target)
  store.clearPdfSyncTarget()
})

onMounted(() => {
  if (store.compileStatus === 'success') {
    void loadPdf()
  }
})

onBeforeUnmount(() => {
  renderTask?.cancel()
  void loadingTask?.destroy()
  if (highlightTimeoutId) clearTimeout(highlightTimeoutId)
})
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="flex shrink-0 items-center justify-between border-b border-slate-800 px-3 py-2">
      <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preview</span>
      <button
        type="button"
        class="rounded bg-violet-600 px-3 py-1 text-xs font-medium text-white hover:bg-violet-500 disabled:opacity-50"
        :disabled="store.compileStatus === 'compiling' || !store.activeFile"
        @click="compileNow"
      >
        {{ store.compileStatus === 'compiling' ? 'Compiling…' : 'Compile' }}
      </button>
    </div>

    <div class="flex shrink-0 items-center justify-between border-b border-slate-800 px-3 py-1.5 text-xs text-slate-400">
      <span
        :class="{
          'text-slate-500': store.compileStatus === 'idle',
          'text-amber-400': store.compileStatus === 'compiling',
          'text-emerald-400': store.compileStatus === 'success',
          'text-red-400': store.compileStatus === 'failed',
        }"
      >
        {{ { idle: 'Idle', compiling: 'Compiling…', success: 'Compiled', failed: 'Compile failed' }[store.compileStatus] }}
        <span v-if="store.project?.compileDurationMs && store.compileStatus !== 'compiling'">
          ({{ store.project.compileDurationMs }}ms)
        </span>
      </span>

      <div class="flex items-center gap-2">
        <div class="flex items-center gap-1">
          <button type="button" class="rounded p-1 hover:bg-slate-800 disabled:opacity-30" :disabled="scale <= MIN_SCALE" @click="zoomOut">
            <MagnifyingGlassMinusIcon class="h-4 w-4" />
          </button>
          <span class="w-10 text-center">{{ Math.round(scale * 100) }}%</span>
          <button type="button" class="rounded p-1 hover:bg-slate-800 disabled:opacity-30" :disabled="scale >= MAX_SCALE" @click="zoomIn">
            <MagnifyingGlassPlusIcon class="h-4 w-4" />
          </button>
        </div>
        <div class="flex items-center gap-1">
          <button type="button" class="rounded p-1 hover:bg-slate-800 disabled:opacity-30" :disabled="currentPage <= 1" @click="prevPage">
            <ChevronLeftIcon class="h-4 w-4" />
          </button>
          <span>Page {{ numPages === 0 ? 0 : currentPage }} of {{ numPages }}</span>
          <button type="button" class="rounded p-1 hover:bg-slate-800 disabled:opacity-30" :disabled="currentPage >= numPages" @click="nextPage">
            <ChevronRightIcon class="h-4 w-4" />
          </button>
        </div>
        <button
          type="button"
          class="rounded p-1 hover:bg-slate-800 disabled:opacity-30"
          title="Refresh"
          :disabled="store.compileStatus !== 'success'"
          @click="refresh"
        >
          <ArrowPathIcon class="h-4 w-4" />
        </button>
      </div>
    </div>

    <div class="flex flex-1 items-center justify-center overflow-auto p-4">
      <p v-if="isLoadingPdf" class="text-sm text-slate-500">Loading PDF…</p>
      <p v-else-if="pdfError" class="max-w-xs text-center text-sm text-red-400">{{ pdfError }}</p>
      <div v-show="!isLoadingPdf && !pdfError && numPages > 0" class="relative">
        <canvas
          ref="canvasRef"
          class="cursor-text shadow-lg"
          title="Click to jump to this spot in the editor"
          @click="handleCanvasClick"
        />
        <div
          v-if="highlightBox"
          class="pointer-events-none absolute rounded bg-amber-400/30 ring-2 ring-amber-400 transition-opacity duration-500"
          :style="{
            top: highlightBox.top + 'px',
            left: highlightBox.left + 'px',
            width: highlightBox.width + 'px',
            height: highlightBox.height + 'px',
          }"
        />
      </div>
      <p v-if="!isLoadingPdf && !pdfError && numPages === 0" class="text-center text-sm text-slate-500">
        {{
          store.compileStatus === 'failed'
            ? 'Compile failed — check Problems below, then recompile.'
            : 'Click Compile to render a real PDF preview.'
        }}
      </p>
    </div>

    <div class="flex shrink-0 flex-col gap-2 border-t border-slate-800 p-3">
      <button
        type="button"
        class="rounded border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 disabled:opacity-50"
        :disabled="!store.activeFile"
        @click="downloadActiveFile"
      >
        Download {{ store.activeFile?.name ?? 'file' }}
      </button>
      <button
        type="button"
        class="rounded border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 disabled:opacity-50"
        :disabled="!store.hasProject"
        @click="downloadProjectZip"
      >
        Download Project (.zip)
      </button>
    </div>
  </div>
</template>
