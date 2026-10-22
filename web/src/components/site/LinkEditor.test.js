import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it, vi } from 'vitest'
import LinkEditor from './LinkEditor.vue'

vi.mock('@iconify/vue', () => ({ Icon: { render: () => null } }))

describe('overview destination selection', () => {
  const types = [
    { name: 'pages', label: 'Pages' },
    { name: 'posts', label: 'Posts' },
    { name: 'internal', label: 'Internal', visibility: 'private' },
  ]
  const settings = {
    default_locale: 'en',
    homepage: { type: 'pages', entry: 'welcome' },
    routes: { pages: { enabled: true, index_path: '' }, posts: { enabled: false, index_path: '/posts' } },
  }

  const render = (collection = '') => renderToString(createSSRApp({
    render: () => h(LinkEditor, { modelValue: { type: 'index', collection }, types, settings, taskFocused: true }),
  }))

  it('populates the dropdown when no public type has an enabled overview URL', async () => {
    const html = await render()
    expect(html).toContain('<option value="pages">Pages</option>')
    expect(html).toContain('<option value="posts">Posts</option>')
    expect(html).not.toContain('<option value="internal"')
  })

  it('keeps an unconfigured overview selectable and explains its missing route', async () => {
    const html = await render('pages')
    expect(html).toContain('<select value="pages"')
    expect(html).toContain('<option value="pages">Pages</option>')
    expect(html).toContain('This destination has no enabled website route.')
    expect(html).not.toContain('pages ·')
  })
})
