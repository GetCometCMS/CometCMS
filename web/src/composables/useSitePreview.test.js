import { afterEach, describe, expect, it, vi } from 'vitest'
import { useSitePreview } from './useSitePreview.js'
import { api } from '../api/index.js'

vi.mock('../api/index.js', () => ({ api: { site: { createPreview: vi.fn() } } }))
vi.mock('../i18n/index.js', () => ({ t: (key) => key }))
afterEach(() => { vi.unstubAllGlobals(); vi.clearAllMocks() })

function windowFixture() {
  const tab = { opener: {}, closed: false, document: { title: '', body: {} }, location: { replace: vi.fn() }, close: vi.fn() }
  const open = vi.fn(() => tab)
  vi.stubGlobal('window', { open })
  return { tab, open }
}

describe('unsaved website previews', () => {
  it('opens the tab during the click and snapshots values before subsequent edits', async () => {
    const { tab, open } = windowFixture()
    let finish
    api.site.createPreview.mockReturnValue(new Promise((resolve) => { finish = resolve }))
    const preview = useSitePreview()
    const body = { entries: [{ values: { title: 'Unsaved title' } }] }
    const pending = preview.open(body)
    expect(open).toHaveBeenCalledOnce()
    expect(tab.opener).toBeNull()
    expect(preview.opening.value).toBe(true)
    body.entries[0].values.title = 'Further edit'
    expect(api.site.createPreview.mock.calls[0][0].entries[0].values.title).toBe('Unsaved title')
    await preview.open(body)
    expect(open).toHaveBeenCalledOnce()
    finish({ data: { url: '/admin/site-preview?snapshot=private' } })
    await pending
    expect(tab.location.replace).toHaveBeenCalledWith('/admin/site-preview?snapshot=private')
    expect(preview.opening.value).toBe(false)
  })

  it('remembers the editor at click time even if navigation changes while preparing the snapshot', async () => {
    const { tab } = windowFixture()
    window.location = { href: 'https://example.test/admin/page-builder/builder/layout/posts/detail?entry=first' }
    let finish
    api.site.createPreview.mockReturnValue(new Promise((resolve) => { finish = resolve }))
    const pending = useSitePreview().open({ target: 'layout' })
    window.location.href = 'https://example.test/admin/media'
    finish({ data: { url: '/admin/site-preview?workspace=default&snapshot=private' } })
    await pending
    const url = new URL(tab.location.replace.mock.calls[0][0], 'https://example.test')
    expect(url.searchParams.get('snapshot')).toBe('private')
    expect(url.searchParams.get('returnTo')).toBe('/admin/page-builder/builder/layout/posts/detail?entry=first&workspace=default')
  })

  it('closes the pending tab and reports a rejected snapshot without saving', async () => {
    const { tab } = windowFixture()
    api.site.createPreview.mockRejectedValue(new Error('Permission denied'))
    const preview = useSitePreview()
    await expect(preview.open({})).rejects.toThrow('Permission denied')
    expect(tab.close).toHaveBeenCalledOnce()
    expect(preview.opening.value).toBe(false)
  })

  it('reports a blocked popup without creating a snapshot', async () => {
    vi.stubGlobal('window', { open: () => null })
    await expect(useSitePreview().open({})).rejects.toThrow('site.preview.popupBlocked')
    expect(api.site.createPreview).not.toHaveBeenCalled()
  })
})
