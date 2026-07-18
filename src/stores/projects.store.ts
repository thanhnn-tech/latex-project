import { defineStore } from 'pinia'
import type { Project, ProjectSummary } from '@/types'
import { detectLanguage } from '@/utils/fileLanguage'
import { projectStorageService } from '@/services/storage/httpProjectStorage.service'
import { markdownToLatexConverter } from '@/services/converter/markdownToLatex.converter'
import { httpClient } from '@/services/api/httpClient'
import { PROJECT_TEMPLATES } from '@/constants/templates'

export const useProjectsStore = defineStore('projects', {
  state: () => ({
    summaries: [] as ProjectSummary[],
    searchQuery: '',
    isLoaded: false,
  }),

  getters: {
    filteredSummaries: (state): ProjectSummary[] => {
      const sorted = [...state.summaries].sort((a, b) => b.updatedAt - a.updatedAt)
      const query = state.searchQuery.trim().toLowerCase()
      if (!query) return sorted
      return sorted.filter((summary) => summary.name.toLowerCase().includes(query))
    },
  },

  actions: {
    async refresh(): Promise<void> {
      this.summaries = await projectStorageService.listProjectSummaries()
      this.isLoaded = true
    },

    async createBlankProject(name: string): Promise<string> {
      const project = await projectStorageService.createProject({
        name,
        files: [{ name: 'main.tex', content: '' }],
      })
      await this.refresh()
      return project.id
    },

    async createFromTemplate(templateId: string, name: string): Promise<string> {
      const template = PROJECT_TEMPLATES.find((candidate) => candidate.id === templateId)
      const project = await projectStorageService.createProject({
        name,
        files: [{ name: 'main.tex', content: template?.mainTexContent ?? '' }],
      })
      await this.refresh()
      return project.id
    },

    async createFromUpload(fileName: string, content: string): Promise<string> {
      const projectName = fileName.replace(/\.[^/.]+$/, '') || 'Untitled Project'
      const language = detectLanguage(fileName)

      const files = language === 'latex'
        ? [{ name: fileName, content }]
        : await (async () => {
          const result = await markdownToLatexConverter.convert({ fileName, content })
          return [
            { name: fileName, content },
            { name: result.outputFileName, content: result.content },
          ]
        })()

      const project = await projectStorageService.createProject({ name: projectName, files })
      await this.refresh()
      return project.id
    },

    async createFromZip(file: File): Promise<string> {
      const formData = new FormData()
      formData.append('file', file)
      const project = await httpClient.postForm<Project>('/projects/import-zip', formData)
      await this.refresh()
      return project.id
    },

    async deleteProject(id: string): Promise<void> {
      await projectStorageService.deleteProject(id)
      this.summaries = this.summaries.filter((summary) => summary.id !== id)
    },
  },
})
