import { describe, expect, it } from 'vitest'
import { DEFAULT_ICON, barOptions, numberBar } from './controlOptions.js'

const title = (value) => (value === '' ? 'Default' : value)
const theme = {
  space: { xs: 4, sm: 8, md: 16, lg: 32, xl: 56, '2xl': 96 },
  radii: { sm: 4, md: 8, lg: 14 },
  radius: 'lg',
}

describe('builder option bars', () => {
  it('mixes icons and token labels for corners, with theme values as hints', () => {
    const options = barOptions('style', 'radius', ['', 'none', 'theme', 'sm', 'md', 'lg', 'full'], { title, theme })
    expect(options.map((option) => option.icon ?? option.label)).toEqual([
      DEFAULT_ICON, 'mdi:square-outline', 'mdi:square-rounded-outline', 'S', 'M', 'L', 'mdi:circle-outline',
    ])
    expect(options.find((option) => option.value === 'md').title).toBe('md · 8px')
    expect(options.find((option) => option.value === 'theme').title).toBe('theme · 14px')
  })

  it('shows spacing tokens for padding and spacers, not for button sizes', () => {
    const padding = barOptions('style', 'padding_y', ['', 'none', 'xs', 'sm', 'md'], { title, theme })
    expect(padding.map((option) => option.label)).toEqual(['–', '0', 'XS', 'S', 'M'])
    expect(padding[4].title).toBe('md · 16px')

    expect(barOptions('props', 'size', ['xs', 'md'], { title, theme, kind: 'spacer' })[1].title).toBe('md · 16px')
    expect(barOptions('props', 'size', ['sm', 'md', 'lg'], { title, theme, kind: 'button' })[1].title).toBe('md')
  })

  it('uses theme swatches for colors and falls back when a bar would not fit', () => {
    const colors = barOptions('style', 'background', ['', 'primary'], { title, colors: { primary: '#2563eb' } })
    expect(colors[1].swatch).toBe('#2563eb')
    expect(barOptions('props', 'tag', ['', 'p', 'h1', 'span', 'div'], { title })).toBeNull()
    expect(numberBar(1, 6)).toHaveLength(6)
    expect(numberBar(1, 100)).toBeNull()
  })
})
