import { ref } from 'vue'
import { validateUploadFile } from '@/utils/fileValidation'

export interface UploadedFile {
  fileName: string
  file: File
  /** Null for binary payloads (e.g. .zip) that must not be text-decoded. */
  content: string | null
}

export function useFileUpload() {
  const progress = ref(0)
  const error = ref<string | null>(null)
  const isUploading = ref(false)

  function readFileContent(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onprogress = (event) => {
        if (event.lengthComputable) {
          progress.value = Math.round((event.loaded / event.total) * 100)
        }
      }
      reader.onload = () => resolve(String(reader.result ?? ''))
      reader.onerror = () => reject(reader.error ?? new Error('Failed to read file'))
      reader.readAsText(file)
    })
  }

  async function uploadFile(file: File): Promise<UploadedFile | null> {
    error.value = null
    progress.value = 0

    const validation = validateUploadFile(file)
    if (!validation.valid) {
      error.value = validation.error ?? 'Invalid file'
      return null
    }

    isUploading.value = true
    try {
      const isBinary = file.name.toLowerCase().endsWith('.zip')
      const content = isBinary ? null : await readFileContent(file)
      progress.value = 100
      return { fileName: file.name, file, content }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to read file'
      return null
    } finally {
      isUploading.value = false
    }
  }

  return { progress, error, isUploading, uploadFile }
}
