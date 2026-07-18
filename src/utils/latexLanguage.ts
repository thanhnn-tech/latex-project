import * as monaco from 'monaco-editor'

let registered = false

export function registerLatexLanguage(): void {
  if (registered) return
  registered = true

  monaco.languages.register({ id: 'latex', extensions: ['.tex'], aliases: ['LaTeX', 'latex'] })

  monaco.languages.setMonarchTokensProvider('latex', {
    tokenizer: {
      root: [
        [/%.*$/, 'comment'],
        [/\\[a-zA-Z]+/, 'keyword'],
        [/\\[^a-zA-Z]/, 'keyword'],
        [/[{}]/, 'delimiter.bracket'],
        [/\$[^$]*\$/, 'string'],
        [/&|\\\\/, 'operator'],
      ],
    },
  })

  monaco.languages.setLanguageConfiguration('latex', {
    comments: { lineComment: '%' },
    brackets: [
      ['{', '}'],
      ['[', ']'],
      ['(', ')'],
    ],
    autoClosingPairs: [
      { open: '{', close: '}' },
      { open: '[', close: ']' },
      { open: '(', close: ')' },
    ],
  })
}
