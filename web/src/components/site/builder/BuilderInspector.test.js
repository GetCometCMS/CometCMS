import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it, vi } from 'vitest'
import BuilderInspector from './BuilderInspector.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { t } from '../../../i18n/index.js'

vi.mock('./BuilderControl.vue', () => ({
  default: { props: ['name'], setup: (props) => () => h('div', { 'data-control': props.name }) },
}))

async function menuControls(props) {
  const app = createSSRApp({ setup() {
    const builder = createSiteBuilder({ t })
    builder.spec.value = { kinds: { menu: { props: Object.fromEntries([
      'menu', 'always_button', 'orientation', 'collapse_mobile', 'panel', 'panel_side', 'panel_background', 'panel_text_color',
    ].map((key) => [key, {}])) } } }
    builder.load([{ id: 'menu', kind: 'menu', props: { collapse_mobile: true, panel: 'dropdown', ...props }, style: {}, children: [] }])
    builder.select('menu')
    return () => h(BuilderInspector)
  } })
  const html = await renderToString(app)
  return [...html.matchAll(/data-control="([^"]+)"/g)].map((match) => match[1])
}

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
