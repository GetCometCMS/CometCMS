import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it } from 'vitest'
import { createSiteBuilder } from './useSiteBuilder.js'
import { reference } from './smartValues.js'

async function builderFixture() {
  let builder
  await renderToString(createSSRApp({ setup() { builder = createSiteBuilder({ t: (key) => key }); return () => null } }))
  builder.spec.value = { root: ['section'], item_elements: ['field', 'text', 'heading'], kinds: {
    section: { props: {}, children: ['row'] }, row: { props: {}, children: ['column'] }, column: { props: {}, children: ['text', 'heading', 'field'] },
    text: { props: { html: { default: '' } }, children: [] }, heading: { props: { text: { default: '' } }, children: [] }, field: { props: { source: { default: '' } }, children: ['text', 'heading', 'field'] },
  } }
  builder.type.value = { name: 'posts', fields: { intro: { type: 'text' } } }
  builder.types.value = [builder.type.value, { name: 'company', singleton: true, fields: { steps: { type: 'repeater', subfields: [{ key: 'detail', type: 'textarea' }] } } }]
  const repeater = builder.make('field', { source: { ...reference('steps', 'single'), collection: 'company' } }, {}, [builder.make('text', { html: reference('detail', 'item') })])
  builder.load([builder.make('section', {}, {}, [builder.make('row', {}, {}, [builder.make('column', {}, {}, [builder.make('text', { html: reference('intro') }), repeater])])])])
  return builder
}

describe('builder integration with smart values', () => {
  it('retains references through undo, redo, copy, paste, save and reload', async () => {
    const builder = await builderFixture()
    const original = builder.nodes.value[0].children[0].children[0].children[0]
    builder.select(original.id)
    builder.updateValue(original.id, 'props', 'html', { type: 'template', parts: ['Welcome ', reference('title', 'site')] })
    expect(builder.nodeSummary(builder.selected.value)).toBe('Welcome [site → title]')
    builder.undo()
    expect(builder.selected.value.props.html).toEqual(reference('intro'))
    builder.redo()
    const edited = structuredClone(JSON.parse(JSON.stringify(builder.selected.value.props)))
    builder.copy()
    expect(builder.paste()).toBe(true)
    expect(builder.selected.value.id).not.toBe(original.id)
    expect(builder.selected.value.props).toEqual(edited)
    builder.markSaved(JSON.parse(JSON.stringify(builder.nodes.value)))
    expect(builder.isDirty.value).toBe(false)
    const saved = JSON.stringify(builder.nodes.value)
    builder.load(JSON.parse(saved))
    expect(JSON.stringify(builder.nodes.value)).toBe(saved)
  })
  it('derives repeater item schemas from cross-type references and preserves them when duplicated', async () => {
    const builder = await builderFixture()
    const repeater = builder.nodes.value[0].children[0].children[0].children[1]
    expect(builder.isTemplateField(repeater)).toBe(true)
    expect(builder.subfieldsOf(repeater)[0].key).toBe('detail')
    expect(builder.templateOwner(repeater.children[0].id).id).toBe(repeater.id)
    builder.select(repeater.id)
    builder.copy()
    builder.paste()
    const copy = builder.selected.value
    expect(copy.id).not.toBe(repeater.id)
    expect(copy.children[0].id).not.toBe(repeater.children[0].id)
    expect(copy.children[0].props.html.source).toBe('item')
    expect(builder.templateOwner(copy.children[0].id).id).toBe(copy.id)
  })
  it('keeps invalid edits detectable across selection and undo history', async () => {
    const builder = await builderFixture()
    const text = builder.nodes.value[0].children[0].children[0].children[0]
    builder.updateValue(text.id, 'props', 'html', { type: 'invalid', text: '{{ incomplete' })
    builder.select(null)
    expect(builder.hasSyntaxErrors.value).toBe(true)
    builder.undo()
    expect(builder.hasSyntaxErrors.value).toBe(false)
    builder.redo()
    expect(builder.hasSyntaxErrors.value).toBe(true)
  })
})
