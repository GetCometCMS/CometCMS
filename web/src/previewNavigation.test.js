import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => { vi.unstubAllGlobals(); vi.resetModules() })

describe('preview return destinations', () => {
  it.each(['', '/cms'])('captures sidebar routes and preserves their state under %j', async (base) => {
    vi.stubGlobal('document', { querySelector: () => ({ content: base }) })
    const { withPreviewReturn } = await import('./previewNavigation.js')
    const source = `${base}/admin/media?folder=photos&page=2#selection`
    const result = new URL(withPreviewReturn(`${base}/admin/site-preview?workspace=design`, source), 'https://example.test')
    expect(result.pathname).toBe(`${base}/admin/site-preview`)
    expect(result.searchParams.get('returnTo')).toBe(`${base}/admin/media?folder=photos&page=2&workspace=design#selection`)
  })

  it('captures the current admin screen for saved preview links', async () => {
    vi.stubGlobal('window', { dispatchEvent: vi.fn(), location: { href: 'https://example.test/admin/content/posts/entry?locale=de' } })
    const { api, setActiveWorkspace } = await import('./api/index.js')
    setActiveWorkspace('default')
    for (const url of [api.site.entryPreviewUrl('posts', 'entry'), api.site.indexPreviewUrl('posts'), api.site.homePreviewUrl()]) {
      expect(new URL(url, 'https://example.test').searchParams.get('returnTo')).toBe('/admin/content/posts/entry?locale=de&workspace=default')
    }
  })

  it('leaves direct previews without a backend source for the dashboard fallback', async () => {
    const { withPreviewReturn } = await import('./previewNavigation.js')
    const url = '/admin/site-preview?workspace=default'
    expect(withPreviewReturn(url, '/posts')).toBe(url)
    expect(withPreviewReturn(url, '/admin/site-preview?path=/posts')).toBe(url)
    expect(withPreviewReturn(url, '')).toBe(url)
  })
})
