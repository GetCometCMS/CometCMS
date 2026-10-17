import { describe, expect, it } from 'vitest'
import { themeColorOptions } from './themeColors.js'

const title = (value) => value
const theme = {
  mode: 'light',
  colors: { primary: '#2563eb', background: '#ffffff' },
  dark_colors: { primary: '#60a5fa', background: '#0b1120' },
  custom_colors: { 'custom-brand': { label: 'Brand', color: '#ab1234', dark_color: '#ff5678' } },
}

describe('theme color options', () => {
  it('combines predefined and named custom colors with stable values', () => {
    const options = themeColorOptions(theme, ['', 'primary', 'inverse'], title)
    expect(options).toEqual([
      { value: 'primary', label: 'primary', swatch: '#2563eb', custom: false },
      { value: 'inverse', label: 'inverse', swatch: '#ffffff', custom: false },
      { value: 'custom-brand', label: 'Brand', swatch: '#ab1234', custom: true },
    ])
  })

  it('uses dark colors for a dark theme and light swatches in auto mode', () => {
    expect(themeColorOptions({ ...theme, mode: 'dark' }, ['primary'], title).map((option) => option.swatch)).toEqual(['#60a5fa', '#ff5678'])
    expect(themeColorOptions({ ...theme, mode: 'auto' }, ['primary'], title).map((option) => option.swatch)).toEqual(['#2563eb', '#ab1234'])
  })

  it('handles themes created before custom colors were available', () => {
    expect(themeColorOptions(null, ['', 'primary'], title)).toEqual([{ value: 'primary', label: 'primary', swatch: '#cccccc', custom: false }])
    expect(themeColorOptions({}, [], title)).toEqual([])
  })
})
