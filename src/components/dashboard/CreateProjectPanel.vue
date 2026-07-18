<script setup lang="ts">
import { computed, ref } from 'vue'
import { PROJECT_TEMPLATES, TEMPLATE_CATEGORIES, type TemplateCategory } from '@/constants/templates'

const emit = defineEmits<{
  createBlank: [name: string]
  createFromTemplate: [templateId: string, name: string]
}>()

const projectName = ref('')
const activeCategory = ref<TemplateCategory>('general')

const visibleTemplates = computed(() =>
  PROJECT_TEMPLATES.filter((template) => template.category === activeCategory.value),
)

function handleCreateBlank(): void {
  emit('createBlank', projectName.value.trim() || 'Untitled Project')
  projectName.value = ''
}

function handleCreateFromTemplate(templateId: string): void {
  emit('createFromTemplate', templateId, projectName.value.trim() || 'Untitled Project')
  projectName.value = ''
}
</script>

<template>
  <div class="flex flex-col gap-4 rounded-lg border border-slate-800 bg-slate-900 p-5">
    <div>
      <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
        Project name
      </label>
      <input
        v-model="projectName"
        type="text"
        placeholder="Untitled Project"
        class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-600 outline-none focus:border-violet-500"
      />
    </div>

    <button
      type="button"
      class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500"
      @click="handleCreateBlank"
    >
      Create Blank Project
    </button>

    <div>
      <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Or start from a template</p>

      <div class="mb-3 flex flex-wrap gap-1.5">
        <button
          v-for="category in TEMPLATE_CATEGORIES"
          :key="category.id"
          type="button"
          class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
          :class="
            activeCategory === category.id
              ? 'bg-violet-600 text-white'
              : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-slate-200'
          "
          @click="activeCategory = category.id"
        >
          {{ category.label }}
        </button>
      </div>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <button
          v-for="template in visibleTemplates"
          :key="template.id"
          type="button"
          class="group flex flex-col overflow-hidden rounded-md border border-slate-800 text-left hover:border-violet-600"
          @click="handleCreateFromTemplate(template.id)"
        >
          <div class="aspect-[3/4] w-full overflow-hidden bg-slate-950">
            <img
              :src="template.previewImage"
              :alt="template.name + ' preview'"
              class="h-full w-full object-cover object-top transition-transform group-hover:scale-105"
              loading="lazy"
            />
          </div>
          <div class="bg-slate-900 px-2.5 py-2">
            <span class="block text-xs font-medium text-slate-200">{{ template.name }}</span>
            <span class="block text-[11px] leading-snug text-slate-500">{{ template.description }}</span>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
