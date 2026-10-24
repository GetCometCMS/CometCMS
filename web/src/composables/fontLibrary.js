/** Match saved IDs without interpreting labels, arbitrary CSS or font names. */
export function referencesFont(value, id) {
  if (value === id) return true
  if (Array.isArray(value)) return value.some((item) => referencesFont(item, id))
  return !!value && typeof value === 'object' && Object.values(value).some((item) => referencesFont(item, id))
}

export function defaultFontSubsets(font) {
  const subsets = font?.subsets ?? []
  const european = ['latin', 'latin-ext'].filter((subset) => subsets.includes(subset))
  return european.length ? european : [font.defSubset || subsets[0]].filter(Boolean)
}

const WEIGHT_NAMES = [
  ['hairline', 100], ['thin', 100], ['extralight', 200], ['ultralight', 200], ['light', 300], ['book', 400], ['regular', 400], ['normal', 400],
  ['medium', 500], ['semibold', 600], ['demibold', 600], ['extrabold', 800], ['ultrabold', 800], ['bold', 700], ['black', 900], ['heavy', 900],
]

/** Suggest face metadata from a file name such as "Brand-SemiBoldItalic.woff2"; the user confirms it before upload. */
export function guessFontFace(fileName) {
  const base = String(fileName).replace(/\.woff2$/i, '')
  const compact = base.toLowerCase().replace(/[\s_-]+/g, '')
  const style = /italic|oblique/.test(compact) ? 'italic' : 'normal'
  if (/variable|\[wght|vf$/.test(compact)) return { family: familyFrom(base), style, min: 100, max: 900 }
  const numeric = base.match(/(?:^|[^0-9])([1-9]00)(?![0-9])/)
  const named = WEIGHT_NAMES.find(([name]) => compact.includes(name))
  const weight = numeric ? Number(numeric[1]) : named ? named[1] : 400
  return { family: familyFrom(base), style, min: weight, max: weight }
}

function familyFrom(base) {
  const words = base.replace(/\[.*?\]/g, '').split(/[-_]/)[0].replace(/([a-z])([A-Z])/g, '$1 $2').trim()
  return words || base
}
