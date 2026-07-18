import { httpClient, ApiError } from '@/services/api/httpClient'
import type { Project, ProjectSummary } from '@/types'
import type { CreateProjectInput, ProjectStorageService } from './storage.types'

export class HttpProjectStorageService implements ProjectStorageService {
  async listProjectSummaries(): Promise<ProjectSummary[]> {
    return httpClient.get<ProjectSummary[]>('/projects')
  }

  async loadProject(id: string): Promise<Project | null> {
    try {
      return await httpClient.get<Project>('/projects/' + id)
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) return null
      throw err
    }
  }

  async createProject(input: CreateProjectInput): Promise<Project> {
    return httpClient.post<Project>('/projects', input)
  }

  async renameProject(id: string, name: string): Promise<Project> {
    return httpClient.put<Project>('/projects/' + id, { name })
  }

  async deleteProject(id: string): Promise<void> {
    await httpClient.delete<void>('/projects/' + id)
  }
}

export const projectStorageService = new HttpProjectStorageService()
