export const isLocalized = (value) => value?.type === 'localized' && value.values && typeof value.values === 'object'
const own = (object, key) => Object.prototype.hasOwnProperty.call(object ?? {}, key)
export function resolveLocalized(value, locale, defaultLocale = 'en') {
  if (!isLocalized(value)) return value
  return own(value.values, locale) ? value.values[locale] : value.values[defaultLocale]
}
export function resolveLocalizedTree(value, locale, defaultLocale = 'en') {
  if (isLocalized(value)) return resolveLocalizedTree(resolveLocalized(value, locale, defaultLocale), locale, defaultLocale)
  if (Array.isArray(value)) return value.map((item) => resolveLocalizedTree(item, locale, defaultLocale))
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, resolveLocalizedTree(item, locale, defaultLocale)]))
  return value
}
export function sourceHash(value) {
  let hash = 0x811c9dc5
  for (const byte of new TextEncoder().encode(JSON.stringify(value ?? null))) hash = Math.imul(hash ^ byte, 0x01000193) >>> 0
  return hash.toString(16).padStart(8, '0')
}
export function setLocalized(current, locale, value, defaultLocale = 'en') {
  const result = isLocalized(current) ? structuredCloneValue(current) : { type: 'localized', values: current == null ? {} : { [defaultLocale]: current } }
  result.values[locale] = value
  if (locale !== defaultLocale) result.sources = { ...result.sources, [locale]: sourceHash(result.values[defaultLocale]) }
  return result
}
export function removeLocalized(value, locale) {
  if (!isLocalized(value)) return value
  const result = structuredCloneValue(value)
  delete result.values[locale]
  if (result.sources) delete result.sources[locale]
  return result
}
export function localizedState(value, locale, defaultLocale = 'en') {
  if (!isLocalized(value)) return locale === defaultLocale ? 'shared' : 'inherited'
  if (!own(value.values, locale)) return 'inherited'
  if (locale !== defaultLocale && value.sources?.[locale] !== sourceHash(value.values[defaultLocale])) return 'review'
  return 'translated'
}
export function entryForLocale(entry, locale, defaultLocale = 'en') {
  if (!entry) return {}
  return { ...entry, ...Object.fromEntries(Object.entries(entry.values ?? {}).map(([key, value]) => [key, resolveLocalized(value, locale, defaultLocale)])), ...(entry.publication?.[locale] ?? { status: 'draft', published_at: null }), locale }
}
function structuredCloneValue(value) { return JSON.parse(JSON.stringify(value)) }
