import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it } from 'vitest'
import LinkEditor from './LinkEditor.vue'

const render = (modelValue, props = {}) => renderToString(createSSRApp(LinkEditor, { modelValue, compact: true, allowModal: true, ...props }))

describe('link target toggles', () => {
  it('offers the modal next to the new tab toggle for website pages', async () => {
    for (const type of ['home', 'current', 'entry', 'index']) {
      const html = await render({ type, open_modal: true })
      expect(html).toMatch(/aria-pressed="true" title="Open in modal"/)
      expect(html).toMatch(/aria-pressed="false" title="Open in a new tab"/)
    }
  })

  it('offers only the new tab for other destinations and editors without modals', async () => {
    for (const type of ['url', 'none', 'anchor']) expect(await render({ type, url: 'https://example.org' })).not.toContain('Open in modal')
    expect(await render({ type: 'entry' }, { allowModal: false })).not.toContain('Open in modal')
  })
})
