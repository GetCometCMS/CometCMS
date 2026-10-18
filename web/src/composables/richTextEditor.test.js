import { describe, expect, it } from 'vitest'
import { normalizeLinkHref } from './richTextEditor.js'

describe('normalizeLinkHref', () => {
  it('keeps safe absolute and relative links', () => {
    expect(normalizeLinkHref(' https://example.com/a ')).toBe('https://example.com/a')
    expect(normalizeLinkHref('mailto:hi@example.com')).toBe('mailto:hi@example.com')
    expect(normalizeLinkHref('tel:+4912345')).toBe('tel:+4912345')
    expect(normalizeLinkHref('/about')).toBe('/about')
    expect(normalizeLinkHref('#contact')).toBe('#contact')
    expect(normalizeLinkHref('../docs')).toBe('../docs')
  })

  it('completes bare domains and email addresses', () => {
    expect(normalizeLinkHref('example.com/path')).toBe('https://example.com/path')
    expect(normalizeLinkHref('example.com:8080/x')).toBe('https://example.com:8080/x')
    expect(normalizeLinkHref('hi@example.com')).toBe('mailto:hi@example.com')
  })

  it('rejects unsafe or meaningless input', () => {
    expect(normalizeLinkHref('javascript:alert(1)')).toBe('')
    expect(normalizeLinkHref('JavaScript:alert(1)')).toBe('')
    expect(normalizeLinkHref('data:text/html,x')).toBe('')
    expect(normalizeLinkHref('just words')).toBe('')
    expect(normalizeLinkHref('')).toBe('')
  })
})
