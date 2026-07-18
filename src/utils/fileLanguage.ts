import type { FileLanguage } from '@/types'

export function detectLanguage(fileName: string): FileLanguage {
  const lower = fileName.toLowerCase()
  if (lower.endsWith('.tex')) return 'latex'
  if (lower.endsWith('.md') || lower.endsWith('.markdown')) return 'markdown'
  return 'plaintext'
}
