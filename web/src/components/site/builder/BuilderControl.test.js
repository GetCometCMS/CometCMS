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

describe('builder gradient controls', () => {
  it('uses the shared gradient editor for backgrounds and text', async () => {
    for (const name of ['background_gradient', 'text_gradient']) {
      const html = await renderControl(name, { type: 'gradient', options: ['primary', 'accent'], default: [] }, { type: 'linear', from: 'primary', to: 'accent', angle: 135 }, { group: 'style' })
      expect(html).toContain(`id="builder-icon1-style-${name}"`)
      expect(html).toContain('linear-gradient(135deg,')
      expect(html).toContain('Start color')
      expect(html).toContain('End color')
    }
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

describe('builder search targets', () => {
  const types = [
    { name: 'blog', label: 'Blog', fields: { slug: { type: 'slug' }, body: { type: 'markdown', label: 'Body' }, cover: { type: 'media' } } },
    { name: 'docs', label: 'Docs', fields: { body: { type: 'html', label: 'Content' }, summary: { type: 'textarea', label: 'Summary' } } },
    { name: 'drafts', label: 'Drafts', fields: {} },
    { name: 'notes', label: 'Notes', visibility: 'private', fields: {} },
  ]

  async function renderSearchControl(name, type, value, props = {}) {
    const app = createSSRApp({ setup() {
      const builder = createSiteBuilder({ t })
      builder.types.value = types
      builder.settings.value = { routes: { blog: { enabled: true }, docs: { enabled: true }, notes: { enabled: true } } }
      return () => h(BuilderControl, { node: { id: 'search1', kind: 'search', props, style: {} }, name, def: { type, default: [] }, value })
    } })
    app.use(createPinia())
    return renderToString(app)
  }

  it('offers public content types with pages and says that none checked searches all of them', async () => {
    const html = await renderSearchControl('collections', 'content_types', [])
    expect(html).toContain('Search in')
    expect(html).toContain('All page types')
    expect(html).toContain('Blog')
    expect(html).toContain('Docs')
    expect(html).not.toContain('Drafts')
    expect(html).not.toContain('Notes')
    expect(html.match(/type="checkbox"/g)).toHaveLength(2)
  })

  it('keeps a chosen type without pages visible so it can be unchecked', async () => {
    const html = await renderSearchControl('collections', 'content_types', ['drafts'])
    expect(html).toContain('Drafts')
    expect(html).toContain('no pages')
    expect(html).not.toContain('All page types')
  })

  it('lists the title and text fields of the searched types and notes where a field is missing', async () => {
    const all = await renderSearchControl('fields', 'search_fields', [])
    expect(all).toContain('All text fields')
    expect(all).toContain('Title')
    expect(all).toContain('Body')
    expect(all).toContain('Summary')
    expect(all).toMatch(/Summary<\/span><span[^>]*>Docs</)
    expect(all).not.toContain('cover')
    expect(all).not.toContain('>slug<')

    const blogOnly = await renderSearchControl('fields', 'search_fields', ['body'], { collections: ['blog'] })
    expect(blogOnly).not.toContain('Summary')
    expect(blogOnly).not.toContain('All text fields')
    expect(blogOnly).toMatch(/checked[^>]*>\s*<span[^>]*>Body</)
  })
})

describe('builder section minimum height', () => {
  const def = { type: 'enum', options: ['', 'quarter', 'half', 'three-quarter', 'screen'], default: '' }

  it('shows five labeled radio presets and preserves the saved selection', async () => {
    for (const value of def.options) {
      const html = await renderControl('min_height', def, value, {
        node: { id: 'section1', kind: 'section', props: {}, style: {} },
      })
      expect(html.match(/type="radio"/g)).toHaveLength(5)
      expect(html.match(/ checked/g)).toHaveLength(1)
      expect(html).toContain(`value="${value}" checked`)
      for (const label of ['Auto', '25%', '50%', '75%', '100%']) expect(html).toContain(`>${label}</span>`)
      expect(html).toContain('Percentage of screen height. Content can make the section taller.')
      expect(html).not.toContain('<select')
    }
  })
})

describe('builder list filters', () => {
  const blog = { name: 'blog', label: 'Blog', fields: {
    category: { type: 'select', label: 'Category' },
    featured: { type: 'boolean', label: 'Featured' },
    price: { type: 'number', label: 'Price' },
    cover: { type: 'media', label: 'Cover' },
  } }

  async function renderListControl(name, type, value) {
    const app = createSSRApp({ setup() {
      const builder = createSiteBuilder({ t })
      builder.types.value = [blog]
      return () => h(BuilderControl, { node: { id: 'list1', kind: 'collection', props: { type: 'blog' }, style: {} }, name, def: { type, default: [] }, value })
    } })
    app.use(createPinia())
    return renderToString(app)
  }

  it('shows each filter with the control its field type gives visitors and offers the remaining fields', async () => {
    const html = await renderListControl('filters', 'list_filters', [{ field: 'category', label: '', multiple: true }, { field: 'gone' }])
    expect(html).toContain('Filters for visitors')
    expect(html).toContain('Category')
    expect(html).toContain('Choices')
    expect(html).toContain('Several')
    expect(html).toContain('field missing')
    expect(html).toContain('Featured · Switch')
    expect(html).toContain('Price · From–to')
    expect(html).toContain('Title · Search')
    expect(html).not.toContain('Cover')
  })

  it('lists sortable fields and says when visitors get no sorting', async () => {
    const html = await renderListControl('sort_choices', 'sort_fields', [])
    expect(html).toContain('None: the list keeps its order')
    expect(html).toContain('Published')
    expect(html).toContain('Price')
    expect(html).not.toContain('Cover')
  })
})
