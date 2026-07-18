import type { ProjectFile } from '@/types'

export interface FileTreeNode {
  path: string
  displayName: string
  isDirectory: boolean
  /** Null for a folder synthesized purely from a descendant's path (no DB row of its own). */
  file: ProjectFile | null
  children: FileTreeNode[]
}

function splitParent(path: string): { displayName: string; parentPath: string } {
  const segments = path.split('/')
  return {
    displayName: segments[segments.length - 1] ?? path,
    parentPath: segments.slice(0, -1).join('/'),
  }
}

export function buildFileTree(files: ProjectFile[]): FileTreeNode[] {
  const root: FileTreeNode[] = []
  const nodesByPath = new Map<string, FileTreeNode>()

  function ensureFolder(path: string): FileTreeNode {
    const existing = nodesByPath.get(path)
    if (existing) return existing

    const { displayName, parentPath } = splitParent(path)
    const node: FileTreeNode = { path, displayName, isDirectory: true, file: null, children: [] }
    nodesByPath.set(path, node)

    if (parentPath) {
      ensureFolder(parentPath).children.push(node)
    } else {
      root.push(node)
    }

    return node
  }

  const sorted = [...files].sort((a, b) => a.name.localeCompare(b.name))

  for (const file of sorted) {
    const { displayName, parentPath } = splitParent(file.name)

    if (file.isDirectory) {
      const existing = nodesByPath.get(file.name)
      if (existing) {
        existing.file = file
        continue
      }
      const node: FileTreeNode = { path: file.name, displayName, isDirectory: true, file, children: [] }
      nodesByPath.set(file.name, node)
      if (parentPath) {
        ensureFolder(parentPath).children.push(node)
      } else {
        root.push(node)
      }
      continue
    }

    const node: FileTreeNode = { path: file.name, displayName, isDirectory: false, file, children: [] }
    if (parentPath) {
      ensureFolder(parentPath).children.push(node)
    } else {
      root.push(node)
    }
  }

  sortTree(root)
  return root
}

function sortTree(nodes: FileTreeNode[]): void {
  nodes.sort((a, b) => {
    if (a.isDirectory !== b.isDirectory) return a.isDirectory ? -1 : 1
    return a.displayName.localeCompare(b.displayName)
  })
  for (const node of nodes) {
    if (node.children.length > 0) sortTree(node.children)
  }
}
