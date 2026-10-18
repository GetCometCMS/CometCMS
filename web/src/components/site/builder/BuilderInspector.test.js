import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it, vi } from 'vitest'
import BuilderInspector from './BuilderInspector.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { t } from '../../../i18n/index.js'

vi.mock('./BuilderControl.vue', () => ({
  default: { props: ['name'], setup: (props) => () => h('div', { 'data-control': props.name }) },
}))

async function elementControls(kind, keys, props) {
  const app = createSSRApp({ setup() {
    const builder = createSiteBuilder({ t })
    builder.spec.value = { kinds: { [kind]: { props: Object.fromEntries(keys.map((key) => [key, {}])) } } }
    builder.load([{ id: 'element', kind, props, style: {}, children: [] }])
    builder.select('element')
    return () => h(BuilderInspector)
  } })
  const html = await renderToString(app)
  return [...html.matchAll(/data-control="([^"]+)"/g)].map((match) => match[1])
}

const menuControls = (props) => elementControls('menu', [
  'menu', 'always_button', 'orientation', 'collapse_mobile', 'panel', 'panel_side', 'panel_background', 'panel_text_color',
], { collapse_mobile: true, panel: 'dropdown', ...props })

describe('gallery masonry controls', () => {
  it('offers navigation only with the modal enabled and restores entry links when disabled', async () => {
    const keys = ['media', 'lightbox', 'lightbox_navigation', 'link', 'masonry']
    const enabled = await elementControls('gallery', keys, { media: [], lightbox: true })
    expect(enabled).toEqual(expect.arrayContaining(['lightbox', 'lightbox_navigation', 'masonry']))
    expect(enabled).not.toContain('link')
    const disabled = await elementControls('gallery', keys, { media: [], lightbox: false })
    expect(disabled).toContain('link')
    expect(disabled).not.toContain('lightbox_navigation')
  })
  it('offers the masonry toggle and hides crop controls only while it is enabled', async () => {
    const keys = ['media', 'masonry', 'ratio', 'fit', 'position', 'columns', 'gap', 'captions']
    const props = { media: [], ratio: '4:3' }
    const masonry = await elementControls('gallery', keys, { ...props, masonry: true })
    expect(masonry).toEqual(expect.arrayContaining(['media', 'masonry', 'columns', 'gap', 'captions']))
    for (const key of ['ratio', 'fit', 'position']) expect(masonry).not.toContain(key)
    const grid = await elementControls('gallery', keys, { ...props, masonry: false })
    expect(grid).toEqual(expect.arrayContaining(['masonry', 'ratio', 'fit', 'position']))
  })
})

describe('menu panel controls', () => {
  it('offers position only for side panels and colors for both panel types', async () => {
    expect(await menuControls({ panel: 'drawer' })).toEqual(expect.arrayContaining(['panel_side', 'panel_background', 'panel_text_color']))
    const fullscreen = await menuControls({ panel: 'fullscreen', always_button: true })
    expect(fullscreen).toEqual(expect.arrayContaining(['panel', 'panel_background', 'panel_text_color']))
    expect(fullscreen).not.toContain('panel_side')
    expect(fullscreen).not.toContain('orientation')
    expect(fullscreen).not.toContain('collapse_mobile')
  })

  it('hides panel settings for dropdowns and menus without a button', async () => {
    const dropdown = await menuControls({})
    expect(dropdown).toContain('panel')
    for (const key of ['panel_side', 'panel_background', 'panel_text_color']) expect(dropdown).not.toContain(key)
    const inline = await menuControls({ panel: 'drawer', collapse_mobile: false })
    for (const key of ['panel', 'panel_side', 'panel_background', 'panel_text_color']) expect(inline).not.toContain(key)
  })
})
