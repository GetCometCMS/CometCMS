/**
 * Which header and footer a layout shows (see SiteRepository::frames()):
 * `{ type: { variant: { header|footer: "none" | componentId } } }`, holding
 * only what differs from the site's own header and footer ("default").
 */
export const FRAME_DEFAULT = 'default'
export const FRAME_NONE = 'none'
export const FRAME_REGIONS = ['header', 'footer']

/** The header or footer of one layout: "default", "none" or a component id. */
export function frameValue(frames, type, variant, region) {
  return frames?.[type]?.[variant]?.[region] || FRAME_DEFAULT
}

/** A copy of `frames` with one layout's header or footer set; "default" removes the entry. */
export function withFrame(frames, type, variant, region, value) {
  const next = JSON.parse(JSON.stringify(frames ?? {}))
  const regions = { ...(next[type]?.[variant] ?? {}) }
  if (value === FRAME_DEFAULT) delete regions[region]
  else regions[region] = value
  const variants = { ...(next[type] ?? {}) }
  if (Object.keys(regions).length) variants[variant] = regions
  else delete variants[variant]
  if (Object.keys(variants).length) next[type] = variants
  else delete next[type]
  return next
}

/** Components that can stand in for a header or footer: sections, shared or of this type. */
export function frameComponents(components, type) {
  return (components ?? []).filter((component) => component.root === 'section' && (!component.type || component.type === type))
}

/** What a header or footer shows, in words. */
export function frameLabel(t, value, region, components) {
  if (value === FRAME_NONE) return t(`site.frame.none.${region}`)
  if (value === FRAME_DEFAULT) return t(`site.frame.default.${region}`)
  return components?.find((component) => component.id === value)?.name ?? t('site.frame.missing')
}
