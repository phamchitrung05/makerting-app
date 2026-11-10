const entities = { nbsp: ' ', amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" }

export function htmlToText(html) {
  return String(html ?? '')
    .replace(/<li\b[^>]*>\s*(?:<p\b[^>]*>)?/gi, '\n\n• ')
    .replace(/<(?:br|hr)\b[^>]*>/gi, '\n')
    .replace(/<\/?(?:p|li|h[1-6]|div|blockquote|pre|tr|table|ul|ol)\b[^>]*>/gi, '\n\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&(?:([a-z]+)|#(\d+)|#x([\da-f]+));/gi, (entity, name, decimal, hex) => {
      if (name) return entities[name.toLowerCase()] ?? entity
      const codePoint = Number.parseInt(hex ?? decimal, hex ? 16 : 10)

      return codePoint <= 0x10FFFF ? String.fromCodePoint(codePoint) : entity
    })
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

export function textToHtml(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .split(/\r?\n\s*\n/)
    .map(p => `<p>${p.replace(/\r?\n/g, '<br>')}</p>`)
    .join('')
}

export function mergeRuleEdits(original, initial, current) {
  const rules = { ...original }
  for (const [field, value] of Object.entries(current)) {
    if (value === initial[field]) continue
    const key = field === 'cta' ? 'ending' : field
    if (value) rules[key] = value
    else delete rules[key]
  }

  return rules
}
