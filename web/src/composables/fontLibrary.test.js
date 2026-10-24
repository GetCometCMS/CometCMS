import { describe, expect, it } from 'vitest'
import { defaultFontSubsets, guessFontFace, referencesFont } from './fontLibrary.js'

describe('font library decisions', () => {
  it('defaults to European coverage and respects fonts with other default alphabets', () => {
    expect(defaultFontSubsets({ subsets: ['greek', 'latin-ext', 'latin'], defSubset: 'greek' })).toEqual(['latin', 'latin-ext'])
    expect(defaultFontSubsets({ subsets: ['arabic'], defSubset: 'arabic' })).toEqual(['arabic'])
  })

  it('finds font IDs in nested component drafts without mistaking substrings for references', () => {
    expect(referencesFont([{ children: [{ style: { font_family: 'fontsource-lora' } }] }], 'fontsource-lora')).toBe(true)
    expect(referencesFont({ fonts: { body: 'custom-123' } }, 'custom-123')).toBe(true)
    expect(referencesFont({ name: 'Uses fontsource-lora' }, 'fontsource-lora')).toBe(false)
    expect(referencesFont(null, 'fontsource-lora')).toBe(false)
  })

  it('suggests upload metadata from common font file names', () => {
    expect(guessFontFace('OpenSans-SemiBoldItalic.woff2')).toEqual({ family: 'Open Sans', style: 'italic', min: 600, max: 600 })
    expect(guessFontFace('brand-latin-700-normal.woff2')).toMatchObject({ style: 'normal', min: 700, max: 700 })
    expect(guessFontFace('Brand-ExtraBold.woff2')).toMatchObject({ min: 800, max: 800 })
    expect(guessFontFace('Inter[wght].woff2')).toEqual({ family: 'Inter', style: 'normal', min: 100, max: 900 })
    expect(guessFontFace('logo.woff2')).toEqual({ family: 'logo', style: 'normal', min: 400, max: 400 })
  })
})
