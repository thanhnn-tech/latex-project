export type FileLanguage = 'markdown' | 'latex' | 'plaintext'

export type CompileStatus = 'idle' | 'compiling' | 'success' | 'failed'

export type FileKind =
  | 'image'
  | 'pdf'
  | 'bibliography'
  | 'latex'
  | 'data'
  | 'archive'
  | 'text'
  | 'directory'

export interface ProjectFile {
  id: string
  /** Full relative path within the project, e.g. "images/logo.png". */
  name: string
  content: string | null
  language: FileLanguage
  kind: FileKind
  isDirectory: boolean
  mimeType: string | null
  size: number
  createdAt: number
  updatedAt: number
}

export interface Project {
  id: string
  name: string
  mainFile: string
  files: ProjectFile[]
  createdAt: number
  updatedAt: number
  compileStatus: CompileStatus
  compiledAt: number | null
  compileDurationMs: number | null
}

export interface ProjectSummary {
  id: string
  name: string
  createdAt: number
  updatedAt: number
  fileCount: number
}

export interface CompileProblem {
  file: string
  line: number | null
  column: number | null
  severity: 'error' | 'warning'
  message: string
}

export interface CompileResult {
  status: CompileStatus
  log: string
  durationMs: number
  problems: CompileProblem[]
}

/** SyncTeX source location -> PDF box, in unscaled PDF points (top-left origin). */
export interface SyncTexForwardResult {
  page: number
  x: number
  y: number
  width: number
  height: number
}

/** SyncTeX PDF coordinates -> source location. */
export interface SyncTexReverseResult {
  file: string
  line: number
  column: number
}

export interface ProjectFileVersion {
  id: number
  size: number
  createdAt: number
}

export interface ProjectFileVersionDetail extends ProjectFileVersion {
  content: string
}
