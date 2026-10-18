import { describe, expect, it } from 'vitest'
import { withPreviewStyles } from './previewDocument.js'

describe('preview document styles', () => {
  it('includes editor outlines in the head before any builder nodes are parsed', () => {
    const html = '<!doctype html><html><head><style>body{color:red}</style></head><body><div data-node="row"></div></body></html>'
    const css = '[data-node]{outline:1px solid transparent;transition:outline-color .12s}'
    const result = withPreviewStyles(html, css)
    expect(result).toContain(`<style>body{color:red}</style><style data-preview-styles>${css}</style></head>`)
    expect(result.indexOf(css)).toBeLessThan(result.indexOf('<body>'))
    expect(result).toContain('<div data-node="row"></div>')
  })

  it('preserves ordinary previews and empty documents', () => {
    const html = '<html><head></head><body>Preview</body></html>'
    expect(withPreviewStyles(html, '')).toBe(html)
    expect(withPreviewStyles('', 'body{color:red}')).toBe('')
  })

  it('handles uppercase head tags and literal CSS replacement characters', () => {
    expect(withPreviewStyles('<HEAD></HEAD><BODY></BODY>', '.label::before{content:"$&"}'))
      .toContain('<style data-preview-styles>.label::before{content:"$&"}</style></head><BODY>')
  })
})
