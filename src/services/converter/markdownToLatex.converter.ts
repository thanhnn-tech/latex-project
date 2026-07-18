import type { ConversionInput, ConversionResult, ConverterService } from './converter.types'

type ListMode = null | 'itemize' | 'enumerate'

const HEADING_COMMAND_BY_LEVEL: Record<number, string> = {
  1: 'section',
  2: 'subsection',
  3: 'subsubsection',
  4: 'paragraph',
  5: 'subparagraph',
  6: 'subparagraph',
}

const STASH_PREFIX = 'ZZLATEXSTASHZZ'
const STASH_PATTERN = new RegExp(`${STASH_PREFIX}(\\d+)ZZ`, 'g')

/**
 * LaTeX reserves these characters; escaping happens in one pass over the
 * original string so replacement text is never re-escaped.
 */
function escapeLatex(text: string): string {
  return text.replace(/[\\&%$#_{}~^]/g, (char) => {
    switch (char) {
      case '\\':
        return '\\textbackslash{}'
      case '~':
        return '\\textasciitilde{}'
      case '^':
        return '\\textasciicircum{}'
      default:
        return '\\' + char
    }
  })
}

/**
 * Code spans, links and images are pulled out before escaping (their URLs/raw
 * content must not be LaTeX-escaped) and stashed back in once the surrounding
 * plain text has been escaped and bold/italic markers have been applied.
 */
function convertInline(rawText: string): string {
  const stashed: string[] = []
  const stash = (fragment: string): string => {
    stashed.push(fragment)
    return STASH_PREFIX + (stashed.length - 1) + 'ZZ'
  }

  let text = rawText
    .replace(/`([^`]+)`/g, (_match, code: string) => stash('\\texttt{' + escapeLatex(code) + '}'))
    .replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (_match, alt: string, src: string) =>
      stash(
        '\\begin{figure}[h]\n\\centering\n\\includegraphics[width=0.8\\linewidth]{' +
          src +
          '}\n\\caption{' +
          escapeLatex(alt) +
          '}\n\\end{figure}',
      ),
    )
    .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_match, label: string, href: string) =>
      stash('\\href{' + href + '}{' + escapeLatex(label) + '}'),
    )

  text = escapeLatex(text)
  text = text
    .replace(/\*\*([^*]+)\*\*/g, (_match, bold: string) => '\\textbf{' + bold + '}')
    .replace(/\*([^*]+)\*/g, (_match, italic: string) => '\\textit{' + italic + '}')

  return text.replace(STASH_PATTERN, (_match, index: string) => stashed[Number(index)] ?? '')
}

/** A GFM table row: any non-blank line containing at least one pipe. */
function isTableRow(line: string): boolean {
  const trimmed = line.trim()
  return trimmed.length > 0 && trimmed.includes('|')
}

/** The `|---|:---:|---|` divider that follows a table header row. */
function isTableSeparatorRow(line: string): boolean {
  const trimmed = line.trim()
  if (!trimmed.includes('-')) return false
  return /^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?$/.test(trimmed)
}

function splitTableRow(line: string): string[] {
  let trimmed = line.trim()
  if (trimmed.startsWith('|')) trimmed = trimmed.slice(1)
  if (trimmed.endsWith('|')) trimmed = trimmed.slice(0, -1)
  return trimmed.split('|').map((cell) => cell.trim())
}

function buildTableLatex(headerCells: string[], bodyRows: string[][]): string {
  const columnCount = headerCells.length
  const colSpec = '|' + new Array(columnCount).fill('X').join('|') + '|'
  const renderRow = (cells: string[]): string => {
    const padded = new Array(columnCount).fill('').map((_, i) => cells[i] ?? '')
    return padded.map((cell) => convertInline(cell)).join(' & ') + ' \\\\'
  }

  const lines = [
    '\\begin{tabularx}{\\linewidth}{' + colSpec + '}',
    '\\hline',
    headerCells.map((cell) => '\\textbf{' + convertInline(cell) + '}').join(' & ') + ' \\\\',
    '\\hline',
  ]
  for (const row of bodyRows) {
    lines.push(renderRow(row), '\\hline')
  }
  lines.push('\\end{tabularx}')
  return lines.join('\n')
}

function convertBody(markdown: string): string {
  const lines = markdown.replace(/\r\n/g, '\n').split('\n')
  const output: string[] = []
  let listMode: ListMode = null
  let inCodeBlock = false
  let paragraphBuffer: string[] = []

  const flushParagraph = (): void => {
    if (paragraphBuffer.length > 0) {
      output.push(convertInline(paragraphBuffer.join(' ')), '')
      paragraphBuffer = []
    }
  }

  const closeList = (): void => {
    if (listMode) {
      output.push('\\end{' + listMode + '}', '')
      listMode = null
    }
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trimEnd()

    if (line.trim().startsWith('```')) {
      if (inCodeBlock) {
        output.push('\\end{verbatim}', '')
        inCodeBlock = false
      } else {
        flushParagraph()
        closeList()
        inCodeBlock = true
        output.push('\\begin{verbatim}')
      }
      continue
    }

    if (inCodeBlock) {
      output.push(line)
      continue
    }

    if (line.trim() === '') {
      flushParagraph()
      closeList()
      continue
    }

    const headingMatch = /^(#{1,6})\s+(.*)$/.exec(line)
    if (headingMatch) {
      flushParagraph()
      closeList()
      const level = headingMatch[1].length
      const command = HEADING_COMMAND_BY_LEVEL[level] ?? 'paragraph'
      output.push('\\' + command + '{' + convertInline(headingMatch[2]) + '}', '')
      continue
    }

    if (isTableRow(line) && i + 1 < lines.length && isTableSeparatorRow(lines[i + 1])) {
      flushParagraph()
      closeList()
      const headerCells = splitTableRow(line)
      const bodyRows: string[][] = []
      i += 2
      while (i < lines.length && isTableRow(lines[i])) {
        bodyRows.push(splitTableRow(lines[i]))
        i += 1
      }
      i -= 1
      output.push(buildTableLatex(headerCells, bodyRows), '')
      continue
    }

    const unorderedMatch = /^[-*+]\s+(.*)$/.exec(line)
    if (unorderedMatch) {
      flushParagraph()
      if (listMode !== 'itemize') {
        closeList()
        output.push('\\begin{itemize}')
        listMode = 'itemize'
      }
      output.push('  \\item ' + convertInline(unorderedMatch[1]))
      continue
    }

    const orderedMatch = /^\d+\.\s+(.*)$/.exec(line)
    if (orderedMatch) {
      flushParagraph()
      if (listMode !== 'enumerate') {
        closeList()
        output.push('\\begin{enumerate}')
        listMode = 'enumerate'
      }
      output.push('  \\item ' + convertInline(orderedMatch[1]))
      continue
    }

    const blockquoteMatch = /^>\s?(.*)$/.exec(line)
    if (blockquoteMatch) {
      flushParagraph()
      closeList()
      output.push('\\begin{quote}\n' + convertInline(blockquoteMatch[1]) + '\n\\end{quote}', '')
      continue
    }

    if (/^([-*_])\1{2,}$/.test(line.replace(/\s+/g, ''))) {
      flushParagraph()
      closeList()
      output.push('\\noindent\\rule{\\linewidth}{0.4pt}', '')
      continue
    }

    closeList()
    paragraphBuffer.push(line.trim())
  }

  flushParagraph()
  closeList()
  if (inCodeBlock) {
    output.push('\\end{verbatim}')
  }

  return output
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

function buildDocument(title: string, body: string): string {
  const lines = [
    '\\documentclass{article}',
    '\\usepackage[utf8]{inputenc}',
    '\\usepackage{hyperref}',
    '\\usepackage{graphicx}',
    '\\usepackage{tabularx}',
    '',
    '\\title{' + escapeLatex(title) + '}',
    '\\date{}',
    '',
    '\\begin{document}',
    '',
    '\\maketitle',
    '',
    body,
    '',
    '\\end{document}',
    '',
  ]
  return lines.join('\n')
}

export class MarkdownToLatexConverter implements ConverterService {
  async convert(input: ConversionInput): Promise<ConversionResult> {
    const warnings: string[] = []
    if (!input.content.trim()) {
      warnings.push('The source file is empty — generated an empty document skeleton.')
    }

    const title = input.fileName.replace(/\.[^/.]+$/, '')
    const body = convertBody(input.content)
    const document = buildDocument(title, body)

    return {
      outputFileName: 'main.tex',
      content: document,
      warnings,
    }
  }
}

export const markdownToLatexConverter = new MarkdownToLatexConverter()
