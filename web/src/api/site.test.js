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
  })
})
