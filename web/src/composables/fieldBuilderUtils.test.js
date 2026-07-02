import { describe, expect, it } from 'vitest'
import {
  clamp,
  normalizeDisplayDecimals,
  normalizeKey,
  optionsToText,
  parseSelectOptions,
  rangeDefaults,
  selectOptions,
  serializeSelectOptions,
  uniqueKey,
} from './fieldBuilderUtils'

describe('fieldBuilderUtils', () => {
  it('normalizes field keys for schema storage', () => {
    expect(normalizeKey(' Hero Title! ')).toBe('hero_title')
    expect(normalizeKey('already_valid')).toBe('already_valid')
    expect(normalizeKey(null)).toBe('')
  })

  it('creates unique keys against used and reserved names', () => {
    expect(uniqueKey('title', new Set(['title', 'title_2']), new Set(['slug']))).toBe('title_3')
    expect(uniqueKey('summary', new Set(), new Set(['title']))).toBe('summary')
  })

  it('parses textarea option lists', () => {
    expect(parseSelectOptions('Draft\n\n Published \nArchived')).toEqual([
      { key: 'Draft', label: 'Draft' },
      { key: 'Published', label: 'Published' },
      { key: 'Archived', label: 'Archived' },
    ])
  })

  it('parses key:Label option lines', () => {
    expect(parseSelectOptions('draft:Draft\npublished:Published\narchived')).toEqual([
      { key: 'draft', label: 'Draft' },
      { key: 'published', label: 'Published' },
      { key: 'archived', label: 'archived' },
    ])
  })

  it('serializes to plain array when all keys equal labels', () => {
    const parsed = parseSelectOptions('Draft\nPublished\nArchived')
    expect(serializeSelectOptions(parsed)).toEqual(['Draft', 'Published', 'Archived'])
  })

  it('serializes to object when any key differs from label', () => {
    const parsed = parseSelectOptions('draft:Draft\npublished:Published\narchived')
    expect(serializeSelectOptions(parsed)).toEqual({ draft: 'Draft', published: 'Published', archived: 'archived' })
  })

  it('converts stored options back to textarea text', () => {
    expect(optionsToText(['Draft', 'Published'])).toBe('Draft\nPublished')
    expect(optionsToText({ draft: 'Draft', published: 'Published', archived: 'archived' })).toBe('draft:Draft\npublished:Published\narchived')
  })

  it('normalizes select options for editor controls', () => {
    expect(selectOptions(['Draft', 'Published'])).toEqual([
      { value: 'Draft', label: 'Draft' },
      { value: 'Published', label: 'Published' },
    ])
    expect(selectOptions({ draft: 'Draft', published: 'Published' })).toEqual([
      { value: 'draft', label: 'Draft' },
      { value: 'published', label: 'Published' },
    ])
  })

  it('clamps range defaults and repairs invalid range settings', () => {
    expect(clamp(12, 0, 10)).toBe(10)
    expect(rangeDefaults({ min: 10, max: 5, default: 20, step: -1, display_decimals: '2' })).toEqual({
      min: 10,
      max: 10,
      default: 10,
      step: 1,
      display_decimals: 2,
    })
  })

  it('normalizes display decimal settings', () => {
    expect(normalizeDisplayDecimals('full')).toBe('full')
    expect(normalizeDisplayDecimals(3)).toBe(3)
    expect(normalizeDisplayDecimals(4)).toBe(0)
  })
})
