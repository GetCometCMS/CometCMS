import { describe, expect, it } from 'vitest'
import { destinationAvailable, eligibleTypes, hasListPage, isSiteLive, entryIdentifier, entryTitle, linkIssue, menuDefaultLabel, menuErrors, safeLinkUrl, validAnchor } from './siteLinks.js'
const types = [
  { name: 'about', label: 'About' },
  { name: 'home' },
  { name: 'blog' }, { name: 'portfolio' },
  { name: 'private', visibility: 'private' },
]
const settings = { homepage: { type: 'home', entry: '' }, routes: {
  about: { enabled: true, index_path: '/about' }, home: { enabled: false, index_enabled: true },
  blog: { enabled: true, index_enabled: true, index_path: '/blog' },
  portfolio: { enabled: false, index_enabled: true }, private: { enabled: true },
} }

describe('automatic menu labels', () => {
  const t = (key) => key === 'site.link.home' ? 'Home' : key

  it('uses the resolved page title and leaves missing pages without a fabricated label', () => {
    const link = { type: 'entry', collection: 'about', entry: 'about' }
    expect(menuDefaultLabel(link, types, settings, 'About us', t)).toBe('About us')
    expect(menuDefaultLabel(link, types, settings, '', t)).toBe('')
  })

  it('matches website defaults for home, listings, URLs, anchors, and groups', () => {
    expect(menuDefaultLabel({ type: 'home' }, types, settings, 'Landing Page', t)).toBe('Home')
    expect(menuDefaultLabel({ type: 'index', collection: 'about' }, types, settings, '', t)).toBe('About')
    expect(menuDefaultLabel({ type: 'index', collection: 'blog' }, types, { routes: { blog: { index_title: 'Latest posts' } } }, '', t)).toBe('Latest posts')
    expect(menuDefaultLabel({ type: 'url', url: '/contact' }, types, settings, '', t)).toBe('/contact')
    expect(menuDefaultLabel({ type: 'anchor', anchor: 'team' }, types, settings, '', t)).toBe('#team')
    expect(menuDefaultLabel({ type: 'none' }, types, settings, '', t)).toBe('')
  })
})

describe('website live state', () => {
  it('is live with a public homepage or public frontend pages, and headless otherwise', () => {
    const off = { homepage: { type: '', entry: '' }, routes: { blog: { enabled: false } } }
    expect(isSiteLive(off, types)).toBe(false)
    expect(isSiteLive({ ...off, homepage: { type: 'about', entry: '' } }, types)).toBe(true)
    expect(isSiteLive({ ...off, routes: { blog: { enabled: true } } }, types)).toBe(true)
    expect(isSiteLive({ ...off, homepage: { type: 'private', entry: '' }, routes: { private: { enabled: true } } }, types)).toBe(false)
    expect(isSiteLive(null, types)).toBe(false)
  })
})

describe('website destinations', () => {
  it('separates routable content and listings, retaining homepage exceptions', () => {
    expect(eligibleTypes(types, settings, 'entry').map((type) => type.name)).toEqual(['about', 'blog'])
    expect(eligibleTypes(types, settings, 'index').map((type) => type.name)).toEqual(['home', 'blog'])
    const homepage = { ...settings, homepage: { type: 'portfolio', entry: '' } }
    expect(eligibleTypes(types, homepage, 'index').map((type) => type.name)).toEqual(['blog', 'portfolio'])
    const homeEntry = { ...settings, homepage: { type: 'home', entry: 'welcome' } }
    expect(eligibleTypes(types, homeEntry, 'entry').map((type) => type.name)).toEqual(['about', 'home', 'blog'])
  })

  it('hides list pages that are not turned on, even with a URL or as the homepage', () => {
    const off = { ...settings, homepage: { type: 'blog', entry: '' }, routes: { ...settings.routes, blog: { enabled: true, index_enabled: false, index_path: '/blog' } } }
    expect(eligibleTypes(types, off, 'index').map((type) => type.name)).toEqual([])
    expect(destinationAvailable({ type: 'index', collection: 'blog' }, types, off)).toBe(false)
    expect(destinationAvailable({ type: 'index', collection: 'about' }, types, settings)).toBe(false)
    expect(hasListPage(settings, 'blog')).toBe(true)
    expect(hasListPage(settings, 'about')).toBe(false)
  })

  it('does not treat unrelated entries as routable when only the homepage entry is enabled', () => {
    const homeSettings = { ...settings, homepage: { type: 'portfolio', entry: 'stable-uid' } }
    expect(destinationAvailable({ type: 'entry', collection: 'portfolio', entry: 'stable-uid' }, types, homeSettings)).toBe(true)
    expect(destinationAvailable({ type: 'entry', collection: 'portfolio', entry: 'stable-uid' }, types, homeSettings, { uid: 'stable-uid', slug: 'welcome' })).toBe(true)
    expect(destinationAvailable({ type: 'entry', collection: 'portfolio', entry: 'other' }, types, homeSettings)).toBe(false)
    expect(destinationAvailable({ type: 'index', collection: 'portfolio' }, types, homeSettings)).toBe(false)
    expect(destinationAvailable({ type: 'entry', collection: 'private', entry: 'x' }, types, settings)).toBe(false)
  })

  it('uses localized titles and immutable identifiers', () => {
    expect(entryTitle({ title: 'About', values: { title: { type: 'localized', values: { en: 'About', de: 'Über uns' } } } }, 'de')).toBe('Über uns')
    expect(entryIdentifier({ uid: 'stable', slug: 'changed', id: 'old' })).toBe('stable')
    expect(entryIdentifier({ slug: 'legacy', id: 'old' })).toBe('')
  })

  it('validates manual anchors and custom links without requiring destination availability', () => {
    expect(validAnchor(' #contact-us ')).toBe(true)
    expect(validAnchor('contact us')).toBe(false)
    expect(validAnchor('a'.repeat(81))).toBe(false)
    for (const url of ['https://example.test', '/about#team', '#team', 'mailto:a@example.test', 'tel:+43123']) expect(safeLinkUrl(url)).toBe(true)
    expect(safeLinkUrl('javascript:alert(1)')).toBe(false)
    expect(linkIssue({ type: 'entry', collection: 'deleted-type', entry: 'old-id' })).toBe('')
    expect(linkIssue({ type: 'anchor', anchor: '' })).toBe('invalidAnchor')
    expect(linkIssue({ type: 'none' }, '')).toBe('groupLabel')
  })

  it('reports item paths before saving incomplete destinations', () => {
    const menus = [{ items: [
      { label: '', link: { type: 'none' }, children: [] },
      { label: 'Bad', link: { type: 'url', url: 'data:text/html,bad' }, children: [] },
      { label: 'Section', link: { type: 'home', anchor: 'two words' }, children: [] },
    ] }]
    expect(Object.keys(menuErrors(menus, (key) => key))).toEqual(['menus.0.items.0', 'menus.0.items.1', 'menus.0.items.2'])
  })
})
