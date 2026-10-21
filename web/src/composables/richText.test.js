import { describe, expect, it } from 'vitest'
import { markdownToHtml, turndown } from './richText.js'

describe('rich text Markdown storage', () => {
  it.each(['u', 's', 'mark', 'sub', 'sup'])(
    'preserves %s formatting across repeated Markdown round trips',
    (tag) => {
      const html = `<p>Before <${tag}><strong>formatted</strong> text</${tag}> after.</p>`
      const markdown = turndown.turndown(html)
      const restored = markdownToHtml(markdown)
      expect(restored).toContain(`<${tag}><strong>formatted</strong> text</${tag}>`)
      expect(turndown.turndown(restored)).toBe(markdown)
    },
  )

  it('preserves new-tab links and their nested formatting', () => {
    const link = '<a href="/about" target="_blank" rel="noopener noreferrer"><em>About us</em></a>'
    const markdown = turndown.turndown(`<p>${link}</p>`)
    expect(markdownToHtml(markdown)).toContain(link)
    expect(turndown.turndown(markdownToHtml(markdown))).toBe(markdown)
  })

  it('continues to use native Markdown for headings, common marks and ordinary links', () => {
    const markdown = turndown.turndown('<h2>Title</h2><p><strong>Bold</strong> <em>italic</em> <a href="/about">About</a></p>')
    expect(markdown).toBe('## Title\n\n**Bold** _italic_ [About](/about)')
    const restored = markdownToHtml(markdown)
    expect(restored).toContain('<h2>Title</h2>')
    expect(restored).toContain('<strong>Bold</strong>')
    expect(restored).toContain('<a href="/about">About</a>')
  })
})
