import { entryForLocale, resolveLocalized } from './localizedValues.js'
import { DEFAULT_MENU_LIMITS } from './menuTree.js'

/**
 * Mirrors SiteRepository::isLive(): the website is live while a public homepage
 * or at least one public content type with frontend pages exists.
 */
export function isSiteLive(settings, types) {
  const isPublic = (name) => types.some((type) => type.name === name && type.visibility !== 'private')
  if (settings?.homepage?.type && isPublic(settings.homepage.type)) return true
  return Object.entries(settings?.routes ?? {}).some(([name, route]) => route?.enabled && isPublic(name))
}

export function eligibleTypes(types, settings, kind) {
  return types.filter((type) => {
    if (type.visibility === 'private') return false
    const route = settings?.routes?.[type.name]
    const home = settings?.homepage
    const homepage = home?.type === type.name
    // Overview destinations can be configured before their website route exists.
    // destinationAvailable reports missing routes without hiding the content type.
    if (kind === 'index') return true
    return !!(route?.enabled || (homepage && home.entry))
  })
}

export function entryTitle(entry, locale) {
  return entryForLocale(entry, locale, entry?.locale || 'en').title || entry?.title || entry?.slug || entry?.id || ''
}

export function entryIdentifier(entry) {
  return entry.uid || entry.slug || entry.id
}

export function destinationAvailable(link, types, settings, entry = null) {
  if (!['entry', 'index'].includes(link.type) || !link.collection) return true
  const type = types.find((type) => type.name === link.collection)
  if (!type || type.visibility === 'private') return false
  const route = settings?.routes?.[type.name]
  const home = settings?.homepage
  if (link.type === 'index') return !!((route?.enabled && route.index_path) || (home?.type === type.name && !home.entry))
  if (route?.enabled) return true
  if (home?.type !== type.name) return false
  return !!home.entry && [link.entry, entry?.uid, entry?.slug, entry?.id].filter(Boolean).includes(home.entry)
}

export function anchorId(value) {
  return String(value ?? '').trim().replace(/^#+/, '')
}

export function validAnchor(value) {
  return /^[A-Za-z0-9_-]{1,80}$/.test(anchorId(value))
}

export function safeLinkUrl(value) {
  const url = String(value ?? '').trim()
  // Match the website sanitizer's supported schemes; relative URLs are allowed.
  return !!url && (!/^[a-z][a-z0-9+.-]*:/i.test(url) || /^(https?|mailto|tel):/i.test(url))
}

export function linkIssue(link = {}, label = '', defaultLocale = 'en') {
  link = resolveLocalized(link, defaultLocale, defaultLocale) ?? {}
  label = String(resolveLocalized(label, defaultLocale, defaultLocale) ?? '')
  const type = link.type ?? 'none'
  if (type === 'none') return label.trim() ? '' : 'groupLabel'
  if (type === 'url') return safeLinkUrl(link.url) ? '' : 'invalidUrl'
  if (!['home', 'entry', 'index', 'anchor'].includes(type)) return 'chooseDestination'
  if (['entry', 'index'].includes(type) && !link.collection) return 'chooseCollection'
  if (type === 'entry' && !link.entry) return 'chooseEntry'
  if ((type === 'anchor' || anchorId(link.anchor)) && !validAnchor(link.anchor)) return 'invalidAnchor'
  return ''
}

/** Local validation mirrors structural server limits and reports item paths. */
export function menuErrors(menus, t, limits = DEFAULT_MENU_LIMITS, defaultLocale = 'en') {
  const errors = {}
  if (menus.length > limits.max_menus) errors.menus = t('site.menus.limitMenus', { count: limits.max_menus })
  const visit = (items, depth, path) => {
    if (items.length && depth > limits.max_depth) errors[path] = t('site.menus.limitDepth', { count: limits.max_depth })
    if (items.length > limits.max_siblings) errors[path] = t('site.menus.limitSiblings', { count: limits.max_siblings })
    for (const [index, item] of items.entries()) {
      const key = `${path}.${index}`
      const issue = linkIssue(item.link, item.label, defaultLocale)
      if (issue) errors[key] = t(`site.link.${issue}`)
      visit(item.children ?? [], depth + 1, `${key}.children`)
    }
  }
  menus.forEach((menu, index) => visit(menu.items ?? [], 1, `menus.${index}.items`))
  return errors
}

export function linkSummary(link, types, t) {
  const collection = types.find((type) => type.name === link?.collection)
  const anchor = anchorId(link?.anchor)
  const suffix = anchor && link?.type !== 'anchor' ? ` #${anchor}` : ''
  switch (link?.type) {
    case 'none': return t('site.link.group')
    case 'home': return t('site.link.home') + suffix
    case 'entry': return `${collection?.label ?? link.collection} / ${link.entry || '…'}${suffix}`
    case 'index': return `${collection?.label ?? link.collection} · ${t('site.link.index')}${suffix}`
    case 'anchor': return `#${anchor || '…'}`
    case 'url': return link.url || t('site.link.url')
    default: return t('site.link.chooseDestination')
  }
}
