import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { useSiteStore } from '../../stores/site.js'
import FontPicker from './FontPicker.vue'

const renderPicker = async (modelValue, extra = {}) => {
  const app = createSSRApp({ setup() {
    useSiteStore().data = {
      fonts: ['system', 'fontsource-inter', 'custom-123'],
      font_stacks: { 'fontsource-inter': '"comet-fontsource-inter",sans-serif' },
      font_families: [
        { id: 'fontsource-inter', name: 'Inter', source: 'fontsource', available: true },
        { id: 'custom-123', name: 'Brand <script>', source: 'custom', available: false },
      ],
    }
    return () => h(FontPicker, { id: 'typeface', modelValue, ...extra })
  } })
  app.use(createPinia())
  return renderToString(app)
}

describe('shared font picker', () => {
  it('renders the selected label without mounting menu previews before opening', async () => {
    const html = await renderPicker('fontsource-inter', { allowEmpty: true, roles: true })
    expect(html).toContain('id="typeface"')
    expect(html).toContain('role="combobox"')
    expect(html).toContain('aria-expanded="false"')
    expect(html).toContain('Inter')
    expect(html).not.toContain('role="option"')
    expect(html).not.toContain('font-family:')
    expect(html).not.toContain('<p')
  })

  it('identifies unavailable references retained by older revisions', async () => {
    const html = await renderPicker('fontsource-missing')
    expect(html).toContain('fontsource-missing · Unavailable')
    expect(html).toContain('Font files are missing.')
    expect(html).toContain('role="status"')
  })
})
