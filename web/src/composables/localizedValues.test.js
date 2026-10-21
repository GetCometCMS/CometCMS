import { describe, expect, it } from 'vitest'
import { entryForLocale, localizedState, removeLocalized, resolveLocalized, resolveLocalizedTree, setLocalized, sourceHash } from './localizedValues.js'

describe('resource-owned localized values', () => {
  it.each(['', null, false, 0, []])('preserves intentional empty values: %j', (empty) => {
    let value = setLocalized(null, 'en', 'Source')
    expect(resolveLocalized(value, 'de')).toBe('Source')
    expect(localizedState(value, 'de')).toBe('inherited')
    value = setLocalized(value, 'de', empty)
    expect(resolveLocalized(value, 'de')).toEqual(empty)
    expect(localizedState(value, 'de')).toBe('translated')
    value = setLocalized(value, 'en', 'New source')
    expect(localizedState(value, 'de')).toBe('review')
    expect(resolveLocalized(value, 'de')).toEqual(empty)
    expect(resolveLocalized(removeLocalized(value, 'de'), 'de')).toBe('New source')
  })

  it('resolves whole smart templates while preserving reference objects', () => {
    const template = { type: 'template', parts: ['Hello ', { type: 'reference', source: 'current', path: ['title'] }] }
    const node = { id: 'stable', props: { html: setLocalized('', 'de', template) } }
    expect(resolveLocalizedTree(node, 'de')).toEqual({ id: 'stable', props: { html: template } })
    expect(node.props.html.type).toBe('localized')
  })

  it('projects values and publication for exactly one language', () => {
    const entry = { values: { title: setLocalized(setLocalized(null, 'en', 'Home'), 'de', 'Startseite'), sku: 'shared' }, publication: { en: { status: 'published' }, de: { status: 'draft' } } }
    expect(entryForLocale(entry, 'de')).toMatchObject({ title: 'Startseite', sku: 'shared', status: 'draft', locale: 'de' })
    expect(entryForLocale(entry, 'en')).toMatchObject({ title: 'Home', status: 'published' })
    expect(entryForLocale(entry, 'fr')).toMatchObject({ title: 'Home', status: 'draft' })
  })

  it('uses the same UTF-8 source fingerprint as the server', () => {
    expect(sourceHash('Grüße 🌍')).toBe('000ca860')
  })
})
