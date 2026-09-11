export const DEFAULT_THEME = 'blue'

export const THEMES = [
  { value: 'blue', label: 'Blue', logo: 'cms-logo-blue.png' },
  { value: 'green', label: 'Green', logo: 'cms-logo-green.png' },
  { value: 'purple', label: 'Purple', logo: 'cms-logo-purple.png' },
  { value: 'orange', label: 'Orange', logo: 'cms-logo-orange.png' },
  { value: 'red', label: 'Red', logo: 'cms-logo-red.png' },
  { value: 'cyan', label: 'Cyan', logo: 'cms-logo-cyan.png' },
  { value: 'dark', label: 'Dark', logo: 'cms-logo-white.png' },
]

export function normalizeTheme(theme) {
  const value = String(theme ?? '').toLowerCase()

  return THEMES.some((item) => item.value === value) ? value : DEFAULT_THEME
}

export function applyTheme(theme) {
  document.documentElement.dataset.theme = normalizeTheme(theme)
}

export function logoForTheme(theme, assetBase = '') {
  const normalized = normalizeTheme(theme)
  const config = THEMES.find((item) => item.value === normalized)

  return `${assetBase}img/${config?.logo ?? 'cms-logo-blue.png'}`
}
