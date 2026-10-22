import { ADMIN_BASE } from './basePath.js'

/** Carry the originating admin screen with a preview, independently of browser history. */
export function withPreviewReturn(url, source = typeof window !== 'undefined' ? window.location?.href : '') {
  if (!url || !source) return url
  const origin = 'https://cometcms.invalid'
  const from = new URL(source, origin)
  if (!from.pathname.startsWith(`${ADMIN_BASE}/`) || from.pathname.startsWith(`${ADMIN_BASE}/site-preview`)) return url
  const preview = new URL(url, origin)
  const workspace = preview.searchParams.get('workspace')
  if (workspace) from.searchParams.set('workspace', workspace)
  preview.searchParams.set('returnTo', from.pathname + from.search + from.hash)
  return preview.pathname + preview.search + preview.hash
}
