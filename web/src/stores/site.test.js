import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('../api/index.js', () => ({ getActiveWorkspace: () => 'default', api: {} }))
import { useSiteStore } from './site.js'

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
