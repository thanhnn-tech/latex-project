import { ref } from 'vue'

export function useAutosave(save: (value: string) => void, delayMs = 600) {
  let timeoutId: ReturnType<typeof setTimeout> | null = null
  const isSaving = ref(false)

  function trigger(value: string): void {
    if (timeoutId) clearTimeout(timeoutId)
    isSaving.value = true
    timeoutId = setTimeout(() => {
      save(value)
      isSaving.value = false
      timeoutId = null
    }, delayMs)
  }

  function flush(value: string): void {
    if (timeoutId) {
      clearTimeout(timeoutId)
      timeoutId = null
    }
    save(value)
    isSaving.value = false
  }

  return { trigger, flush, isSaving }
}
