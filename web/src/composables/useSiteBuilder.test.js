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

async function componentFixture(componentApi) {
  let builder
  await renderToString(createSSRApp({ setup() { builder = createSiteBuilder({ t: (key) => key, componentApi }); return () => null } }))
  builder.spec.value = { root: ['section'], item_elements: ['text'], kinds: {
    section: { props: {}, children: ['row'] }, row: { props: {}, children: ['column'] }, column: { props: { span: { default: { mobile: 12, tablet: 0, desktop: 0 } } }, children: ['text'] },
    text: { props: { html: { default: '' } }, children: [] },
    component: { props: { component: { default: '' }, root: { default: 'section' }, span: { default: { mobile: 12, tablet: 0, desktop: 0 } } }, children: [] },
  } }
  const column = builder.make('column', { span: { mobile: 12, tablet: 6, desktop: 0 } }, {}, [builder.make('text', { html: reference('intro') })])
  builder.load([builder.make('section', {}, {}, [builder.make('row', {}, {}, [column, builder.make('column')])])])
  return { builder, column }
}

describe('builder components', () => {
  it('converts a column into an instance in one undo step and keeps its width', async () => {
    const created = []
    const { builder, column } = await componentFixture({
      create: async (body) => {
        created.push(body)
        return { id: 'cabc', name: body.name, root: 'column', type: body.type, nodes: body.nodes }
      },
    })
    builder.type.value = { name: 'posts' }

    const component = await builder.convertToComponent(column.id, 'Intro')
    expect(component.id).toBe('cabc')
    expect(created[0].type).toBe('posts')
    expect(created[0].nodes[0].id).toBe(column.id)
    const row = builder.nodes.value[0].children[0]
    expect(row.children[0]).toMatchObject({ kind: 'component', props: { component: 'cabc', root: 'column', span: { mobile: 12, tablet: 6, desktop: 0 } } })
    expect(builder.selectedId.value).toBe(row.children[0].id)
    expect(builder.components.value).toEqual([{ id: 'cabc', name: 'Intro', root: 'column', type: 'posts' }])
    expect(builder.nodeLabel(row.children[0])).toBe('Intro')

    builder.undo()
    expect(builder.nodes.value[0].children[0].children[0].id).toBe(column.id)
  })

  it('detaches an instance into a copy with fresh ids and the instance width', async () => {
    const stored = { id: 'cabc', root: 'column', nodes: [{ id: 'root', kind: 'column', props: { span: { mobile: 12, tablet: 0, desktop: 0 } }, style: {}, children: [{ id: 'inner', kind: 'text', props: { html: 'Hi' }, style: {}, children: [] }] }] }
    const { builder } = await componentFixture({ load: async () => stored })
    const instance = builder.make('component', { component: 'cabc', root: 'column', span: { mobile: 12, tablet: 4, desktop: 0 } })
    builder.insert(instance, builder.nodes.value[0].children[0].children[1].id)
    expect(builder.nodes.value[0].children[0].children[2].id).toBe(instance.id)

    expect(await builder.detach(instance.id)).toBe(true)
    const copy = builder.nodes.value[0].children[0].children[2]
    expect(copy.kind).toBe('column')
    expect(copy.id).not.toBe('root')
    expect(copy.children[0].id).not.toBe('inner')
    expect(copy.children[0].props.html).toBe('Hi')
    expect(copy.props.span).toEqual({ mobile: 12, tablet: 4, desktop: 0 })
    expect(stored.nodes[0].id).toBe('root')
  })

  it('keeps the single root of a component in place', async () => {
    const { builder } = await componentFixture({ create: async () => ({}) })
    builder.rootKind.value = 'section'
    builder.componentId.value = 'cself'
    const root = builder.nodes.value[0]

    expect(builder.isLockedRoot(root.id)).toBe(true)
    expect(builder.canConvert(root)).toBe(false)
    expect(builder.canConvert(root.children[0])).toBe(true)
    builder.remove(root.id)
    builder.duplicate(root.id)
    builder.addSectionAfter(root.id)
    expect(builder.insert(builder.make('section'), root.id)).toBe(false)
    expect(builder.nodes.value).toHaveLength(1)
    expect(builder.canUndo.value).toBe(false)

    expect(builder.insert(builder.make('text'), root.children[0].children[1].id)).toBe(true)
  })
})


describe('builder linked state', () => {
  it('undoes and redoes linked edits in order with tree edits', async () => {
    let builder
    const state = { theme: null }
    await renderToString(createSSRApp({ setup() {
      builder = createSiteBuilder({ t: (key) => key, linked: { get: () => state.theme, set: (theme) => { state.theme = theme } } })
      return () => null
    } }))
    builder.spec.value = { root: ['section'], kinds: { section: { props: {}, children: [] } } }
    builder.load([])

    // The first theme to arrive is the loaded one, not an edit.
    state.theme = { primary: '#000000' }
    builder.linkedChanged()
    expect(builder.canUndo.value).toBe(false)

    builder.mutate((tree) => { tree.push(builder.make('section')) })
    state.theme = { primary: '#ff0000' }
    builder.linkedChanged()

    builder.undo()
    expect(state.theme).toEqual({ primary: '#000000' })
    expect(builder.nodes.value).toHaveLength(1)
    builder.undo()
    expect(builder.nodes.value).toHaveLength(0)
    expect(builder.canUndo.value).toBe(false)
    builder.redo()
    builder.redo()
    expect(state.theme).toEqual({ primary: '#ff0000' })

    // A saved theme replaces the baseline without an undo step.
    state.theme = { primary: '#ff0000', updated_at: 'now' }
    builder.linkedChanged({ record: false })
    builder.undo()
    expect(state.theme).toEqual({ primary: '#000000' })
  })
})
