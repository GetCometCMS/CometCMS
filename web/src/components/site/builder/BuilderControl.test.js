import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { getIcon } from '@iconify/vue'
import BuilderControl from './BuilderControl.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { SITE_ICONS } from '../../../composables/siteIcons.js'
import { t } from '../../../i18n/index.js'

async function renderControl(name, def, value, overrides = {}) {
  const app = createSSRApp({ setup() {
    createSiteBuilder({ t })
    return () => h(BuilderControl, { node: { id: 'icon1', kind: 'icon', props: {}, style: {} }, name, def, value, ...overrides })
  } })
  app.use(createPinia())
  return renderToString(app)
}

describe('builder icon controls', () => {
  it('uses the existing picker with a label and a locally available selected icon', async () => {
    const html = await renderControl('icon', { type: 'icon', default: 'mdi:star-outline', options: SITE_ICONS }, 'mdi:heart-outline')
    expect(html).toContain('<button id="builder-icon1-props-icon"')
    expect(html).toContain('for="builder-icon1-props-icon"')
    expect(html).toContain('mdi:heart-outline')
    expect(html).not.toContain('<select')
    expect(getIcon('mdi:heart-outline')?.body).toContain('<path')
  })

  it('offers exact pixel sizes and an accessible label rather than a field label toggle', async () => {
    const size = await renderControl('size', { type: 'int', min: 12, max: 240, unit: 'px', default: 48 }, 48)
    expect(size).toContain('step="1"')
    expect(size).toContain('value="48"')
    const label = await renderControl('label', { type: 'string', max: 120, default: '' }, '')
    expect(label).toContain('Accessible label')
    expect(label).not.toContain('Show field label')
  })
})

describe('builder hover effects', () => {
  it('describes movement for hover and entrances for other triggers', async () => {
    const def = { type: 'enum', options: ['none', 'fade', 'fade-left', 'zoom-in', 'blur'] }
    const hover = await renderControl('animation', def, 'fade-left', {
      group: 'style', node: { id: 'text1', kind: 'text', style: { animation_trigger: 'hover' } },
    })
    expect(hover).toContain('Hover effect')
    expect(hover).toContain('Move left')
    expect(hover).toContain('Dim slightly')
    expect(hover).toContain('Blur slightly')
    expect(hover).not.toContain('Fade in from the right')

    const scroll = await renderControl('animation', def, 'fade-left', { group: 'style' })
    expect(scroll).toContain('Entrance')
    expect(scroll).toContain('Fade in from the right')
  })
})

describe('builder link destinations', () => {
  it('uses the shared destination selector and dynamic content picker for fields and galleries', async () => {
    for (const kind of ['field', 'gallery']) {
      const html = await renderControl('link', { type: 'link', binding: 'link', default: { type: 'none' } }, { type: 'current' }, {
        node: { id: 'linked', kind, props: {}, style: {} },
      })
      expect(html).toContain('Insert dynamic content')
      expect(html).toContain('This entry')
      expect(html).toContain('Open in a new tab')
      expect(html).not.toContain('Link to the entry')
      expect(html).not.toContain('role="switch"')
    }
  })
})
