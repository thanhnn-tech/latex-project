/** Extensions accepted by the in-workspace asset "Upload" button (not project-level import). */
export const ASSET_UPLOAD_EXTENSIONS = [
  '.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif',
  '.pdf', '.bib', '.tex', '.sty', '.cls', '.bst',
  '.csv', '.json', '.xml', '.txt',
] as const

export function assetUploadAccept(): string {
  return ASSET_UPLOAD_EXTENSIONS.join(',')
}
