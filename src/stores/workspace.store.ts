import { defineStore } from 'pinia'
import type {
  CompileProblem,
  CompileResult,
  CompileStatus,
  FileLanguage,
  Project,
  ProjectFile,
  ProjectFileVersion,
  ProjectFileVersionDetail,
  SyncTexForwardResult,
  SyncTexReverseResult,
} from '@/types'
import { projectStorageService } from '@/services/storage/httpProjectStorage.service'
import { httpClient, apiUrl, ApiError } from '@/services/api/httpClient'

interface PendingJump {
  fileId: string
  line: number
}

interface PendingContentReset {
  fileId: string
  content: string
}

export const useWorkspaceStore = defineStore('workspace', {
  state: () => ({
    project: null as Project | null,
    openTabIds: [] as string[],
    activeTabId: null as string | null,
    compileStatus: 'idle' as CompileStatus,
    compileLog: '',
    problems: [] as CompileProblem[],
    pdfVersion: 0,
    pendingJump: null as PendingJump | null,
    previewFileId: null as string | null,
    pdfSyncTarget: null as SyncTexForwardResult | null,
    fileVersions: [] as ProjectFileVersion[],
    fileVersionsFileId: null as string | null,
    pendingContentReset: null as PendingContentReset | null,
  }),

  getters: {
    files: (state): ProjectFile[] => state.project?.files ?? [],
    openTabs: (state): ProjectFile[] =>
      state.openTabIds
        .map((id) => state.project?.files.find((file) => file.id === id))
        .filter((file): file is ProjectFile => Boolean(file)),
    activeFile: (state): ProjectFile | null => {
      if (!state.activeTabId) return null
      return state.project?.files.find((file) => file.id === state.activeTabId) ?? null
    },
    previewFile: (state): ProjectFile | null => {
      if (!state.previewFileId) return null
      return state.project?.files.find((file) => file.id === state.previewFileId) ?? null
    },
    hasProject: (state): boolean => state.project !== null,
    pdfUrl: (state): string | null => {
      if (!state.project) return null
      return apiUrl('/projects/' + state.project.id + '/pdf') + '?v=' + state.pdfVersion
    },
    rawFileUrl: (state) => (fileId: string): string | null => {
      if (!state.project) return null
      return apiUrl('/projects/' + state.project.id + '/files/' + fileId + '/raw')
    },
  },

  actions: {
    async loadProject(id: string): Promise<boolean> {
      const project = await projectStorageService.loadProject(id)
      this.project = project
      const firstFileId = project?.files[0]?.id ?? null
      this.openTabIds = firstFileId ? [firstFileId] : []
      this.activeTabId = firstFileId
      this.compileStatus = project?.compileStatus ?? 'idle'
      this.compileLog = ''
      this.problems = []
      this.pdfVersion = 0

      if (project) {
        await this.refreshProblems()
      }

      return project !== null
    },

    openTab(fileId: string): void {
      if (!this.openTabIds.includes(fileId)) {
        this.openTabIds.push(fileId)
      }
      this.activeTabId = fileId
    },

    closeTab(fileId: string): void {
      const index = this.openTabIds.indexOf(fileId)
      if (index === -1) return
      this.openTabIds.splice(index, 1)
      if (this.activeTabId === fileId) {
        this.activeTabId = this.openTabIds[index] ?? this.openTabIds[index - 1] ?? null
      }
    },

    setActiveTab(fileId: string): void {
      if (this.openTabIds.includes(fileId)) {
        this.activeTabId = fileId
      }
    },

    async createFile(name: string, _language: FileLanguage = 'plaintext', content = ''): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const created = await httpClient.post<ProjectFile>('/projects/' + projectId + '/files', { name, content })
      this.project.files.push(created)
      this.project.updatedAt = Date.now()
      this.openTab(created.id)
    },

    async createFolder(path: string): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const folder = await httpClient.post<ProjectFile>('/projects/' + projectId + '/folders', { path })
      this.project.files.push(folder)
      this.project.updatedAt = Date.now()
    },

    async uploadFile(file: File, path?: string): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const formData = new FormData()
      formData.append('file', file)
      if (path) formData.append('path', path)

      const uploaded = await httpClient.postForm<ProjectFile>('/projects/' + projectId + '/files/upload', formData)
      this.project.files.push(uploaded)
      this.project.updatedAt = Date.now()

      // Only text-like kinds make sense as a Monaco tab — binary assets (images,
      // PDFs, archives) are surfaced via the file tree's own preview/download
      // handling instead of opening an editor tab with an empty text model.
      if (uploaded.kind === 'latex' || uploaded.kind === 'bibliography' || uploaded.kind === 'data' || uploaded.kind === 'text') {
        this.openTab(uploaded.id)
      } else if (uploaded.kind === 'image') {
        this.openImagePreview(uploaded.id)
      }
    },

    async removeFile(id: string): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const file = this.project.files.find((candidate) => candidate.id === id)
      if (!file) return

      const removedPrefix = file.name + '/'
      this.project.files = this.project.files.filter(
        (candidate) => candidate.id !== id && !(file.isDirectory && candidate.name.startsWith(removedPrefix)),
      )
      this.closeTab(id)
      await httpClient.delete('/projects/' + projectId + '/files/' + id)
    },

    async moveFile(id: string, newPath: string): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const file = this.project.files.find((candidate) => candidate.id === id)
      if (!file) return
      const oldPath = file.name
      const wasDirectory = file.isDirectory

      const updated = await httpClient.put<ProjectFile>(
        '/projects/' + projectId + '/files/' + id + '/move',
        { path: newPath },
      )
      Object.assign(file, updated)

      if (wasDirectory) {
        const oldPrefix = oldPath + '/'
        for (const candidate of this.project.files) {
          if (candidate.id !== id && candidate.name.startsWith(oldPrefix)) {
            candidate.name = newPath + candidate.name.slice(oldPath.length)
          }
        }
      }
    },

    async renameFile(id: string, newDisplayName: string): Promise<void> {
      const file = this.project?.files.find((candidate) => candidate.id === id)
      if (!file) return
      const segments = file.name.split('/')
      segments[segments.length - 1] = newDisplayName
      await this.moveFile(id, segments.join('/'))
    },

    async duplicateFile(id: string): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const duplicated = await httpClient.post<ProjectFile>('/projects/' + projectId + '/files/' + id + '/duplicate')

      if (duplicated.isDirectory) {
        // Descendant rows were created server-side too — simplest to resync in full.
        await this.loadProject(projectId)
      } else {
        this.project.files.push(duplicated)
        this.project.updatedAt = Date.now()
      }
    },

    async updateFileContent(id: string, content: string): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const file = this.project.files.find((candidate) => candidate.id === id)
      if (!file) return
      file.content = content
      file.updatedAt = Date.now()
      await httpClient.put('/projects/' + projectId + '/files/' + id, { content })
    },

    openImagePreview(fileId: string): void {
      this.previewFileId = fileId
    },

    closeImagePreview(): void {
      this.previewFileId = null
    },

    async compile(signal?: AbortSignal): Promise<void> {
      if (!this.project) return
      this.compileStatus = 'compiling'
      try {
        const result = await httpClient.post<CompileResult>(
          '/projects/' + this.project.id + '/compile',
          undefined,
          { signal },
        )
        this.compileStatus = result.status
        this.compileLog = result.log
        this.problems = result.problems
        if (result.status === 'success') {
          this.pdfVersion += 1
        }
      } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') return
        this.compileStatus = 'failed'
        throw err
      }
    },

    async refreshProblems(): Promise<void> {
      if (!this.project) return
      this.problems = await httpClient.get<CompileProblem[]>('/projects/' + this.project.id + '/problems')
    },

    requestJump(fileName: string, line: number): void {
      const file = this.project?.files.find((candidate) => candidate.name === fileName)
      if (!file) return
      this.pendingJump = { fileId: file.id, line }
    },

    clearJump(): void {
      this.pendingJump = null
    },

    /** Editor -> PDF: locate a source line in the compiled PDF. No-ops if SyncTeX data isn't available yet. */
    async syncForwardToPdf(fileName: string, line: number): Promise<void> {
      if (!this.project || this.compileStatus !== 'success') return
      const query = new URLSearchParams({ file: fileName, line: String(line) })
      try {
        this.pdfSyncTarget = await httpClient.get<SyncTexForwardResult>(
          '/projects/' + this.project.id + '/synctex/forward?' + query.toString(),
        )
      } catch (err) {
        if (!(err instanceof ApiError && err.status === 404)) throw err
      }
    },

    clearPdfSyncTarget(): void {
      this.pdfSyncTarget = null
    },

    /** PDF -> editor: clicking a spot in the PDF jumps the editor to the matching source line. */
    async syncReverseToEditor(page: number, x: number, y: number): Promise<void> {
      if (!this.project || this.compileStatus !== 'success') return
      const query = new URLSearchParams({ page: String(page), x: String(x), y: String(y) })
      try {
        const result = await httpClient.get<SyncTexReverseResult>(
          '/projects/' + this.project.id + '/synctex/reverse?' + query.toString(),
        )
        this.requestJump(result.file, result.line)
      } catch (err) {
        if (!(err instanceof ApiError && err.status === 404)) throw err
      }
    },

    async loadFileVersions(fileId: string): Promise<void> {
      if (!this.project) return
      this.fileVersionsFileId = fileId
      this.fileVersions = await httpClient.get<ProjectFileVersion[]>(
        '/projects/' + this.project.id + '/files/' + fileId + '/versions',
      )
    },

    async restoreFileVersion(fileId: string, versionId: number): Promise<void> {
      if (!this.project) return
      const projectId = this.project.id
      const file = this.project.files.find((candidate) => candidate.id === fileId)
      if (!file) return

      const restored = await httpClient.post<ProjectFile>(
        '/projects/' + projectId + '/files/' + fileId + '/versions/' + versionId + '/restore',
      )
      Object.assign(file, restored)
      this.pendingContentReset = { fileId, content: restored.content ?? '' }

      await this.loadFileVersions(fileId)
    },

    async loadVersionContent(fileId: string, versionId: number): Promise<string> {
      if (!this.project) return ''
      const version = await httpClient.get<ProjectFileVersionDetail>(
        '/projects/' + this.project.id + '/files/' + fileId + '/versions/' + versionId,
      )
      return version.content
    },

    clearContentReset(): void {
      this.pendingContentReset = null
    },
  },
})
