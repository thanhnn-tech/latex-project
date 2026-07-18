import type { Project, ProjectSummary } from '@/types'

export interface CreateProjectInput {
  name: string
  mainFile?: string
  files?: Array<{ name: string; content: string }>
}

export interface ProjectStorageService {
  listProjectSummaries(): Promise<ProjectSummary[]>
  loadProject(id: string): Promise<Project | null>
  createProject(input: CreateProjectInput): Promise<Project>
  renameProject(id: string, name: string): Promise<Project>
  deleteProject(id: string): Promise<void>
}
