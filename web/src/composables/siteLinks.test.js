import { describe, expect, it } from 'vitest'
import { destinationAvailable, eligibleTypes, isSiteLive, entryIdentifier, entryTitle, linkIssue, menuErrors, safeLinkUrl, validAnchor } from './siteLinks.js'
const types = [
  { name: 'about', label: 'About' },
  { name: 'home' },
  { name: 'blog' }, { name: 'portfolio' },
  { name: 'private', visibility: 'private' },
]
const settings = { homepage: { type: 'home', entry: '' }, routes: {
  about: { enabled: true }, blog: { enabled: true, index_path: '/blog' },
  portfolio: { enabled: false }, private: { enabled: true },
} }

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
  it('offers public overview types while limiting entries to routable content', () => {
    expect(eligibleTypes(types, settings, 'entry').map((type) => type.name)).toEqual(['about', 'blog'])
    expect(eligibleTypes(types, settings, 'index').map((type) => type.name)).toEqual(['about', 'home', 'blog', 'portfolio'])
    const homepage = { ...settings, homepage: { type: 'portfolio', entry: '' } }
    expect(eligibleTypes(types, homepage, 'index').map((type) => type.name)).toEqual(['about', 'home', 'blog', 'portfolio'])
    const homeEntry = { ...settings, homepage: { type: 'home', entry: 'welcome' } }
    expect(eligibleTypes(types, homeEntry, 'entry').map((type) => type.name)).toEqual(['about', 'home', 'blog'])
  })

  it('allows overview selection before routes are configured and still reports availability', () => {
    expect(eligibleTypes(types, null, 'index')).toEqual(types.filter((type) => type.visibility !== 'private'))
    for (const [collection, available] of [['about', false], ['home', true], ['blog', true], ['portfolio', false], ['private', false]]) {
      expect(destinationAvailable({ type: 'index', collection }, types, settings)).toBe(available)
    }
  })

  it('does not treat unrelated entries as routable when only the homepage entry is enabled', () => {
    const homeSettings = { ...settings, homepage: { type: 'portfolio', entry: 'welcome' } }
    expect(destinationAvailable({ type: 'entry', collection: 'portfolio', entry: 'welcome' }, types, homeSettings)).toBe(true)
    expect(destinationAvailable({ type: 'entry', collection: 'portfolio', entry: 'stable-uid' }, types, homeSettings, { uid: 'stable-uid', slug: 'welcome' })).toBe(true)
    expect(destinationAvailable({ type: 'entry', collection: 'portfolio', entry: 'other' }, types, homeSettings)).toBe(false)
    expect(destinationAvailable({ type: 'index', collection: 'portfolio' }, types, homeSettings)).toBe(false)
    expect(destinationAvailable({ type: 'entry', collection: 'private', entry: 'x' }, types, settings)).toBe(false)
  })

  it('uses localized titles and stable identifiers, falling back for legacy content', () => {
    expect(entryTitle({ title: 'About', values: { title: { type: 'localized', values: { en: 'About', de: 'Über uns' } } } }, 'de')).toBe('Über uns')
    expect(entryIdentifier({ uid: 'stable', slug: 'changed', id: 'old' })).toBe('stable')
    expect(entryIdentifier({ slug: 'legacy', id: 'old' })).toBe('legacy')
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
