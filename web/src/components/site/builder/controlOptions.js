// How builder options are presented: icon bars for directions and edges,
// compact size scales. Anything not covered here falls
// back to a segmented control or a select.

/** "Use the default" — shared by every bar that has an empty option. */
export const DEFAULT_ICON = 'mdi:circle-off-outline'

const ICONS = {
  'style.align': { left: 'mdi:format-align-left', center: 'mdi:format-align-center', right: 'mdi:format-align-right' },
  'props.vertical_align': { start: 'mdi:format-vertical-align-top', center: 'mdi:format-vertical-align-center', end: 'mdi:format-vertical-align-bottom' },
  'props.align': { stretch: 'mdi:arrow-expand-vertical', start: 'mdi:align-vertical-top', center: 'mdi:align-vertical-center', end: 'mdi:align-vertical-bottom' },
  'props.justify': { start: 'mdi:align-horizontal-left', center: 'mdi:align-horizontal-center', end: 'mdi:align-horizontal-right', between: 'mdi:arrow-left-right' },
  'style.border': { none: 'mdi:border-none-variant', all: 'mdi:border-all-variant', top: 'mdi:border-top-variant', bottom: 'mdi:border-bottom-variant' },
  'style.radius': { none: 'mdi:square-outline', theme: 'mdi:square-rounded-outline', full: 'mdi:circle-outline' },
  'style.max_width': { narrow: 'mdi:arrow-collapse-horizontal', prose: 'mdi:text-long' },
  'props.orientation': { horizontal: 'mdi:dots-horizontal', vertical: 'mdi:dots-vertical' },
  'props.sort_order': { desc: 'mdi:sort-descending', asc: 'mdi:sort-ascending' },
  'props.display': { cards: 'mdi:view-grid-outline', list: 'mdi:format-list-bulleted' },
  'props.fit': { cover: 'mdi:crop', contain: 'mdi:fit-to-screen-outline' },
  'style.overlay': { none: 'mdi:image-outline', light: 'mdi:brightness-5', dark: 'mdi:brightness-3' },
}

/** Size scales read best as short labels: – 0 XS S M L XL 2XL. */
const SCALE = { '': '–', none: '0', xs: 'XS', sm: 'S', md: 'M', lg: 'L', xl: 'XL', '2xl': '2XL' }

const WEIGHTS = { normal: 400, medium: 500, semibold: 600, bold: 700 }

/** Options whose values are theme spacing tokens. */
const SPACE_KEYS = new Set(['style.padding_y', 'style.padding_x', 'style.margin_bottom'])

/** The theme value behind a token option, e.g. "16px", for tooltips. */
function tokenHint(key, value, kind, theme) {
  if (!theme) return ''
  const isSpace = SPACE_KEYS.has(key) || (key === 'props.size' && kind === 'spacer')
  if (isSpace && theme.space?.[value] !== undefined) return `${theme.space[value]}px`
  if (key !== 'style.radius') return ''
  if (theme.radii?.[value] !== undefined) return `${theme.radii[value]}px`
  if (value === 'theme') {
    if (theme.radius === 'none') return '0px'
    return theme.radii?.[theme.radius] !== undefined ? `${theme.radii[theme.radius]}px` : ''
  }
  return ''
}

export const DEVICE_ICONS = { mobile: 'mdi:cellphone', tablet: 'mdi:tablet', desktop: 'mdi:monitor' }

/**
 * Bar options for an enum, or null when a bar would not fit.
 * `title(value)` returns the translated name of an option.
 */
export function barOptions(group, name, values, { title, theme = null, kind = '' }) {
  const key = `${group}.${name}`
  const describe = (value) => ({
    value,
    title: [title(value), tokenHint(key, value, kind, theme)].filter(Boolean).join(' · '),
  })

  // Icons where they say more than a word; size tokens keep their short label.
  if (ICONS[key] && values.every((value) => value === '' || ICONS[key][value] || value in SCALE)) {
    return values.map((value) => {
      if (value === '') return { ...describe(value), icon: DEFAULT_ICON }
      return ICONS[key][value] ? { ...describe(value), icon: ICONS[key][value] } : { ...describe(value), label: SCALE[value] }
    })
  }

  if (values.length <= 8 && values.every((value) => value in SCALE)) {
    return values.map((value) => ({ ...describe(value), label: SCALE[value] }))
  }

  if (values.every((value) => /^h[1-6]$/.test(value))) {
    return values.map((value) => ({ ...describe(value), label: value.toUpperCase() }))
  }

  if (name === 'font_weight') {
    return values.map((value) => (value === ''
      ? { ...describe(value), icon: DEFAULT_ICON }
      : { ...describe(value), label: 'Aa', style: { fontWeight: WEIGHTS[value] } }))
  }

  return null
}

/** Small integer ranges (e.g. 1–6 columns) as a bar of numbers. */
export function numberBar(min, max) {
  if (max - min > 5) return null
  return Array.from({ length: max - min + 1 }, (_, index) => {
    const value = min + index
    return { value, title: String(value), label: String(value) }
  })
}
