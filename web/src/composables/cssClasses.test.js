import { describe, expect, it } from 'vitest'
import { classesFromSelector, normalizeCssClasses } from './cssClasses.js'

describe('builder CSS classes', () => {
  it('reads compound, grouped and pseudo-class selectors', () => {
    expect(classesFromSelector('.hero.card:hover, :is(.intro, .card) > .button::before')).toEqual(['hero', 'card', 'intro', 'button'])
  })

  it('ignores dots in attributes, strings, comments and unsupported class names', () => {
    expect(classesFromSelector('[href="logo.svg"][data-note=.ignored] .real:not([title=".fake"]) /* .comment */ .sm\\:wide .123bad .foo\\.bar')).toEqual(['real'])
  })

  it('decodes CSS escapes and preserves class case', () => {
    expect(classesFromSelector('.\\68 ero .Hero .hero .\\_card')).toEqual(['hero', 'Hero', '_card'])
  })

  it('keeps stored classes compatible with backend validation', () => {
    expect(normalizeCssClasses(' hero\ncard hero\t_custom invalid! 2bad')).toEqual(['hero', 'card', '_custom'])
    expect(normalizeCssClasses(null)).toEqual([])
    expect(normalizeCssClasses(['a'.repeat(61), 'b'.repeat(62)])).toEqual(['a'.repeat(61)])
    expect(normalizeCssClasses(Array.from({ length: 10 }, (_, i) => `class-${i}`))).toHaveLength(8)
  })
})
