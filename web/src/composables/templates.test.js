import { describe, expect, it } from 'vitest'
import {
  DEFAULT_TEMPLATE, FRAME_NONE, areaLabel, conditionFields, conditionLabel, conditionOps, defaultTemplate, frameComponents,
  frameLabel, reasonLabel, templateById, templateId, templateList, withNewTemplate, withTemplate, withTemplateMoved, withoutTemplate,
} from './templates.js'

const t = (key, params = {}) => Object.entries(params).reduce((text, [name, value]) => text.replace(`{${name}}`, value), key)
/** Shows a condition's parts, or the key of texts without them. */
const words = (key, params) => (params ? `${params.field}=${params.value}` : key)
const tips = { id: 'tips', name: 'Tips', conditions: [{ field: 'category', op: 'equals', value: 'tips' }], header: FRAME_NONE, content: 'long', footer: 'default' }

describe('templates', () => {
  it('starts every page kind with the default template', () => {
    expect(templateList({}, 'blog', 'detail')).toEqual([defaultTemplate()])
    expect(templateList({ blog: { detail: [tips, defaultTemplate()] } }, 'blog', 'detail')).toEqual([tips, defaultTemplate()])
    expect(templateById({}, 'blog', 'detail', 'missing').id).toBe(DEFAULT_TEMPLATE)
  })

  it('changes templates without touching the original or the key order', () => {
    const original = { blog: { detail: [tips, defaultTemplate()] } }
    const changed = withTemplate(original, 'blog', 'detail', 'tips', { header: 'default' })
    expect(Object.keys(changed.blog.detail[0])).toEqual(Object.keys(tips))
    expect(changed.blog.detail[0].header).toBe('default')
    expect(original.blog.detail[0].header).toBe(FRAME_NONE)
  })

  it('adds, moves and removes templates while the default stays last', () => {
    let templates = { blog: { detail: [tips, defaultTemplate()] } }
    const added = withNewTemplate(templates, 'blog', 'detail', 'Tips')
    expect(added.id).toBe('tips-2')
    templates = added.templates
    expect(templateList(templates, 'blog', 'detail').map((template) => template.id)).toEqual(['tips', 'tips-2', 'default'])
    expect(templateList(templates, 'blog', 'detail')[1]).toMatchObject({ conditions: [], content: 'default' })

    const copy = withNewTemplate(templates, 'blog', 'detail', 'Tips copy', tips)
    expect(templateList(copy.templates, 'blog', 'detail')[2]).toMatchObject({ id: 'tips-copy', header: FRAME_NONE, content: 'long', conditions: tips.conditions })

    templates = withTemplateMoved(templates, 'blog', 'detail', 'tips-2', 0)
    expect(templateList(templates, 'blog', 'detail').map((template) => template.id)).toEqual(['tips-2', 'tips', 'default'])
    expect(templateList(withTemplateMoved(templates, 'blog', 'detail', 'tips', 9), 'blog', 'detail').at(-1).id).toBe('default')
    expect(withoutTemplate(templates, 'blog', 'detail', 'default')).toBe(templates)
    expect(templateList(withoutTemplate(templates, 'blog', 'detail', 'tips'), 'blog', 'detail').map((template) => template.id)).toEqual(['tips-2', 'default'])
  })

  it('makes readable ids and never reuses "default"', () => {
    expect(templateId('Ünique Läyout!', () => false)).toBe('unique-layout')
    expect(templateId('Default', () => false)).toBe('default-2')
    expect(templateId('', () => false)).toBe('template')
  })

  it('offers the comparisons each field type supports, like the server', () => {
    expect(conditionOps('boolean')).toEqual(['equals', 'not_equals', 'empty', 'not_empty'])
    expect(conditionOps('select')).toEqual(['equals', 'not_equals', 'contains', 'empty', 'not_empty'])
    expect(conditionOps('media')).toEqual(['empty', 'not_empty'])
    expect(conditionOps('repeater')).toEqual([])
    expect(conditionFields({ fields: { title: { type: 'text', label: 'Title' }, steps: { type: 'repeater' } } }).map((field) => field.key)).toEqual(['title', 'slug'])
  })

  it('describes parts, conditions and reasons in words', () => {
    const type = { fields: { category: { type: 'select', label: 'Category', options: { tips: 'Tips & tricks' } }, featured: { type: 'boolean', label: 'Featured' } } }
    expect(conditionLabel(t, tips.conditions[0], type)).toBe('site.templates.op.equals')
    expect(conditionLabel(words, tips.conditions[0], type)).toBe('Category=Tips & tricks')
    expect(conditionLabel(words, { field: 'featured', op: 'equals', value: true }, type)).toBe('Featured=site.templates.true')
    expect(reasonLabel((key, params) => (params?.conditions !== undefined ? `${key}:${params.conditions}` : key), 'conditions', tips, type)).toBe('site.templates.reason.conditions:site.templates.op.equals')
    expect(frameLabel(t, 'promo', 'footer', [{ id: 'promo', name: 'Promo' }])).toBe('Promo')
    expect(frameLabel(t, FRAME_NONE, 'header', [])).toBe('site.frame.none.header')
    expect(areaLabel(t, 'long', [{ id: 'long', name: 'Long read' }])).toBe('Long read')
    expect(areaLabel(t, 'default', [])).toBe('site.templates.defaultArea')
    expect(frameComponents([{ id: 'a', root: 'section', type: '' }, { id: 'b', root: 'column', type: '' }, { id: 'c', root: 'section', type: 'about' }], 'blog').map((component) => component.id)).toEqual(['a'])
  })
})
