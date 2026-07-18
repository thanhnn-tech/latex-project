<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

export interface ContextMenuItem {
  label: string
  action: string
  danger?: boolean
}

defineProps<{ x: number; y: number; items: ContextMenuItem[] }>()
const emit = defineEmits<{ select: [action: string]; close: [] }>()

const menuRef = ref<HTMLDivElement | null>(null)

function handleClickOutside(event: MouseEvent): void {
  if (menuRef.value && !menuRef.value.contains(event.target as Node)) {
    emit('close')
  }
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div
    ref="menuRef"
    class="fixed z-50 min-w-40 rounded-md border border-slate-700 bg-slate-900 py-1 shadow-xl"
    :style="{ left: x + 'px', top: y + 'px' }"
  >
    <button
      v-for="item in items"
      :key="item.action"
      type="button"
      class="block w-full px-3 py-1.5 text-left text-xs"
      :class="item.danger ? 'text-red-400 hover:bg-red-500/10' : 'text-slate-300 hover:bg-slate-800'"
      @click="emit('select', item.action)"
    >
      {{ item.label }}
    </button>
  </div>
</template>
