import { selectOptions } from './fieldBuilderUtils.js'

/**
 * Templates assemble a page kind's pages from parts (see
 * SiteRepository::templates()): `{ type: { variant: [template, …] } }`, where
 * a template is `{ id, name, conditions, header, content, footer }`. The first
 * template whose conditions match an entry is used; the last one is the
 * default and always applies. Key order follows the server, so an unchanged
 * draft compares equal to what was saved.
 */
export const FRAME_DEFAULT = 'default'
export const FRAME_NONE = 'none'
export const FRAME_REGIONS = ['header', 'footer']
export const DEFAULT_TEMPLATE = 'default'
export const DEFAULT_AREA = 'default'
export const MAX_TEMPLATES = 50
export const MAX_CONDITIONS = 10

export function defaultTemplate() {
  return { id: DEFAULT_TEMPLATE, name: 'Default', conditions: [], header: FRAME_DEFAULT, content: DEFAULT_AREA, footer: FRAME_DEFAULT }
}

/** A page kind's templates in order, the default last. */
export function templateList(templates, type, variant) {
  const list = templates?.[type]?.[variant]
  return Array.isArray(list) && list.length ? list : [defaultTemplate()]
}

/** A copy of `templates` with one page kind's list replaced. */
export function withTemplateList(templates, type, variant, list) {
  const next = JSON.parse(JSON.stringify(templates ?? {}))
  next[type] = { ...(next[type] ?? {}), [variant]: JSON.parse(JSON.stringify(list)) }
  return next
}

/** A copy of `templates` with some parts (or the name or conditions) of one template changed. */
export function withTemplate(templates, type, variant, id, changes) {
  return withTemplateList(templates, type, variant, templateList(templates, type, variant).map((template) => template.id === id ? { ...template, ...changes } : template))
}

/** The template a page kind's header or footer shows by default: the one the builder previews when nothing else is chosen. */
export function templateById(templates, type, variant, id) {
  const list = templateList(templates, type, variant)
  return list.find((template) => template.id === id) ?? list[list.length - 1]
}

/** A readable id from a name, numbered when taken (like component ids). */
export function templateId(name, taken) {
  const slug = String(name ?? '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60).replace(/-+$/, '') || 'template'
  let id = slug === DEFAULT_TEMPLATE ? `${slug}-2` : slug
  for (let number = 2; taken(id); number++) id = `${slug}-${number}`
  return id
}

/** A new template named `name`, with the parts of `from`, placed before the default. */
export function withNewTemplate(templates, type, variant, name, from = null) {
  const list = templateList(templates, type, variant)
  const id = templateId(name, (candidate) => list.some((template) => template.id === candidate))
  const source = from ?? list[list.length - 1]
  const template = { id, name, conditions: from ? JSON.parse(JSON.stringify(from.conditions ?? [])) : [], header: source.header, content: source.content, footer: source.footer }
  return { templates: withTemplateList(templates, type, variant, [...list.slice(0, -1), template, list[list.length - 1]]), id }
}

/** Move a template (not the default, which stays last) to `index`. */
export function withTemplateMoved(templates, type, variant, id, index) {
  const list = templateList(templates, type, variant)
  const others = list.slice(0, -1)
  const from = others.findIndex((template) => template.id === id)
  if (from < 0) return templates
  const [moved] = others.splice(from, 1)
  others.splice(Math.max(0, Math.min(index, others.length)), 0, moved)
  return withTemplateList(templates, type, variant, [...others, list[list.length - 1]])
}

export function withoutTemplate(templates, type, variant, id) {
  if (id === DEFAULT_TEMPLATE) return templates
  return withTemplateList(templates, type, variant, templateList(templates, type, variant).filter((template) => template.id !== id))
}

/** Components that can stand in for a header or footer: sections, shared or of this type. */
export function frameComponents(components, type) {
  return (components ?? []).filter((component) => component.root === 'section' && (!component.type || component.type === type))
}

export function templateName(t, template) {
  return template?.id === DEFAULT_TEMPLATE ? t('site.templates.defaultName') : template?.name ?? ''
}

/** What a header or footer shows, in words. */
export function frameLabel(t, value, region, components) {
  if (value === FRAME_NONE) return t(`site.frame.none.${region}`)
  if (value === FRAME_DEFAULT) return t(`site.frame.default.${region}`)
  return components?.find((component) => component.id === value)?.name ?? t('site.frame.missing')
}

export function areaLabel(t, id, areas) {
  if (id === DEFAULT_AREA) return t('site.templates.defaultArea')
  return areas?.find((area) => area.id === id)?.name ?? t('site.templates.missingArea')
}

/** The comparisons a condition on a field of this type can make (mirrors SiteRepository::conditionOps()). */
export function conditionOps(fieldType) {
  if (!fieldType || ['page', 'json', 'repeater'].includes(fieldType)) return []
  const ops = ['equals', 'not_equals', 'contains', 'empty', 'not_empty']
  return ops.filter((op) => {
    if (op === 'equals' || op === 'not_equals') return ['text', 'slug', 'textarea', 'number', 'range', 'boolean', 'select', 'color', 'date', 'datetime'].includes(fieldType)
    if (op === 'contains') return ['text', 'textarea', 'markdown', 'html', 'select'].includes(fieldType)
    return true
  })
}

/** Fields of a content type that conditions can test, with their field type. */
export function conditionFields(type) {
  const fields = Object.entries(type?.fields ?? {})
    .filter(([, config]) => conditionOps(config?.type).length)
    .map(([key, config]) => ({ key, label: config?.label || key, type: config?.type }))
  return fields.some((field) => field.key === 'slug') ? fields : [...fields, { key: 'slug', label: 'Slug', type: 'slug' }]
}

export const needsValue = (op) => !['empty', 'not_empty'].includes(op)

/** A condition in words, e.g. "Category = Tips". */
export function conditionLabel(t, condition, type) {
  const config = type?.fields?.[condition.field]
  const field = config?.label || condition.field
  let value = condition.value
  if (config?.type === 'select') value = selectOptions(config.options).find((option) => option.value === String(value))?.label ?? value
  if (typeof value === 'boolean') value = t(value ? 'site.templates.true' : 'site.templates.false')
  return t(`site.templates.op.${condition.op}`, { field, value: value ?? '' })
}

/** Why a page uses its template, in words (see SiteRepository::resolveTemplate() and PageRenderer::areaTemplate()). */
export function reasonLabel(t, reason, template, type) {
  if (reason === 'conditions') return t('site.templates.reason.conditions', { conditions: (template?.conditions ?? []).map((condition) => conditionLabel(t, condition, type)).join(', ') })
  return t(`site.templates.reason.${reason}`)
}
