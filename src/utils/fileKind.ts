import {
  PhotoIcon,
  DocumentIcon,
  DocumentTextIcon,
  BookOpenIcon,
  TableCellsIcon,
  ArchiveBoxIcon,
  FolderIcon,
} from '@heroicons/vue/24/outline'
import type { Component } from 'vue'
import type { FileKind } from '@/types'

interface FileKindPresentation {
  icon: Component
  colorClass: string
}

const PRESENTATION_BY_KIND: Record<FileKind, FileKindPresentation> = {
  directory: { icon: FolderIcon, colorClass: 'text-violet-400' },
  image: { icon: PhotoIcon, colorClass: 'text-emerald-400' },
  pdf: { icon: DocumentIcon, colorClass: 'text-red-400' },
  bibliography: { icon: BookOpenIcon, colorClass: 'text-amber-400' },
  latex: { icon: DocumentTextIcon, colorClass: 'text-sky-400' },
  data: { icon: TableCellsIcon, colorClass: 'text-teal-400' },
  archive: { icon: ArchiveBoxIcon, colorClass: 'text-slate-400' },
  text: { icon: DocumentIcon, colorClass: 'text-slate-400' },
}

export function fileKindPresentation(kind: FileKind): FileKindPresentation {
  return PRESENTATION_BY_KIND[kind]
}
