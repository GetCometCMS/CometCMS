import { describe, expect, it } from 'vitest'
import { templatePartBounds } from './builderTemplateParts.js'

function element(left, top, width, height, children = []) {
  return {
    children,
    getBoundingClientRect: () => ({ left, top, width, height, right: left + width, bottom: top + height }),
  }
}

describe('template part hover bounds', () => {
  it('covers the entire header including its padding', () => {
    expect(templatePartBounds(element(0, 0, 1280, 100, [element(24, 12, 1232, 76)])))
      .toEqual({ left: 0, top: 0, width: 1280, height: 100 })
  })

  it('finds sticky sections when the header uses display:contents', () => {
    const header = element(0, 0, 0, 0, [element(0, 0, 820, 64), element(0, 64, 820, 32)])
    expect(templatePartBounds(header)).toEqual({ left: 0, top: 0, width: 820, height: 96 })
  })

  it('finds a floating header with a zero height wrapper', () => {
    const header = element(0, -300, 390, 0, [element(0, 0, 390, 72)])
    expect(templatePartBounds(header)).toEqual({ left: 0, top: 0, width: 390, height: 72 })
  })

  it('uses viewport coordinates for a footer after scrolling', () => {
    expect(templatePartBounds(element(0, 420, 1280, 160)))
      .toEqual({ left: 0, top: 420, width: 1280, height: 160 })
  })

  it('does not highlight absent or hidden template content', () => {
    expect(templatePartBounds(element(0, 0, 1280, 0, [element(0, 0, 0, 0)]))).toBeNull()
  })
})
