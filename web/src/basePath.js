// CometCMS can be installed in a sub-directory (e.g. https://example.com/cms/).
// The PHP shell publishes that prefix in <meta name="comet-base">; every URL the
// admin builds — API calls, router history, images, public API examples — must
// start from it instead of assuming the site root.

function readBase() {
  if (typeof document === 'undefined') return ''
  const content = document.querySelector('meta[name="comet-base"]')?.content ?? ''
  return content.replace(/\/+$/, '')
}

/** Path prefix of the installation, '' at the site root, e.g. '/cms' otherwise. */
export const APP_BASE = readBase()

/** Path of the admin UI, e.g. '/admin' or '/cms/admin'. */
export const ADMIN_BASE = `${APP_BASE}/admin`

/** Path of the admin JSON API. */
export const ADMIN_API_BASE = `${ADMIN_BASE}/api`

/** Absolute URL of the installation root, used when showing copyable public URLs. */
export function appOrigin() {
  return typeof window === 'undefined' ? APP_BASE : `${window.location.origin}${APP_BASE}`
}

/** Path to a file shipped in web/public (served from the admin build). */
export function adminAsset(path) {
  return `${ADMIN_BASE}/${String(path).replace(/^\/+/, '')}`
}
