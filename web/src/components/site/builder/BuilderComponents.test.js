import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import BuilderComponents from './BuilderComponents.vue'
import BuilderPalette from './BuilderPalette.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { t } from '../../../i18n/index.js'

vi.mock('../../ConfirmModal.vue', () => ({ default: { render: () => null } }))

async function renderPanel(panel) {
  const app = createSSRApp({ setup() {
    const builder = createSiteBuilder({ t })
    builder.spec.value = { elements: ['text', 'component'], kinds: {} }
    builder.components.value = [
      { id: 'shared', name: 'Shared promo', root: 'section', type: '' },
      { id: 'typed', name: 'Article teaser', root: 'row', type: 'posts' },
      { id: 'self', name: 'Current component', root: 'section', type: '' },
    ]
    builder.componentId.value = 'self'
    builder.currentAvailable.value = false
    return () => h(panel, { withFields: false })
  } })
  app.use(createPinia())
  return renderToString(app)
}

describe('builder components panel', () => {
  it('lists every component with add, edit and delete controls, disabling incompatible placements', async () => {
    const html = await renderPanel(BuilderComponents)
    expect(html).toContain('Shared promo')
    expect(html).toContain('Article teaser')
    expect(html).toContain('Current component')
    expect(html.match(/draggable="true"/g)).toHaveLength(1)
    expect(html.match(/draggable="false"/g)).toHaveLength(2)
    expect(html).toContain('Edit component: Shared promo')
    expect(html).toContain('Delete: Shared promo')
  })

  it('keeps components out of the Add panel, including the generic component element', async () => {
    const html = await renderPanel(BuilderPalette)
    expect(html).not.toContain('Shared promo')
    expect(html).not.toContain('Article teaser')
    expect(html).not.toContain('Components')
    expect(html).not.toContain('>Component<')
    expect(html).toContain('Text')
  })
})
