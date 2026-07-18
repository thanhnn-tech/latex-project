<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import * as monaco from 'monaco-editor'
import '@/utils/monacoWorkers'
import { registerLatexLanguage } from '@/utils/latexLanguage'
import { useWorkspaceStore } from '@/stores/workspace.store'
import { useAutosave } from '@/composables/useAutosave'
import { useAutoCompile } from '@/composables/useAutoCompile'
import { IMAGE_PATH_DRAG_TYPE } from '@/utils/dragTypes'
import type { CompileProblem, FileLanguage } from '@/types'

const store = useWorkspaceStore()
const containerRef = ref<HTMLDivElement | null>(null)
let editor: monaco.editor.IStandaloneCodeEditor | null = null
const models = new Map<string, monaco.editor.ITextModel>()

// Tracks whichever file the editor's current model actually belongs to. Save/compile
// callbacks fire asynchronously (debounced) and must never read `store.activeFile?.id`
// directly — by the time they run, a tab switch may have already changed it, which
// would silently attribute the outgoing editor content to the wrong (new) file.
let currentEditingFileId: string | null = null

const { trigger: scheduleSave, flush: flushSave, isSaving } = useAutosave((value) => {
  if (currentEditingFileId) void store.updateFileContent(currentEditingFileId, value)
})

const { trigger: scheduleCompile, cancel: cancelCompile } = useAutoCompile(async (signal) => {
  // Compile reads the file from disk server-side, so the latest edit must be
  // persisted first — the separate autosave timer alone doesn't guarantee that.
  if (currentEditingFileId && editor) {
    await store.updateFileContent(currentEditingFileId, editor.getValue())
  }
  await store.compile(signal)
}, 1000)

function monacoLanguageId(language: FileLanguage): string {
  if (language === 'latex') return 'latex'
  if (language === 'markdown') return 'markdown'
  return 'plaintext'
}

function getOrCreateModel(fileId: string, content: string, language: FileLanguage): monaco.editor.ITextModel {
  const existing = models.get(fileId)
  if (existing) return existing
  const model = monaco.editor.createModel(content, monacoLanguageId(language))
  models.set(fileId, model)
  applyMarkersToModel(fileId, model)
  return model
}

function markersForProblems(problems: CompileProblem[], model: monaco.editor.ITextModel): monaco.editor.IMarkerData[] {
  return problems.map((problem) => {
    const line = Math.min(Math.max(problem.line ?? 1, 1), model.getLineCount())
    return {
      severity: problem.severity === 'error' ? monaco.MarkerSeverity.Error : monaco.MarkerSeverity.Warning,
      message: problem.message,
      startLineNumber: line,
      startColumn: 1,
      endLineNumber: line,
      endColumn: model.getLineMaxColumn(line),
    }
  })
}

function applyMarkersToModel(fileId: string, model: monaco.editor.ITextModel): void {
  const file = store.files.find((candidate) => candidate.id === fileId)
  const problems = file ? store.problems.filter((problem) => problem.file === file.name) : []
  monaco.editor.setModelMarkers(model, 'latex-compile', markersForProblems(problems, model))
}

function refreshAllMarkers(): void {
  for (const [fileId, model] of models) {
    applyMarkersToModel(fileId, model)
  }
}

function syncActiveFile(): void {
  const file = store.activeFile
  if (!editor || !file) return

  const model = getOrCreateModel(file.id, file.content ?? '', file.language)
  if (editor.getModel() !== model) {
    editor.setModel(model)
  }
  currentEditingFileId = file.id
}

function pruneStaleModels(): void {
  const liveIds = new Set(store.files.map((file) => file.id))
  for (const [id, model] of models) {
    if (!liveIds.has(id)) {
      model.dispose()
      models.delete(id)
    }
  }
}

function handleContainerDragOver(event: DragEvent): void {
  if (event.dataTransfer?.types.includes(IMAGE_PATH_DRAG_TYPE)) {
    event.preventDefault()
  }
}

function handleContainerDrop(event: DragEvent): void {
  const path = event.dataTransfer?.getData(IMAGE_PATH_DRAG_TYPE)
  if (!path || !editor) return
  event.preventDefault()

  const target = editor.getTargetAtClientPoint(event.clientX, event.clientY)
  const position = target?.position ?? editor.getPosition()
  if (!position) return

  const snippet = '\\includegraphics[width=\\textwidth]{' + path + '}'
  editor.executeEdits('drag-insert-image', [
    {
      range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
      text: snippet,
    },
  ])
  editor.focus()
}

onMounted(() => {
  registerLatexLanguage()
  if (!containerRef.value) return

  editor = monaco.editor.create(containerRef.value, {
    automaticLayout: true,
    minimap: { enabled: false },
    fontSize: 13,
    theme: 'vs-dark',
    wordWrap: 'on',
  })

  editor.onDidChangeModelContent(() => {
    scheduleSave(editor?.getValue() ?? '')
    scheduleCompile()
  })

  editor.addAction({
    id: 'sync-to-pdf',
    label: 'Sync to PDF (locate cursor in preview)',
    keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyMod.Alt | monaco.KeyCode.KeyJ],
    run: (ed) => {
      const line = ed.getPosition()?.lineNumber
      if (line && store.activeFile) void store.syncForwardToPdf(store.activeFile.name, line)
    },
  })

  syncActiveFile()
})

watch(() => store.activeFile?.id, () => {
  if (editor) flushSave(editor.getValue())
  syncActiveFile()
})

watch(() => store.files.map((file) => file.id).join(','), () => {
  pruneStaleModels()
})

watch(() => store.files.map((file) => file.id + ':' + file.language).join(','), () => {
  for (const file of store.files) {
    const model = models.get(file.id)
    const desired = monacoLanguageId(file.language)
    if (model && model.getLanguageId() !== desired) {
      monaco.editor.setModelLanguage(model, desired)
    }
  }
})

watch(() => store.problems, refreshAllMarkers, { deep: true })

// A version restore overwrites file content server-side, bypassing the normal
// typing flow entirely — the Monaco model (if the file is open) needs to be
// force-synced to match, otherwise the editor would silently show stale text.
watch(() => store.pendingContentReset, (reset) => {
  if (!reset) return
  const model = models.get(reset.fileId)
  if (model && model.getValue() !== reset.content) {
    model.setValue(reset.content)
  }
  store.clearContentReset()
})

watch(() => store.pendingJump, (jump) => {
  if (!jump) return
  if (store.activeTabId !== jump.fileId) {
    store.openTab(jump.fileId)
  }
  void nextTick(() => {
    syncActiveFile()
    if (editor) {
      editor.revealLineInCenter(jump.line)
      editor.setPosition({ lineNumber: jump.line, column: 1 })
      editor.focus()
    }
    store.clearJump()
  })
})

onBeforeUnmount(() => {
  cancelCompile()
  if (editor) flushSave(editor.getValue())
  editor?.dispose()
  models.forEach((model) => model.dispose())
  models.clear()
})
</script>

<template>
  <div class="flex h-full flex-col bg-slate-900">
    <div v-if="!store.activeFile" class="flex flex-1 items-center justify-center text-sm text-slate-500">
      Select or create a file to start editing.
    </div>
    <div
      v-show="store.activeFile"
      ref="containerRef"
      class="min-h-0 flex-1"
      @dragover="handleContainerDragOver"
      @drop="handleContainerDrop"
    />
    <div v-if="isSaving" class="shrink-0 border-t border-slate-800 px-3 py-1 text-xs text-slate-500">
      Saving…
    </div>
  </div>
</template>
