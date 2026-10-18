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
 * The admin's information architecture in one place: the sidebar and the
 * command palette both render these groups.
 *
 * Each item: { key, label, icon, to, match: [path prefixes] }.
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

    if (showPageBuilder.value) {
      result.push({
        key: 'website',
        label: t('app.nav.websiteGroup'),
        items: [
          { key: 'page-builder', label: t('app.nav.website'), icon: 'mdi:web', to: '/page-builder', match: ['/page-builder'] },
        ],
      })
    }

    const admin = []
    if (auth.can('schema.read')) {
      admin.push({ key: 'content-types', label: t('app.nav.contentTypes'), icon: 'mdi:shape-outline', to: '/content-types', match: ['/content-types'] })
    }
    if (auth.can('users.read')) {
      admin.push({ key: 'users', label: t('app.nav.usersRoles'), icon: 'mdi:account-group-outline', to: '/users', match: ['/users', '/roles'] })
    } else if (auth.can('roles.read')) {
      admin.push({ key: 'roles', label: t('app.nav.roles'), icon: 'mdi:shield-account-outline', to: '/roles', match: ['/roles'] })
    }
    if (auth.can('workspaces.manage')) {
      admin.push({ key: 'workspaces', label: t('app.nav.workspaces'), icon: 'mdi:folder-multiple-outline', to: '/workspaces', match: ['/workspaces'] })
    }
    admin.push({ key: 'connect', label: t('app.nav.connect'), icon: 'mdi:api', to: '/connect/access-tokens', match: ['/connect'] })
    if (auth.can('backups.read')) {
      admin.push({ key: 'backups', label: t('app.nav.backupRestore'), icon: 'mdi:backup-restore', to: '/backups', match: ['/backups'] })
    }
    if (auth.can('updates.check')) {
      admin.push({ key: 'updates', label: t('app.nav.updates'), icon: 'mdi:update', to: '/update', match: ['/update'] })
    }
    result.push({ key: 'admin', label: t('app.nav.administration'), items: admin })

    return result
  })

  function isActive(item, path) {
    return item.match.some((prefix) => pathMatches(path, prefix))
  }

  return { groups, contentTypes, allTypes, isActive }
}
