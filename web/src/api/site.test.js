import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('../i18n/index.js', () => ({ t: (key) => key }))

afterEach(() => {
  vi.unstubAllGlobals()
  vi.resetModules()
})

describe('entry preview URLs', () => {
  it.each(['', '/cms'])('keeps the admin prefix under installation %j', async (base) => {
    vi.stubGlobal('document', {
      querySelector: (selector) => selector === 'meta[name="comet-base"]' ? { content: base } : null,
    })
    const { api, setActiveWorkspace } = await import('./index.js')
    setActiveWorkspace('verkehrsinitiative-at')

    expect(api.site.entryPreviewUrl('startseite', 'startseite', 'de')).toBe(
      `${base}/admin/site-preview/startseite/startseite?workspace=verkehrsinitiative-at&locale=de`,
    )
    expect(api.site.indexPreviewUrl('blog', 'de')).toBe(`${base}/admin/site-preview/blog?workspace=verkehrsinitiative-at&locale=de`)
    expect(api.site.homePreviewUrl()).toBe(`${base}/admin/site-preview?workspace=verkehrsinitiative-at`)
  })
})

describe('new workspace font installation requests', () => {
  it('targets the new workspace without changing the workspace open in the admin', async () => {
    const fetch = vi.fn().mockResolvedValue({ ok: true, headers: new Headers(), json: async () => ({ data: {} }) })
    vi.stubGlobal('fetch', fetch)
    const { api, getActiveWorkspace, setActiveWorkspace } = await import('./index.js')
    setActiveWorkspace('current-website')
    await api.site.get('new-website')
    await api.fonts.install({ id: 'inter' }, 'new-website')
    await api.fonts.step('job', 0, 'new-website')
    await api.site.saveTheme({ fonts: {} }, 'new-website')
    expect(fetch).toHaveBeenCalledTimes(4)
    for (const [, options] of fetch.mock.calls) {
      expect(options.headers['X-Comet-Workspace']).toBe('new-website')
    }
    expect(getActiveWorkspace()).toBe('current-website')
  })
})
