import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it } from 'vitest'
import { createSiteBuilder } from './useSiteBuilder.js'
import { reference } from './smartValues.js'
import { findNode, resolveDrop } from './layoutTree.js'

describe('builder tabs', () => {
  it('edits normal elements inside tabs, supports history and duplicates panel IDs safely', async () => {
    const builder = await builderFixture()
    builder.spec.value.kinds.column.children.push('tabs')
    builder.spec.value.kinds.tabs = { props: { title: { default: 'Tabs' }, initial_tab: { default: 1 } }, children: ['tab'] }
    builder.spec.value.kinds.tab = { props: { title: { default: 'Tab' } }, children: ['text', 'heading', 'row', 'tabs'] }
    const column = builder.nodes.value[0].children[0].children[0]
    const tabs = builder.make('tabs')
    expect(tabs.children).toHaveLength(2)
    expect(builder.insert(tabs, column.id)).toBe(true)
    const second = tabs.children[1]
    const text = builder.make('text', { html: reference('intro') })
    expect(builder.insert(text, second.id)).toBe(true)
    expect(builder.entryContext(text.id).type.name).toBe('posts')
    expect(findNode(builder.nodes.value, second.id).children[0].id).toBe(text.id)
    builder.updateValue(tabs.id, 'props', 'initial_tab', 2)
    const heading = builder.make('heading')
    expect(builder.insert(heading, tabs.id)).toBe(true)
    expect(findNode(builder.nodes.value, second.id).children[1].id).toBe(heading.id)
    expect(resolveDrop(builder.spec.value, builder.nodes.value, second, null, 'inside')).toBeNull()
    builder.remove(second.id)
    expect(findNode(builder.nodes.value, second.id)).toBeNull()
    builder.undo()
    expect(findNode(builder.nodes.value, second.id).children).toHaveLength(2)
    builder.duplicate(tabs.id)
    const copy = builder.selected.value
    expect(copy.kind).toBe('tabs')
    expect(copy.id).not.toBe(tabs.id)
    expect(copy.children[1].id).not.toBe(second.id)
    expect(copy.children[1].children[0].id).not.toBe(text.id)
  })
})

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

describe('builder spacing', () => {
  it('splits paired padding in one undo step and resets individual edges to the default', async () => {
    const builder = await builderFixture()
    const section = builder.nodes.value[0]
    section.style = { padding_y: 'lg', padding_x: 'md', padding_right: 'xs', background: 'accent' }
    builder.load(builder.nodes.value)
    builder.updateValue(section.id, 'style', 'padding_top', 'sm')
    expect(builder.nodes.value[0].style).toEqual({ padding_top: 'sm', padding_bottom: 'lg', padding_x: 'md', padding_right: 'xs', background: 'accent' })
    builder.undo()
    expect(builder.nodes.value[0].style).toEqual(section.style)
    builder.redo()
    builder.updateValue(section.id, 'style', 'padding_left', '')
    expect(builder.nodes.value[0].style).toEqual({ padding_top: 'sm', padding_bottom: 'lg', padding_right: 'xs', background: 'accent' })
    builder.undo()
    expect(builder.nodes.value[0].style.padding_x).toBe('md')
    builder.updateValue(section.id, 'style', 'margin_bottom', 'none')
    expect(builder.nodes.value[0].style.margin_bottom).toBe('none')
    builder.updateValue(section.id, 'style', 'margin_bottom', '')
    expect(builder.nodes.value[0].style).not.toHaveProperty('margin_bottom')
  })
})

describe('builder page navigation', () => {
  it('removes disabled types from navigation while retaining their layouts and data bindings', async () => {
    const builder = await builderFixture()
    builder.types.value.push({ name: 'private', visibility: 'private' }, { name: 'unused' })
    builder.settings.value = { routes: { posts: { enabled: true }, company: { enabled: false }, private: { enabled: true } } }
    const saved = JSON.stringify(builder.nodes.value)

    expect(builder.pageTypes.value.map((type) => type.name)).toEqual(['posts'])
    builder.settings.value.routes.posts.enabled = false
    expect(builder.pageTypes.value).toEqual([])
    expect(JSON.stringify(builder.nodes.value)).toBe(saved)
    expect(builder.typeFields().some((field) => field.key === 'intro')).toBe(true)
    const repeater = builder.nodes.value[0].children[0].children[0].children[1]
    expect(builder.fieldConfig(repeater).type).toBe('repeater')

    builder.settings.value.routes.company.enabled = true
    expect(builder.pageTypes.value.map((type) => type.name)).toEqual(['company'])
    builder.settings.value = null
    expect(builder.pageTypes.value).toEqual([])
  })

  it('retains a public homepage without a separate route for single pages and collections', async () => {
    const builder = await builderFixture()
    builder.settings.value = { routes: {}, homepage: { type: 'company', entry: '' } }
    expect(builder.pageTypes.value.map((type) => type.name)).toEqual(['company'])

    builder.settings.value.homepage = { type: 'posts', entry: '' }
    expect(builder.pageTypes.value.map((type) => type.name)).toEqual(['posts'])
    builder.settings.value.homepage.entry = 'welcome'
    expect(builder.pageTypes.value.map((type) => type.name)).toEqual(['posts'])

    builder.types.value[0].visibility = 'private'
    expect(builder.pageTypes.value).toEqual([])
    builder.settings.value.homepage.type = 'deleted'
    expect(builder.pageTypes.value).toEqual([])
  })
})

describe('builder element names', () => {
  it('renames nested nodes without changing their content and restores names through undo, redo and reload', async () => {
    const builder = await builderFixture()
    const node = builder.nodes.value[0].children[0].children[0].children[0]
    const props = JSON.stringify(node.props)
    builder.select(node.id)
    builder.renameNode(node.id, '  Intro text  ')
    expect(builder.nodeLabel(builder.selected.value)).toBe('Intro text')
    expect(JSON.stringify(builder.selected.value.props)).toBe(props)
    expect(builder.isDirty.value).toBe(true)
    builder.undo()
    expect(builder.nodeLabel(builder.selected.value)).toBe('site.kind.text')
    expect(builder.isDirty.value).toBe(false)
    builder.redo()
    expect(builder.selected.value.name).toBe('Intro text')
    builder.markSaved(JSON.parse(JSON.stringify(builder.nodes.value)))
    builder.load(JSON.parse(JSON.stringify(builder.nodes.value)))
    expect(builder.selected.value.name).toBe('Intro text')
    expect(builder.isDirty.value).toBe(false)
    builder.renameNode(node.id, '  ')
    expect(builder.selected.value).not.toHaveProperty('name')
    expect(builder.nodeLabel(builder.selected.value)).toBe('site.kind.text')
    builder.undo()
    expect(builder.selected.value.name).toBe('Intro text')
  })

  it('keeps names on duplicates and pasted branches and ignores unchanged or invalid renames', async () => {
    const builder = await builderFixture()
    const section = builder.nodes.value[0]
    builder.renameNode(section.id, 'Hero')
    builder.renameNode(section.children[0].id, 'Hero columns')
    builder.duplicate(section.id)
    expect(builder.selected.value.name).toBe('Hero')
    expect(builder.selected.value.children[0].name).toBe('Hero columns')
    expect(builder.selected.value.id).not.toBe(section.id)
    builder.copy(section.id)
    builder.paste()
    expect(builder.selected.value.name).toBe('Hero')
    builder.markSaved(JSON.parse(JSON.stringify(builder.nodes.value)))
    builder.load(builder.nodes.value)
    builder.renameNode(section.id, 'Hero')
    builder.renameNode('missing', 'Nothing')
    builder.renameNode(section.id, null)
    expect(builder.canUndo.value).toBe(false)
    expect(builder.isDirty.value).toBe(false)
    builder.renameNode(section.id, 'ä'.repeat(121))
    expect(builder.nodes.value[0].name).toBe('ä'.repeat(120))
  })
})

describe('builder integration with smart values', () => {
  it('retains references through undo, redo, copy, paste, save and reload', async () => {
    const builder = await builderFixture()
    const original = builder.nodes.value[0].children[0].children[0].children[0]
    builder.select(original.id)
    builder.updateValue(original.id, 'props', 'html', { type: 'template', parts: ['Welcome ', reference('title', 'site')] })
    expect(builder.nodeSummary(builder.selected.value)).toBe('Welcome [site › title]')
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
    builder.renameNode(instance.id, 'Sidebar promo')
    expect(builder.nodes.value[0].children[0].children[2].id).toBe(instance.id)

    expect(await builder.detach(instance.id)).toBe(true)
    const copy = builder.nodes.value[0].children[0].children[2]
    expect(copy.kind).toBe('column')
    expect(copy.name).toBe('Sidebar promo')
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

  it('keeps all components listed but only inserts compatible components outside themselves', async () => {
    const { builder } = await componentFixture({})
    const shared = { id: 'shared', type: '' }
    const typed = { id: 'typed', type: 'posts' }
    builder.components.value = [shared, typed]
    expect(builder.canInsertComponent(shared)).toBe(true)
    expect(builder.canInsertComponent(typed)).toBe(false)
    builder.type.value = { name: 'posts' }
    expect(builder.canInsertComponent(typed)).toBe(true)
    builder.currentAvailable.value = false
    expect(builder.canInsertComponent(typed)).toBe(false)
    builder.componentId.value = 'shared'
    expect(builder.canInsertComponent(shared)).toBe(false)
    expect(builder.components.value).toHaveLength(2)
  })

  it('detaches deleted components throughout the draft, undo, redo and clipboard without losing pending edits', async () => {
    const component = { id: 'shared', root: 'column', nodes: [{ id: 'root', kind: 'column', props: { span: { mobile: 12 } }, style: { background: 'primary' }, children: [{ id: 'heading', kind: 'text', props: { html: 'Shared content' }, style: {}, children: [] }] }] }
    const { builder } = await componentFixture({ remove: async () => component })
    builder.components.value = [component]
    const instance = builder.make('component', { component: 'shared', root: 'column', span: { mobile: 12, tablet: 4, desktop: 3 } })
    instance.name = 'Sidebar promo'
    const sibling = builder.make('column', {}, {}, [builder.make('text', { html: 'Before' })])
    builder.load([builder.make('section', {}, {}, [builder.make('row', {}, {}, [instance, sibling])])])
    const textId = sibling.children[0].id
    builder.copy(instance.id)
    builder.select(instance.id)
    builder.updateValue(textId, 'props', 'html', 'Pending')
    builder.mutate((tree) => { tree[0].children[0].children[1].children[0].props.html = 'Future' })
    builder.undo()

    await builder.deleteComponent('shared')
    const detached = builder.selected.value
    expect(detached).toMatchObject({ id: instance.id, kind: 'column', props: { span: { mobile: 12, tablet: 4, desktop: 3 } }, style: { background: 'primary' } })
    expect(detached.name).toBe('Sidebar promo')
    expect(detached.children[0].props.html).toBe('Shared content')
    expect(detached.children[0].id).not.toBe('heading')
    expect(builder.components.value).toEqual([])
    expect(builder.clipboard.value.kind).toBe('column')
    expect(builder.isDirty.value).toBe(true)
    expect(builder.nodes.value[0].children[0].children[1].children[0].props.html).toBe('Pending')
    builder.redo()
    expect(builder.nodes.value[0].children[0].children[1].children[0].props.html).toBe('Future')
    expect(builder.selected.value).toEqual(detached)
    builder.undo()
    builder.undo()
    expect(builder.isDirty.value).toBe(false)
    expect(builder.selected.value).toEqual(detached)
  })

  it('leaves the current state and history intact when deleting fails', async () => {
    const { builder } = await componentFixture({ remove: async () => { throw new Error('Request failed') } })
    builder.components.value = [{ id: 'shared', name: 'Shared' }]
    builder.mutate((tree) => { tree.push(builder.make('section')) })
    const before = JSON.stringify(builder.nodes.value)
    await expect(builder.deleteComponent('shared')).rejects.toThrow('Request failed')
    expect(JSON.stringify(builder.nodes.value)).toBe(before)
    expect(builder.components.value).toHaveLength(1)
    expect(builder.canUndo.value).toBe(true)
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

  it('keeps a restored linked state through undo and redo of earlier tree edits', async () => {
    let builder
    const state = { theme: null }
    await renderToString(createSSRApp({ setup() {
      builder = createSiteBuilder({ t: (key) => key, linked: { get: () => state.theme, set: (theme) => { state.theme = theme } } })
      return () => null
    } }))
    builder.spec.value = { root: ['section'], kinds: { section: { props: {}, children: [] } } }
    builder.load([])
    state.theme = { primary: '#000000' }
    builder.linkedChanged()

    builder.mutate((tree) => { tree.push(builder.make('section')) })
    state.theme = { primary: '#ff0000' }
    builder.linkedChanged()
    builder.mutate((tree) => { tree.push(builder.make('section')) })
    builder.undo()

    state.theme = { primary: '#00ff00' }
    builder.rebaseLinked()

    // The theme-only step is gone; the layout steps remain and keep the restored theme.
    builder.undo()
    expect(builder.nodes.value).toHaveLength(0)
    expect(state.theme).toEqual({ primary: '#00ff00' })
    expect(builder.canUndo.value).toBe(false)
    builder.redo()
    builder.redo()
    expect(builder.nodes.value).toHaveLength(2)
    expect(state.theme).toEqual({ primary: '#00ff00' })
  })
})

async function contentFixture(fieldApi) {
  let builder
  await renderToString(createSSRApp({ setup() { builder = createSiteBuilder({ t: (key) => key, fieldApi }); return () => null } }))
  builder.spec.value = { root: ['section'], item_elements: ['heading'], kinds: {
    section: { props: {}, children: ['row'] }, row: { props: {}, children: ['column'] }, column: { props: {}, children: ['heading', 'image', 'field'] },
    heading: { props: { text: { default: '' } }, children: [] }, image: { props: { media: { default: '' } }, children: [] },
    field: { props: { source: { default: '' } }, children: ['heading'] },
  } }
  builder.type.value = { name: 'posts', label: 'Posts', fields: { title: { type: 'text' }, heading: { type: 'text' } } }
  builder.types.value = [builder.type.value, { name: 'company', singleton: true, fields: { steps: { type: 'repeater', subfields: [{ key: 'name', type: 'text' }] } } }]
  const heading = builder.make('heading', { text: 'Our story' })
  const image = builder.make('image', { media: 'team.jpg' })
  const bound = builder.make('heading', { text: reference('title') })
  const template = builder.make('field', { source: { ...reference('steps', 'single'), collection: 'company' } }, {}, [builder.make('heading', { text: 'Step' })])
  builder.load([builder.make('section', {}, {}, [builder.make('row', {}, {}, [builder.make('column', {}, {}, [heading, image, bound, template])])])])
  return { builder, heading, image, bound, item: template.children[0] }
}

describe('builder content fields', () => {
  it('offers literal content for conversion, but not bound or template content', async () => {
    const { builder, heading, image, bound, item } = await contentFixture({ create: async () => null, can: () => true })
    expect(builder.canMakeField(heading)).toBe(true)
    expect(builder.canMakeField(image)).toBe(true)
    expect(builder.canMakeField(bound)).toBe(false)
    expect(builder.canMakeField(item)).toBe(false)
    builder.currentAvailable.value = false
    expect(builder.canMakeField(heading)).toBe(false)
  })

  it('needs permission to change the content type', async () => {
    const { builder, heading } = await contentFixture({ create: async () => null, can: () => false })
    expect(builder.canMakeField(heading)).toBe(false)
  })

  it('adds a uniquely named field and binds the element with its content as fallback, in one undo step', async () => {
    const calls = []
    const { builder, heading, image } = await contentFixture({
      can: () => true,
      create: async ({ type, key, config }) => {
        calls.push({ type, key, config })
        return { name: type, fields: { ...builder.type.value.fields, [key]: config } }
      },
    })
    expect(await builder.convertToField(heading.id, 'Heading')).toBe('heading_2')
    expect(calls[0]).toEqual({ type: 'posts', key: 'heading_2', config: { type: 'text', label: 'Heading', required: false, default: 'Our story' } })
    const converted = builder.nodes.value[0].children[0].children[0].children[0]
    expect(converted.props.text).toEqual({ ...reference('heading_2'), filters: [{ name: 'fallback', value: 'Our story' }] })
    expect(builder.type.value.fields.heading_2.type).toBe('text')
    expect(builder.types.value[0].fields.heading_2).toBeTruthy()

    expect(await builder.convertToField(image.id, 'Team photo')).toBe('team_photo')
    expect(calls[1].config).toEqual({ type: 'media', label: 'Team photo', required: false, localized: false })

    builder.undo()
    builder.undo()
    expect(builder.nodes.value[0].children[0].children[0].children[0].props.text).toBe('Our story')
  })

  it('designs collection cards in place with the fields of the listed type', async () => {
    let builder
    const card = { id: 'std', kind: 'column', props: {}, style: { shadow: 'md' }, children: [{ id: 'std-t', kind: 'text', props: { html: reference('title') }, style: {}, children: [] }] }
    const templateApi = { load: async (type) => (type === 'posts' ? { nodes: [card] } : null) }
    await renderToString(createSSRApp({ setup() { builder = createSiteBuilder({ t: (key) => key, templateApi }); return () => null } }))
    builder.spec.value = { root: ['section'], item_elements: [], kinds: {
      section: { props: {}, children: ['row'] }, row: { props: {}, children: ['column'] }, column: { props: {}, children: ['text', 'collection', 'row'] },
      text: { props: { html: { default: '' } }, children: [] }, collection: { props: { type: { default: '' } }, children: ['column'] },
    } }
    builder.type.value = { name: 'pages', fields: {} }
    builder.currentAvailable.value = false
    builder.types.value = [builder.type.value, { name: 'posts', fields: { intro: { type: 'text' } } }]
    const list = builder.make('collection', { type: 'posts' })
    builder.load([builder.make('section', {}, {}, [builder.make('row', {}, {}, [builder.make('column', {}, {}, [list])])])])

    const copy = await builder.customizeCard(list.id)
    expect(copy.id).not.toBe('std')
    expect(copy.children[0].id).not.toBe('std-t')
    expect(builder.selectedId.value).toBe(copy.id)
    expect(builder.nodeLabel(copy)).toBe('site.kind.card')
    expect(builder.entryContext(copy.children[0].id)).toMatchObject({ type: { name: 'posts' }, currentAvailable: true })
    expect(builder.entryContext(list.id)).toMatchObject({ type: { name: 'pages' }, currentAvailable: false })
    expect(builder.typeFields(copy.id).map((field) => field.key)).toContain('intro')
    builder.duplicate(copy.id)
    expect(builder.nodes.value[0].children[0].children[0].children[0].children).toHaveLength(1)

    builder.resetCard(list.id)
    expect(builder.nodes.value[0].children[0].children[0].children[0].children).toEqual([])
    builder.undo()
    expect(builder.nodes.value[0].children[0].children[0].children[0].children).toHaveLength(1)
  })
})


describe('loading layout revisions', () => {
  it('keeps the saved baseline and unsaved edits, supports undo/redo, and only becomes saved explicitly', async () => {
    const builder = await builderFixture()
    const saved = builder.savedSnapshot.value
    builder.mutate((nodes) => { nodes[0].name = 'Unsaved work' })
    const draft = JSON.parse(JSON.stringify(builder.nodes.value))
    const revision = [builder.make('section')]

    builder.loadRevision(revision)
    expect(builder.nodes.value).toEqual(revision)
    expect(builder.savedSnapshot.value).toBe(saved)
    expect(builder.isDirty.value).toBe(true)
    builder.undo()
    expect(builder.nodes.value).toEqual(draft)
    builder.redo()
    expect(builder.nodes.value).toEqual(revision)
    builder.loadRevision(draft)
    expect(builder.nodes.value).toEqual(draft)
    expect(builder.isDirty.value).toBe(true)
    builder.markSaved(draft)
    expect(builder.isDirty.value).toBe(false)
  })
})

describe('builder localization', () => {
  it('keeps translated values on node identities through language changes history and duplication', async () => {
    const builder = await builderFixture()
    builder.settings.value = { default_locale: 'en', locales: ['en', 'de'] }
    builder.spec.value.kinds.heading.props.text.localized = true
    builder.locale.value = 'en'
    const column = builder.nodes.value[0].children[0].children[0]
    const heading = builder.make('heading', { text: 'English' })
    builder.insert(heading, column.id)
    builder.locale.value = 'de'
    builder.editingMode.value = 'translate'
    builder.updateValue(heading.id, 'props', 'text', 'Deutsch')
    expect(builder.valueFor(findNode(builder.nodes.value, heading.id), 'props', 'text')).toBe('Deutsch')
    expect(findNode(builder.nodes.value, heading.id).props.text.values).toEqual({ en: 'English', de: 'Deutsch' })
    expect(builder.insert(builder.make('text'), column.id)).toBe(false)
    builder.remove(heading.id)
    expect(findNode(builder.nodes.value, heading.id)).not.toBeNull()
    builder.undo()
    expect(builder.valueFor(findNode(builder.nodes.value, heading.id), 'props', 'text')).toBe('English')
    builder.redo()
    expect(builder.valueFor(findNode(builder.nodes.value, heading.id), 'props', 'text')).toBe('Deutsch')
    builder.editingMode.value = 'design'
    builder.duplicate(heading.id)
    const copy = builder.selected.value
    expect(copy.id).not.toBe(heading.id)
    expect(copy.props.text.values).toEqual({ en: 'English', de: 'Deutsch' })
    builder.locale.value = 'en'
    builder.updateValue(heading.id, 'props', 'text', 'Changed source')
    builder.locale.value = 'de'
    expect(builder.translationState(findNode(builder.nodes.value, heading.id), 'props', 'text')).toBe('review')
    expect(builder.valueFor(copy, 'props', 'text')).toBe('Deutsch')
    builder.resetTranslation(heading.id, 'props', 'text')
    expect(builder.valueFor(findNode(builder.nodes.value, heading.id), 'props', 'text')).toBe('Changed source')
  })
})
