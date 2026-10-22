import { readFileSync } from 'node:fs'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { createSiteBuilder } from './useSiteBuilder.js'
import { findNode } from './layoutTree.js'
import BuilderPalette from '../components/site/builder/BuilderPalette.vue'
import BuilderControl from '../components/site/builder/BuilderControl.vue'
import { t } from '../i18n/index.js'

const manifest = JSON.parse(readFileSync(new URL('../../../examples/blocks/acme-marketing/blocks/callout/block.json', import.meta.url), 'utf8'))
// A block is named after its package vendor and folder: acme/marketing's blocks/callout is acme/callout.
const kind = 'block:acme/callout'
const spec = {
  root: ['section'], elements: ['heading', kind], item_elements: [kind], style: {},
  kinds: {
    section: { props: {}, children: ['row'] }, row: { props: {}, children: ['column'] },
    column: { props: {}, children: ['heading', 'field', 'tabs', kind] },
    heading: { props: { text: { default: '' } }, children: [] },
    field: { props: {}, children: [kind] }, tabs: { props: {}, children: ['tab'] },
    tab: { props: {}, children: [kind] },
    [kind]: { label: manifest.title, description: manifest.description, extension: true, props: manifest.props, style: [], children: [] },
  },
}

async function fixture(render = () => null) {
  let builder
  const app = createSSRApp({ setup() {
    builder = createSiteBuilder({ t })
    builder.spec.value = structuredClone(spec)
    return () => render(builder)
  } })
  app.use(createPinia())
  const html = await renderToString(app)
  return { builder, html }
}

describe('block package blocks in the builder', () => {
  it('exposes the installed example alongside built-ins with its own palette name and description', async () => {
    const { html } = await fixture(() => h(BuilderPalette, { withFields: false }))
    expect(html).toContain('>Callout<')
    expect(html).toContain('>Heading<')
    expect(html).toContain(manifest.description)
    expect(html).not.toContain('site.kind.block:')
  })

  it('creates defaults and wraps root insertions, supports duplication, editing and undo', async () => {
    const { builder } = await fixture()
    const block = builder.make(kind)
    expect(block.props.title).toBe(manifest.props.title.default)
    expect(builder.insert(block)).toBe(true)
    expect(builder.nodes.value[0].kind).toBe('section')
    expect(findNode(builder.nodes.value, block.id).kind).toBe(kind)
    expect(builder.nodeLabel(block)).toBe('Callout')
    builder.updateValue(block.id, 'props', 'title', 'Changed')
    expect(findNode(builder.nodes.value, block.id).props.title).toBe('Changed')
    builder.undo()
    expect(findNode(builder.nodes.value, block.id).props.title).toBe(manifest.props.title.default)
    builder.duplicate(block.id)
    const column = builder.nodes.value[0].children[0].children[0]
    expect(column.children).toHaveLength(2)
    expect(column.children[0].id).not.toBe(column.children[1].id)
  })

  it('uses manifest labels and provides an editable URL input and enum choices', async () => {
    const { html } = await fixture((builder) => h('div', Object.entries(manifest.props).map(([name, def]) => h(BuilderControl, {
      node: builder.make(kind), name, def, value: def.default,
    }))))
    for (const def of Object.values(manifest.props)) expect(html).toContain(def.label)
    expect(html).toContain('value="#details"')
    expect(html).toContain('value="Something worth sharing"')
    expect(html).toContain('success')
    expect(html).not.toContain('site.prop.url')
  })
})
