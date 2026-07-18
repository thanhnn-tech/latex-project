export interface FileValidationResult {
  valid: boolean
  error?: string
}

const ACCEPTED_EXTENSIONS = ['.md', '.markdown', '.tex', '.zip'] as const

export function getAcceptedExtensions(): readonly string[] {
  return ACCEPTED_EXTENSIONS
}

export function validateUploadFile(file: File): FileValidationResult {
  const name = file.name.toLowerCase()
  const isAccepted = ACCEPTED_EXTENSIONS.some((extension) => name.endsWith(extension))

  if (!isAccepted) {
    return {
      valid: false,
      error: `Unsupported file type. Accepted formats: ${ACCEPTED_EXTENSIONS.join(', ')}`,
    }
  }

  return { valid: true }
}
