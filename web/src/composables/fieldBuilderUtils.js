import { isLocalized, resolveLocalized, setLocalized } from './localizedValues.js'
export function normalizeKey(value) {
  return String(value ?? '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9_]+/g, '_')
    .replace(/^_+|_+$/g, '')
}

export function toSlug(value) {
  return String(value ?? '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

export function uniqueKey(base, used, reserved = new Set()) {
  let candidate = base
  let suffix = 2

  while (reserved.has(candidate) || used.has(candidate)) {
    candidate = `${base}_${suffix}`
    suffix++
  }

  return candidate
}

export function parseSelectOptions(value) {
  return String(value ?? '')
    .split('\n')
    .map((s) => s.trim())
    .filter(Boolean)
    .map((line) => {
      const colonIdx = line.indexOf(':')
      if (colonIdx > 0) {
        const key = line.slice(0, colonIdx).trim()
        const label = line.slice(colonIdx + 1).trim()
        if (key) return { key, label: label || key }
      }
      return { key: line, label: line }
    })
}

export function optionsToText(options, defaultLocale = 'en') {
  if (Array.isArray(options)) {
    return options.join('\n')
  }
  if (options && typeof options === 'object') {
    return Object.entries(options)
      .map(([key, value]) => { const label = resolveLocalized(value, defaultLocale, defaultLocale); return key === label ? key : `${key}:${label}` })
      .join('\n')
  }
  return ''
}

export function serializeSelectOptions(parsed, previous = {}, defaultLocale = 'en') {
  const localized = parsed.some(({ key }) => isLocalized(previous?.[key]))
  if (localized) return Object.fromEntries(parsed.map(({ key, label }) => {
    const old = previous?.[key]
    return [key, isLocalized(old) ? (resolveLocalized(old, defaultLocale, defaultLocale) === label ? old : setLocalized(old, defaultLocale, label, defaultLocale)) : label]
  }))
  if (parsed.every(({ key, label }) => key === label)) {
    return parsed.map(({ key }) => key)
  }
  return Object.fromEntries(parsed.map(({ key, label }) => [key, label]))
}

export function selectOptions(options) {
  if (options && typeof options === 'object' && !Array.isArray(options)) {
    return Object.entries(options).map(([key, label]) => ({
      value: String(key),
      label: String(resolveLocalized(label, 'en', 'en') ?? key),
    }))
  }

  return (options ?? []).map((option) => ({
    value: String(option),
    label: String(option),
  }))
}

export function numberOr(value, fallback) {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback
}

export function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max)
}

export function normalizeDisplayDecimals(value) {
  if (value === 'full') return 'full'
  const number = Number(value)
  return Number.isInteger(number) && number >= 0 && number <= 3 ? number : 0
}

export function rangeDefaults(field = {}) {
  const min = numberOr(field.min, 0)
  const max = Math.max(numberOr(field.max, 100), min)
  const fallbackDefault =
    field.default === '' || field.default === null || field.default === undefined
      ? min
      : field.default
  const defaultValue = clamp(numberOr(fallbackDefault, min), min, max)
  const rawStep = numberOr(field.step, 1)
  const step = rawStep > 0 ? rawStep : 1
  const displayDecimals = normalizeDisplayDecimals(field.display_decimals)

  return { min, step, default: defaultValue, max, display_decimals: displayDecimals }
}
