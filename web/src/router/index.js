import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { setActiveWorkspace } from '../api/index.js'
import { ADMIN_BASE } from '../basePath.js'

const routes = [
  { path: '/',              redirect: '/dashboard' },
  { path: '/login',         component: () => import('../views/LoginView.vue') },
  { path: '/setup',         component: () => import('../views/SetupView.vue') },
  {
    path: '/dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/content-types',
    component: () => import('../views/ContentTypesView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/content-types/new',
    component: () => import('../views/ContentTypeEditView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/content-types/:name/edit',
    component: () => import('../views/ContentTypeEditView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/content/:collection',
    component: () => import('../views/ContentListView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/content/:collection/new',
    component: () => import('../views/ContentEditView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/content/:collection/:id',
    component: () => import('../views/ContentEditView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/trash/:collection',
    component: () => import('../views/TrashView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/media',
    component: () => import('../views/MediaView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/users',
    component: () => import('../views/UsersView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/api-tokens',
    redirect: '/connect/access-tokens',
  },
  {
    path: '/roles',
    component: () => import('../views/RolesView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/backups',
    component: () => import('../views/BackupRestoreView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/webhooks',
    redirect: '/connect/webhooks',
  },
  {
    path: '/connect/:tab(access-tokens|api|mcp|webhooks)?',
    component: () => import('../views/ConnectView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/update',
    component: () => import('../views/UpdateView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/profile',
    component: () => import('../views/ProfileView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/api-explorer',
    redirect: '/connect/api',
  },
  {
    path: '/page-builder/forms',
    redirect: '/content-types',
  },
  // Site settings and SEO moved to the Website page.
  {
    path: '/page-builder/overview',
    redirect: (to) => ({ path: '/website/general', query: to.query, hash: to.hash }),
  },
  {
    path: '/page-builder/seo',
    redirect: '/website/seo',
  },
  {
    path: '/page-builder/:tab(design|templates|menus|fonts|components)?',
    component: () => import('../views/PageBuilderView.vue'),
    meta: { requiresAuth: true },
  },
  // Cards are designed in the collection element now; the list page shows them.
  {
    path: '/page-builder/builder/layout/:name/card',
    redirect: (to) => ({ path: `/page-builder/builder/layout/${to.params.name}/index`, query: to.query }),
  },
  {
    // Full-screen editor for a content type layout, the header/footer, a component,
    // or one entry's page content (/page/:type/:entry, ?field= picks the page field).
    path: '/page-builder/builder/:target(layout|region|component|page)/:name/:variant?',
    component: () => import('../views/PageBuilderEditorView.vue'),
    // One editor for all builder pages: it loads the next page in place instead of remounting.
    meta: { requiresAuth: true, bare: true, viewKey: 'page-builder-editor' },
  },
  {
    path: '/website/:tab(general|seo|forms|workspaces)?',
    component: () => import('../views/WebsiteView.vue'),
    meta: { requiresAuth: true },
  },
  // Old /website URLs (bookmarks, links in notes).
  {
    path: '/website/pages',
    redirect: '/content-types',
  },
  {
    // The design settings moved into the builder.
    path: '/website/theme',
    redirect: '/page-builder/builder/region/header?settings=design',
  },
  {
    path: '/website/header-footer',
    redirect: '/page-builder/builder/region/header',
  },
  {
    path: '/website/:rest(.*)*',
    redirect: (to) => ({ path: `/page-builder/${[].concat(to.params.rest ?? []).join('/')}`.replace(/\/$/, ''), query: to.query }),
  },
  {
    path: '/workspaces',
    redirect: '/website/workspaces',
  },
  {
    // Unknown admin URLs (typos, removed pages, stale bookmarks).
    path: '/:pathMatch(.*)*',
    component: () => import('../views/NotFoundView.vue'),
    meta: { requiresAuth: true },
  },
]

const router = createRouter({
  history: createWebHistory(ADMIN_BASE),
  routes,
})

let authInitialized = false

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!authInitialized) {
    await auth.init()
    authInitialized = true
  }

  if (auth.isAuthenticated && typeof to.query.workspace === 'string' && auth.can('workspaces.read')) {
    setActiveWorkspace(to.query.workspace)
  }

  if (auth.notSetUp && to.path !== '/setup') {
    return '/setup'
  }

  if (!auth.notSetUp && !auth.isAuthenticated && to.meta.requiresAuth) {
    // Remember deep links (bookmarks, shared entry URLs) for after sign-in.
    return to.fullPath === '/dashboard' ? '/login' : { path: '/login', query: { redirect: to.fullPath } }
  }

  if (auth.isAuthenticated && (to.path === '/login' || to.path === '/setup')) {
    return '/dashboard'
  }
})

export default router
