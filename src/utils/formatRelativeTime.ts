export function formatRelativeTime(epochMs: number): string {
  const diffSeconds = Math.round((Date.now() - epochMs) / 1000)
  if (diffSeconds < 60) return 'just now'

  const units: [number, string][] = [
    [60, 'minute'],
    [60, 'hour'],
    [24, 'day'],
    [7, 'week'],
    [4.345, 'month'],
    [12, 'year'],
  ]

  let value = diffSeconds
  let unitLabel = 'second'
  for (const [divisor, label] of units) {
    if (value < divisor) break
    value /= divisor
    unitLabel = label
  }

  const rounded = Math.floor(value)
  return rounded + ' ' + unitLabel + (rounded === 1 ? '' : 's') + ' ago'
}
