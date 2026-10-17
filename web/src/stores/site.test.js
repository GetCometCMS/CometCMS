import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('../api/index.js', () => ({ getActiveWorkspace: () => 'default', api: { site: { saveSettings: vi.fn(), saveTheme: vi.fn(), saveMenus: vi.fn() } } }))
import { useSiteStore } from './site.js'
import { api } from '../api/index.js'

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
