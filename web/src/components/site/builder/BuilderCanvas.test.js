import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it, vi } from 'vitest'
import BuilderCanvas from './BuilderCanvas.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { PREVIEW_KEEP } from '../../../composables/previewDocument.js'
import { t } from '../../../i18n/index.js'

const preview = vi.hoisted(() => ({ document: null, emit: null }))
vi.mock('../SitePreviewFrame.vue', () => ({ default: {
  setup(_props, { emit }) {
    preview.emit = emit
    emit('ready', preview.document)
    return () => null
  },
} }))

function eventTarget() {
  const listeners = {}
  return {
    addEventListener: (name, listener) => { listeners[name] = listener },
    dispatch: (name, event = {}) => listeners[name]?.(event),
  }
}

function part(name, editable = false) {
  return {
    editable,
    children: [],
    classList: { contains: (value) => value === `c-${name}` },
    getBoundingClientRect: () => ({ left: 0, top: name === 'header' ? 0 : 600, right: 820, bottom: name === 'header' ? 72 : 760, width: 820, height: name === 'header' ? 72 : 160 }),
  }
}

async function canvas(props = {}) {
  const overlays = []
  const selectTemplatePart = vi.fn()
  const editField = vi.fn()
  let builder
  const parts = { header: part('header'), footer: part('footer') }
  const doc = {
    ...eventTarget(),
    documentElement: eventTarget(),
    defaultView: eventTarget(),
    body: { append: (...elements) => { overlays.splice(0, overlays.length, ...elements) } },
    createElement: () => ({ ...eventTarget(), style: {}, attributes: {}, children: [],
      setAttribute(name, value) { this.attributes[name] = value },
      append(child) { this.children.push(child) },
    }),
    querySelectorAll: () => [],
    getSelection: () => ({ toString: () => props.selection ?? '' }),
    querySelector: (selector) => Object.entries(parts).find(([name, value]) => selector === `.c-${name}:not([data-drop-root])` && !value.editable)?.[1] ?? null,
  }
  preview.document = doc
  const app = createSSRApp({ setup() {
    builder = createSiteBuilder({ t })
    return () => h(BuilderCanvas, { ...props, onSelectTemplatePart: selectTemplatePart, onEditField: editField })
  } })
  await renderToString(app)
  const highlight = overlays.find((element) => element.className === 'cometcms-template-highlight')
  const target = (name) => ({
    closest: (selector) => {
      if (selector === '[data-node]' && name === 'page') return { dataset: { node: 'section1' } }
      return selector === '.c-region:not([data-drop-root])' && !parts[name]?.editable ? parts[name] : null
    },
  })
  const hover = (name) => doc.dispatch('mouseover', { target: target(name) })
  const click = (name) => {
    const event = { target: target(name), preventDefault: vi.fn(), stopPropagation: vi.fn() }
    doc.dispatch('click', event)
    return event
  }
  return { doc, parts, highlight, hover, click, selectTemplatePart, editField, builder }
}

describe('builder shared template hover', () => {
  it('shows a labeled overlay for header and footer descendants, then clears it over page content', async () => {
    const { highlight, hover } = await canvas()
    expect(highlight.hidden).toBe(true)
    hover('header')
    expect(highlight.hidden).toBe(false)
    expect(highlight.children[0].textContent).toBe('Header · Template part')
    expect(highlight.style).toMatchObject({ left: '0px', top: '0px', width: '820px', height: '72px' })
    hover('footer')
    expect(highlight.children[0].textContent).toBe('Footer · Template part')
    expect(highlight.style.top).toBe('600px')
    hover(null)
    expect(highlight.hidden).toBe(true)
  })

  it('preserves the overlay on preview updates and hides it when the pointer leaves the frame', async () => {
    const { doc, highlight, hover } = await canvas()
    hover('header')
    expect(highlight.attributes).toHaveProperty(PREVIEW_KEEP)
    preview.emit('updated', doc)
    expect(highlight.hidden).toBe(false)
    doc.documentElement.dispatch('mouseleave')
    expect(highlight.hidden).toBe(true)
  })

  it('leaves the currently edited region to the regular node hover controls', async () => {
    const { parts, highlight, hover } = await canvas()
    parts.header.editable = true
    hover('header')
    expect(highlight.hidden).toBe(true)
  })

  it('selects the template part on a single click and clears it when selecting page content', async () => {
    const { click, selectTemplatePart, builder } = await canvas()
    for (const name of ['header', 'footer']) {
      const event = click(name)
      expect(selectTemplatePart).toHaveBeenLastCalledWith(name)
      expect(builder.selectedId.value).toBeNull()
      expect(event.preventDefault).toHaveBeenCalledOnce()
      expect(event.stopPropagation).toHaveBeenCalledOnce()
    }
    click('page')
    expect(selectTemplatePart).toHaveBeenLastCalledWith(null)
    expect(builder.selectedId.value).toBe('section1')
    click(null)
    expect(selectTemplatePart).toHaveBeenLastCalledWith(null)
    expect(builder.selectedId.value).toBeNull()
  })

  it('keeps the selected part highlighted when the pointer moves into the sidebar', async () => {
    const { doc, highlight, hover } = await canvas({ selectedTemplatePart: 'footer' })
    expect(highlight.hidden).toBe(false)
    expect(highlight.style.outlineStyle).toBe('solid')
    hover('header')
    expect(highlight.children[0].textContent).toBe('Header · Template part')
    expect(highlight.style.outlineStyle).toBe('dashed')
    doc.documentElement.dispatch('mouseleave')
    expect(highlight.hidden).toBe(false)
    expect(highlight.children[0].textContent).toBe('Footer · Template part')
    expect(highlight.style.outlineStyle).toBe('solid')
  })

  it('does not select the region already being edited as a shared template part', async () => {
    const { parts, click, selectTemplatePart } = await canvas()
    parts.header.editable = true
    click('header')
    expect(selectTemplatePart).toHaveBeenLastCalledWith(null)
  })
})

describe('builder smart tag double-click', () => {
  const ref = (key, source = 'current', path = [key]) => ({ type: 'reference', source, path, filters: [] })

  async function doubleClick(text, selection) {
    const result = await canvas({ selection })
    result.builder.spec.value = { kinds: { heading: { props: { text: { binding: 'text' }, level: {} } } } }
    result.builder.nodes.value = [{ id: 'heading1', kind: 'heading', props: { text }, style: {}, children: [] }]
    const event = { target: { closest: (selector) => selector === '[data-node]' ? { dataset: { node: 'heading1' }, querySelector: () => null } : null }, preventDefault: vi.fn() }
    result.doc.dispatch('dblclick', event)
    return { ...result, event }
  }

  it('asks to edit the fields of the previewed entry that the text shows', async () => {
    const text = { type: 'template', parts: ['Meet ', ref('name'), ' from ', ref('city'), ref('title', 'site'), ref('author', 'current', ['author', 'title'])] }
    const { editField, event } = await doubleClick(text, 'Berlin')
    expect(editField).toHaveBeenCalledWith({ fields: ['name', 'city'], text: 'Berlin' })
    expect(event.preventDefault).toHaveBeenCalledOnce()
  })

  it('leaves text without tags of the previewed entry alone', async () => {
    const { editField } = await doubleClick(ref('title', 'site'))
    expect(editField).not.toHaveBeenCalled()
  })
})
