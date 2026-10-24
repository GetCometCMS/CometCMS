import { describe, expect, it } from 'vitest'
import { FRAME_DEFAULT, FRAME_NONE, frameComponents, frameLabel, frameValue, withFrame } from './frames.js'

describe('frames', () => {
  it('reads the default where nothing is assigned', () => {
    expect(frameValue({}, 'blog', 'detail', 'header')).toBe(FRAME_DEFAULT)
    expect(frameValue({ blog: { detail: { header: FRAME_NONE } } }, 'blog', 'detail', 'header')).toBe(FRAME_NONE)
    expect(frameValue(undefined, 'blog', 'index', 'footer')).toBe(FRAME_DEFAULT)
  })

  it('stores only what differs from the default and leaves the original untouched', () => {
    const original = { about: { detail: { footer: 'promo' } } }
    const assigned = withFrame(original, 'blog', 'detail', 'header', FRAME_NONE)
    expect(assigned).toEqual({ about: { detail: { footer: 'promo' } }, blog: { detail: { header: FRAME_NONE } } })
    expect(original).toEqual({ about: { detail: { footer: 'promo' } } })
    expect(withFrame(assigned, 'blog', 'detail', 'header', FRAME_DEFAULT)).toEqual(original)
    expect(withFrame(original, 'about', 'detail', 'footer', FRAME_DEFAULT)).toEqual({})
  })

  it('offers section components that are shared or belong to the type', () => {
    const components = [
      { id: 'shared', root: 'section', type: '' },
      { id: 'blog', root: 'section', type: 'blog' },
      { id: 'about', root: 'section', type: 'about' },
      { id: 'column', root: 'column', type: '' },
    ]
    expect(frameComponents(components, 'blog').map((component) => component.id)).toEqual(['shared', 'blog'])
  })

  it('names what a header or footer shows', () => {
    const t = (key) => key
    expect(frameLabel(t, FRAME_DEFAULT, 'header', [])).toBe('site.frame.default.header')
    expect(frameLabel(t, FRAME_NONE, 'footer', [])).toBe('site.frame.none.footer')
    expect(frameLabel(t, 'promo', 'footer', [{ id: 'promo', name: 'Promo footer' }])).toBe('Promo footer')
    expect(frameLabel(t, 'gone', 'footer', [])).toBe('site.frame.missing')
  })
})
