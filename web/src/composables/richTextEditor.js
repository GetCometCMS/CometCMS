import StarterKit from '@tiptap/starter-kit'
import Highlight from '@tiptap/extension-highlight'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'

const SAFE_SCHEMES = ['http:', 'https:', 'mailto:', 'tel:']

/** Tiptap extensions for rich text; the output stays within the server's HtmlSanitizer allow-list. */
export function richTextExtensions() {
  return [
    StarterKit.configure({
      link: { openOnClick: false, autolink: true, defaultProtocol: 'https', HTMLAttributes: { target: null, rel: null } },
    }),
    Highlight,
    Subscript,
    Superscript,
  ]
}

/** Turns what an editor typed into a safe href, or '' when the link would be rejected on save. */
export function normalizeLinkHref(input) {
  const value = String(input ?? '').trim()
  if (!value) return ''
  if (/^(#|\/|\.\.?\/)/.test(value)) return value
  const scheme = value.match(/^([a-z][a-z0-9+.-]*):(?!\d)/i)?.[1]
  if (scheme) return SAFE_SCHEMES.includes(`${scheme.toLowerCase()}:`) ? value : ''
  if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return `mailto:${value}`
  return /^[^\s/]+\.[^\s/]+/.test(value) ? `https://${value}` : ''
}
