import { useAuthStore } from '../stores/auth.js'
import { allowsPermission } from '../stores/permissions.js'

/**
 * Resource-aware permission checks for hiding actions a user cannot perform.
 * The server remains the authority; this only keeps the UI from offering
 * buttons that are guaranteed to fail with "forbidden".
 */
export function usePermissions() {
  const auth = useAuthStore()

  function anyResource(action, resources) {
    return [...new Set(resources)].some((resource) => auth.can(action, resource))
  }

  /** Whether a deny grant matches exactly this resource. */
  function isDenied(action, resource) {
    const denies = (auth.user?.capabilities?.permissions ?? [])
      .filter((grant) => grant.effect === 'deny')
      .map((grant) => ({ ...grant, effect: 'allow' }))
    return allowsPermission(denies, action, resource)
  }

  /** Content actions on a collection, optionally narrowed to one entry. */
  function canContent(action, collection, entry = null) {
    const entryResources = [entry?.slug, entry?.id]
      .filter(Boolean)
      .map((key) => `content:${collection}:${key}`)
    // As on the server, a deny on the entry itself beats broader allows.
    if (entryResources.some((resource) => isDenied(action, resource))) return false
    return anyResource(action, [...entryResources, `content:${collection}:*`, `content:${collection}`, 'content:*', '*'])
  }

  /** Media actions, optionally narrowed to a category. */
  function canMedia(action, category = '') {
    const resources = ['media:*', '*']
    if (category) resources.unshift(`media:category:${category}`)
    return anyResource(action, resources)
  }

  /** Whether the user may perform a media action on at least some files. */
  function canAnyMedia(action) {
    const grants = auth.user?.capabilities?.permissions ?? []
    return canMedia(action) || grants.some((grant) => grant.effect !== 'deny'
      && (grant.actions ?? []).some((pattern) => pattern === '*' || pattern === action || (pattern.endsWith('*') && action.startsWith(pattern.slice(0, -1)))))
  }

  function canSchema(action, name = '') {
    return anyResource(action, name ? [`schema:${name}`, 'schema:*', '*'] : ['schema:*', '*'])
  }

  return { canContent, canMedia, canAnyMedia, canSchema }
}
