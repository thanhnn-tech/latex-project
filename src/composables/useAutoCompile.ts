import { ref } from 'vue'

export function useAutoCompile(compile: (signal: AbortSignal) => Promise<void>, delayMs = 1000) {
  let timeoutId: ReturnType<typeof setTimeout> | null = null
  let controller: AbortController | null = null
  const isScheduled = ref(false)

  function runNow(): void {
    controller?.abort()
    controller = new AbortController()
    isScheduled.value = false
    void compile(controller.signal)
  }

  function trigger(): void {
    if (timeoutId) clearTimeout(timeoutId)
    isScheduled.value = true
    timeoutId = setTimeout(runNow, delayMs)
  }

  function cancel(): void {
    if (timeoutId) {
      clearTimeout(timeoutId)
      timeoutId = null
    }
    controller?.abort()
    isScheduled.value = false
  }

  return { trigger, runNow, cancel, isScheduled }
}
