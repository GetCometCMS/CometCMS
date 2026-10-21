import { computed } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useContentTypesStore } from '../stores/contentTypes.js'
import { useSiteStore } from '../stores/site.js'
import { useI18n } from '../i18n/index.js'

const DEFAULT_COLLECTION_ICON = 'mdi:file-document-outline'
const DEFAULT_PAGE_ICON = 'mdi:file-document-edit-outline'

export function contentTypeIcon(type) {
  return type?.icon || (type?.singleton ? DEFAULT_PAGE_ICON : DEFAULT_COLLECTION_ICON)
}

/**
 * True when `path` is `prefix` itself or a page below it, so editing an entry
 * keeps its content type highlighted. `/content/pages` does not match
 * `/content/pages-archive`.
 */
export function pathMatches(path, prefix) {
  return path === prefix || path.startsWith(prefix.endsWith('/') ? prefix : `${prefix}/`)
}

/**
 * A section with sub-pages. Its `match` covers all children, and a section left
 * with a single child (missing permissions) becomes that child's plain link.
 */
function section(item, children) {
  const visible = children.filter(Boolean)
  if (visible.length === 0) return null
  if (visible.length === 1) return { ...visible[0], icon: item.icon }
  return { ...item, to: visible[0].to, match: visible.flatMap((child) => child.match), children: visible }
}

/**
 * The admin's information architecture in one place: the sidebar and the
 * command palette both render these groups. Groups run from daily editing
 * (content) to rare setup work (administration).
 *
 * Each item: { key, label, icon, to, match: [path prefixes], children? }.
 * Children are { key, label, to, match } and mirror the tabs of their page.
 */
export function useNavigation() {
  const auth = useAuthStore()
  const typesStore = useContentTypesStore()
  const site = useSiteStore()
  const { t } = useI18n()

  const allTypes = computed(() =>
    typesStore.list.length > 0 ? typesStore.list : (auth.user?.accessible_content_types ?? []),
  )

  const contentTypes = computed(() =>
    allTypes.value.filter((type) => auth.can('content.read', `content:${type.name}:*`)),
  )

  const showPageBuilder = computed(() => auth.can('site.manage') && site.pageBuilderActive)

  const groups = computed(() => {
    const result = [
      {
        key: 'main',
        items: [
          { key: 'dashboard', label: t('app.nav.dashboard'), icon: 'mdi:view-dashboard-outline', to: '/dashboard', match: ['/dashboard'] },
        ],
      },
    ]

    result.push({
      key: 'content',
      label: t('app.nav.content'),
      items: [
        ...contentTypes.value.map((type) => ({
          key: `content:${type.name}`,
          label: type.label,
          icon: contentTypeIcon(type),
          to: `/content/${type.name}`,
          match: [`/content/${type.name}`, `/trash/${type.name}`],
          type,
        })),
        { key: 'media', label: t('app.nav.media'), icon: 'mdi:image-multiple-outline', to: '/media', match: ['/media'] },
      ],
    })

    const website = []
    if (showPageBuilder.value) {
      website.push({ key: 'page-builder', label: t('app.nav.website'), icon: 'mdi:application-edit-outline', to: '/page-builder', match: ['/page-builder'] })
    }
    if (auth.can('site.manage')) {
      website.push(section({ key: 'website-settings', label: t('app.nav.websiteSettings'), icon: 'mdi:web' }, [
        { key: 'website-general', label: t('website.tab.general'), to: '/website/general', match: ['/website/general'] },
        { key: 'website-seo', label: t('website.tab.seo'), to: '/website/seo', match: ['/website/seo'] },
      ]))
    }
    if (website.length > 0) {
      result.push({ key: 'website', label: t('app.nav.websiteGroup'), items: website })
    }

    const developer = []
    if (auth.can('schema.read')) {
      developer.push({ key: 'content-types', label: t('app.nav.contentTypes'), icon: 'mdi:shape-outline', to: '/content-types', match: ['/content-types'] })
    }
    developer.push(section({ key: 'connect', label: t('app.nav.connect'), icon: 'mdi:connection' }, [
      { key: 'connect-tokens', label: t('connect.accessTokens'), to: '/connect/access-tokens', match: ['/connect/access-tokens'] },
      { key: 'connect-api', label: t('connect.api'), to: '/connect/api', match: ['/connect/api'] },
      { key: 'connect-mcp', label: t('connect.mcp'), to: '/connect/mcp', match: ['/connect/mcp'] },
      { key: 'connect-webhooks', label: t('connect.webhooks'), to: '/connect/webhooks', match: ['/connect/webhooks'] },
    ]))
    result.push({ key: 'developer', label: t('app.nav.developer'), items: developer })

    const admin = [
      section({ key: 'users', label: t('app.nav.usersRoles'), icon: 'mdi:account-group-outline' }, [
        auth.can('users.read') && { key: 'users-list', label: t('app.nav.users'), to: '/users', match: ['/users'] },
        auth.can('roles.read') && { key: 'roles', label: t('app.nav.roles'), to: '/roles', match: ['/roles'] },
      ]),
    ]
    if (auth.can('workspaces.manage')) {
      admin.push({ key: 'workspaces', label: t('workspaces.title'), icon: 'mdi:folder-multiple-outline', to: '/website/workspaces', match: ['/website/workspaces'] })
    }
    if (auth.can('backups.read')) {
      admin.push({ key: 'backups', label: t('app.nav.backupRestore'), icon: 'mdi:backup-restore', to: '/backups', match: ['/backups'] })
    }
    if (auth.can('updates.check')) {
      admin.push({ key: 'updates', label: t('app.nav.updates'), icon: 'mdi:update', to: '/update', match: ['/update'] })
    }
    result.push({ key: 'admin', label: t('app.nav.administration'), items: admin.filter(Boolean) })

    return result.filter((group) => group.items.length > 0)
  })

  function isActive(item, path) {
    return item.match.some((prefix) => pathMatches(path, prefix))
  }

  return { groups, contentTypes, allTypes, isActive }
}
