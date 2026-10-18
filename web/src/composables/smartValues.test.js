import { describe, expect, it } from 'vitest'
import { compatibleField, fieldShortcut, hasInvalidValues, parseReference, parseSmartValue, reference, referenceConfig, serializeSmartValue, smartSummary } from './smartValues.js'

describe('smart tag grammar and structured values', () => {
  it('round trips each source, formatting and literal delimiters', () => {
    for (const syntax of ['current.intro_text', 'item.heading', 'single.company.phone', 'entry.services["stable_uid"].description', 'site.year', 'current.author.name | fallback:"Our team" | truncate:30', 'current.published_at | date:"long"']) {
      const value = parseSmartValue(`{{ ${syntax} }}`)
      expect(parseSmartValue(serializeSmartValue(value))).toEqual(value)
    }
    const literal = 'Hello {{ literal }} and \\ braces'
    expect(parseSmartValue(serializeSmartValue(literal))).toBe(literal)
    expect(parseSmartValue('Hi {{ current.author.name }}!')).toMatchObject({ type: 'template', parts: ['Hi ', { path: ['author', 'name'] }, '!'] })
    expect(parseSmartValue('{{ current.title | fallback:"A }} B | C" }}').filters[0].value).toBe('A }} B | C')
  })
  it('rejects expressions, malformed tags, unsafe positions and scalar/list ambiguity', () => {
    for (const text of ['{{ current.name', '{{ fetch("evil") }}', '{{ current.a + current.b }}', '{{ current.title | execute:"x" }}', '{{ current.title | truncate:0 }}', '{{ current.date | date:"arbitrary" }}', '{{ current.a.b.c.d.e.f.g }}']) {
      expect(() => parseSmartValue(text)).toThrow()
    }
    expect(() => parseSmartValue('prefix {{ current.photo }}', 'media')).toThrow()
    expect(() => parseSmartValue('<a href="{{ current.url }}">link</a>', 'html')).toThrow()
    expect(() => parseSmartValue('{{ current.photo | date:"long" }}', 'media')).toThrow()
  })
  it('keeps invalid edits detectable after selection changes or copy/paste', () => {
    expect(hasInvalidValues([{ props: { html: { type: 'invalid', text: '{{ unfinished' } } }])).toBe(true)
    expect(hasInvalidValues([{ props: { html: reference('intro') } }])).toBe(false)
  })
})

const type = { name: 'posts', fields: { intro: { type: 'text' }, author: { type: 'relation', target: 'authors' }, authors: { type: 'relation', target: 'authors', multiple: true } } }
const types = [type, { name: 'authors', fields: { bio: { type: 'textarea' }, secret: { type: 'relation', target: 'private' } } }, { name: 'private', visibility: 'private', fields: { password: { type: 'text' } } }]
describe('binding compatibility, context and shortcuts', () => {
  it('resolves field schemas through singular relations and explicit sources', () => {
    expect(referenceConfig(parseReference('current.author.bio'), { type, types })).toEqual({ type: 'textarea' })
    expect(referenceConfig(parseReference('current.authors.bio'), { type, types })).toBeNull()
    expect(referenceConfig(parseReference('current.author.secret.password'), { type, types })).toBeNull()
    expect(referenceConfig(parseReference('entry.authors["uid"].bio'), { type, types })).toEqual({ type: 'textarea' })
    expect(referenceConfig(parseReference('item.intro'), { type, types, itemFields: [{ key: 'intro', config: { type: 'html' } }] })).toEqual({ type: 'html' })
    expect(referenceConfig(parseReference('item.author.created_at'), { type, types, itemFields: [{ key: 'author', config: { type: 'relation', target: 'authors' } }] })).toEqual({ type: 'datetime' })
    expect(referenceConfig(parseReference('current.url'), { type, types })).toEqual({ type: 'url' })
  })
  it('filters incompatible fields without silently selecting the first item', () => {
    expect(compatibleField({ type: 'media' }, 'media')).toBe(true)
    expect(compatibleField({ type: 'media', multiple: true }, 'media')).toBe(false)
    expect(compatibleField({ type: 'repeater' }, 'text')).toBe(false)
    expect(compatibleField({ type: 'select', multiple: true }, 'text')).toBe(false)
    expect(compatibleField({ type: 'repeater' }, 'value')).toBe(true)
  })
  const make = (kind, props = {}) => ({ kind, props, style: {}, children: [] })
  it('creates ordinary bound elements and specialized repeaters', () => {
    expect(fieldShortcut({ key: 'title', type: 'text' }, make)).toMatchObject({ kind: 'heading', props: { text: reference('title') } })
    expect(fieldShortcut({ key: 'intro', type: 'textarea' }, make)).toMatchObject({ kind: 'text', props: { html: reference('intro') } })
    expect(fieldShortcut({ key: 'photo', type: 'media', config: {} }, make).kind).toBe('image')
    const repeater = fieldShortcut({ key: 'steps', type: 'repeater', config: { subfields: [{ key: 'title', type: 'text' }, { key: 'detail', type: 'html' }] } }, make)
    expect(repeater.kind).toBe('field')
    expect(repeater.children[0]).toMatchObject({ kind: 'heading', props: { text: reference('title', 'item'), level: 'h4' } })
    expect(repeater.children[1]).toMatchObject({ kind: 'text', props: { html: reference('detail', 'item') } })
    expect(smartSummary(repeater.children[0].props.text)).toBe('item › title')
  })
})
