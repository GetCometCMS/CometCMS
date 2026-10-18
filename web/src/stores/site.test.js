import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const mocks = vi.hoisted(() => ({ workspace: 'default' }))
vi.mock('../api/index.js', () => ({ getActiveWorkspace: () => mocks.workspace, api: { site: { get: vi.fn(), saveSettings: vi.fn(), saveTheme: vi.fn(), saveMenus: vi.fn() } } }))
import { useSiteStore } from './site.js'
import { api } from '../api/index.js'

const deferred = () => {
  let resolve, reject
  const promise = new Promise((yes, no) => { resolve = yes; reject = no })
  return { promise, resolve, reject }
}
const siteData = (workspace) => ({ workspace, settings: { title: workspace }, theme: {}, menus: [] })

describe('workspace loads', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mocks.workspace = 'default'
  })

  it('ignores an old workspace response after the new workspace finishes loading', async () => {
    const old = deferred()
    api.site.get.mockReturnValueOnce(old.promise).mockResolvedValueOnce({ data: siteData('second') })
    const site = useSiteStore()
    const first = site.load()
    mocks.workspace = 'second'
    await site.load()
    site.draft.settings.title = 'Pending second workspace title'
    old.resolve({ data: siteData('default') })
    await first
    expect(site.data.workspace).toBe('second')
    expect(site.draft.settings.title).toBe('Pending second workspace title')
    expect((await site.load()).workspace).toBe('second')
    expect(api.site.get).toHaveBeenCalledTimes(2)
  })

  it('clears old data while switching and shares the new pending request', async () => {
    api.site.get.mockResolvedValueOnce({ data: siteData('default') })
    const site = useSiteStore()
    await site.load()
    const pending = deferred()
    api.site.get.mockReturnValueOnce(pending.promise)
    mocks.workspace = 'second'
    const next = site.load()
    const shared = site.load()
    expect(site.data).toBeNull()
    expect(site.draft).toBeNull()
    expect(site.loading).toBe(true)
    expect(api.site.get).toHaveBeenCalledTimes(2)
    pending.resolve({ data: siteData('second') })
    expect((await next).workspace).toBe('second')
    expect((await shared).workspace).toBe('second')
  })

  it('ignores stale errors without clearing the active loading state or request', async () => {
    const old = deferred(), current = deferred()
    api.site.get.mockReturnValueOnce(old.promise).mockReturnValueOnce(current.promise)
    const site = useSiteStore()
    const first = site.load()
    mocks.workspace = 'second'
    const next = site.load()
    const failed = expect(first).rejects.toThrow('Old workspace failed')
    old.reject(new Error('Old workspace failed'))
    await failed
    expect(site.error).toBe('')
    expect(site.loading).toBe(true)
    const shared = site.load()
    expect(api.site.get).toHaveBeenCalledTimes(2)
    current.resolve({ data: siteData('second') })
    await Promise.all([next, shared])
    expect(site.loading).toBe(false)
  })

  it('invalidates pending requests even when reloading the same workspace', async () => {
    const old = deferred()
    api.site.get.mockReturnValueOnce(old.promise).mockResolvedValueOnce({ data: siteData('replacement') })
    const site = useSiteStore()
    const first = site.load()
    site.invalidate()
    await site.load()
    old.resolve({ data: siteData('old') })
    await first
    expect(site.data.workspace).toBe('replacement')
    expect(site.draft.settings.title).toBe('replacement')
  })
})

describe('website revision restoration', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('applies a restored theme and preserves pending edits to settings and menus', () => {
    const site = useSiteStore()
    site.data = { settings: { title: 'Saved' }, theme: { base_size: 17 }, menus: [] }
    site.resetDraft()
    site.draft.settings.title = 'Pending settings'
    site.draft.menus.push({ name: 'pending-menu' })
    site.draft.theme.base_size = 20
    const restored = { base_size: 15, custom_css: '.old {}' }

    site.applyRestoredTheme(restored)

    expect(site.data.theme).toEqual(restored)
    expect(site.draft.theme).toEqual(restored)
    expect(site.draft.settings.title).toBe('Pending settings')
    expect(site.draft.menus).toEqual([{ name: 'pending-menu' }])
    expect(site.dirtyParts).toEqual(['settings', 'menus'])
    site.draft.theme.base_size = 16
    expect(site.data.theme.base_size).toBe(15)
    expect(restored.base_size).toBe(15)
  })
})


describe('menu saves', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })

  it('keeps incomplete menu drafts and reports errors before saving other dirty parts', async () => {
    const site = useSiteStore()
    site.data = { settings: { title: 'Saved' }, theme: {}, menus: [] }
    site.resetDraft()
    site.draft.settings.title = 'Pending'
    site.draft.menus = [{ name: 'main', items: [{ id: 'bad', label: 'Custom', link: { type: 'url', url: '' }, children: [] }] }]
    await expect(site.save()).rejects.toThrow()
    expect(site.fieldErrors['menus.0.items.0']).toBeTruthy()
    expect(api.site.saveSettings).not.toHaveBeenCalled()
    expect(api.site.saveMenus).not.toHaveBeenCalled()
    expect(site.draft.menus[0].items[0].id).toBe('bad')
    expect(site.saving).toBe(false)
  })

  it('saves nested menus with their IDs and resets the dirty flag after success', async () => {
    const site = useSiteStore()
    site.data = { settings: {}, theme: {}, menus: [] }
    site.resetDraft()
    const menus = [{ name: 'main', items: [{ id: 'parent', label: 'Group', link: { type: 'none' }, children: [{ id: 'child', label: 'Contact', link: { type: 'anchor', anchor: 'contact' }, children: [] }] }] }]
    site.draft.menus = menus
    api.site.saveMenus.mockResolvedValue({ data: menus })
    await site.save()
    expect(api.site.saveMenus).toHaveBeenCalledWith(menus)
    expect(site.isDirty).toBe(false)
    expect(site.draft.menus[0].items[0].children[0].id).toBe('child')
    expect(site.draft.menus).not.toBe(site.data.menus)
  })
})

describe('design edits from the builder', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })

  it('saves only the requested parts and keeps other pending edits', async () => {
    const site = useSiteStore()
    site.data = { settings: { title: 'Saved' }, theme: { base_size: 17 }, menus: [] }
    site.resetDraft()
    site.draft.settings.title = 'Pending'
    site.draft.theme.base_size = 20
    api.site.saveTheme.mockResolvedValue({ data: { base_size: 20 } })
    await site.save({ parts: ['theme'] })
    expect(api.site.saveTheme).toHaveBeenCalledWith({ base_size: 20 })
    expect(api.site.saveSettings).not.toHaveBeenCalled()
    expect(site.dirtyParts).toEqual(['settings'])
    expect(site.draft.settings.title).toBe('Pending')
  })

  it('discards pending design edits only', () => {
    const site = useSiteStore()
    site.data = { settings: { title: 'Saved' }, theme: { base_size: 17 }, menus: [] }
    site.resetDraft()
    site.draft.settings.title = 'Pending'
    site.draft.theme.base_size = 20
    site.discardTheme()
    expect(site.draft.theme).toEqual({ base_size: 17 })
    expect(site.draft.theme).not.toBe(site.data.theme)
    expect(site.dirtyParts).toEqual(['settings'])
  })
})
