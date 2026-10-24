import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it } from 'vitest'
import LinkOptions from './LinkOptions.vue'

const render = (link, props = {}) => renderToString(createSSRApp(LinkOptions, { link, compact: true, allowModal: true, ...props }))

describe('builder modal link option', () => {
  it('offers the toggle for each structured CMS destination in the compact sidebar', async () => {
    for (const type of ['home', 'current', 'entry', 'index']) {
      const html = await render({ type, open_modal: true })
      expect(html).toContain('Open in modal')
      expect(html).toMatch(/type="checkbox"[^>]*checked/)
    }
  })

  it('hides the toggle for custom URLs, anchors, dynamic values and menu editors', async () => {
    for (const type of ['none', 'url', 'anchor', 'dynamic']) {
      expect(await render({ type, url: 'https://example.org', open_modal: true })).not.toContain('Open in modal')
    }
    expect(await render({ type: 'entry' }, { allowModal: false })).not.toContain('Open in modal')
  })
})
