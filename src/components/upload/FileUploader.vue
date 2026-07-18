<script setup lang="ts">
import { ref } from 'vue'
import { useFileUpload, type UploadedFile } from '@/composables/useFileUpload'
import { getAcceptedExtensions } from '@/utils/fileValidation'

const emit = defineEmits<{ uploaded: [file: UploadedFile] }>()

const { progress, error, isUploading, uploadFile } = useFileUpload()
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const acceptedExtensions = getAcceptedExtensions()

async function handleFiles(fileList: FileList | null): Promise<void> {
  const file = fileList?.[0]
  if (!file) return
  const result = await uploadFile(file)
  if (result) emit('uploaded', result)
}

function onDrop(event: DragEvent): void {
  isDragging.value = false
  void handleFiles(event.dataTransfer?.files ?? null)
}

function onInputChange(event: Event): void {
  const target = event.target as HTMLInputElement
  void handleFiles(target.files)
  target.value = ''
}

function openFileDialog(): void {
  fileInput.value?.click()
}
</script>

<template>
  <div class="w-full max-w-xl">
    <div
      class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-10 text-center transition-colors"
      :class="isDragging ? 'border-violet-500 bg-violet-500/10' : 'border-slate-700 bg-slate-900'"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
    >
      <p class="text-slate-300">Drag &amp; drop a .md, .tex, or .zip project file here, or</p>
      <button
        type="button"
        class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500"
        @click="openFileDialog"
      >
        Choose File
      </button>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        :accept="acceptedExtensions.join(',')"
        @change="onInputChange"
      />
      <p class="text-xs text-slate-500">Supported formats: {{ acceptedExtensions.join(', ') }}</p>
    </div>

    <div v-if="isUploading" class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-800">
      <div class="h-full bg-violet-500 transition-all" :style="{ width: progress + '%' }" />
    </div>

    <p v-if="error" class="mt-3 text-sm text-red-400">{{ error }}</p>
  </div>
</template>
