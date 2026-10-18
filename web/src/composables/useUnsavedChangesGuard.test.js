import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createRenderer, h, nextTick, ref } from 'vue'
import { createMemoryHistory, createRouter, isNavigationFailure, RouterView } from 'vue-router'
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard.js'

// Mount the keyed router view without a browser so the real update/leave guards run.
const node = () => ({ children: [], parent: null })
const renderer = createRenderer({
  createElement: node, createText: node, createComment: node,
  setText: () => {}, setElementText: () => {}, patchProp: () => {},
  insert(child, parent, anchor = null) {
    child.parent = parent
    const index = anchor ? parent.children.indexOf(anchor) : -1
    parent.children.splice(index < 0 ? parent.children.length : index, 0, child)
  },
  remove(child) {
    child.parent?.children.splice(child.parent.children.indexOf(child), 1)
    child.parent = null
  },
  parentNode: (child) => child.parent,
  nextSibling: (child) => child.parent?.children[child.parent.children.indexOf(child) + 1] ?? null,
})
let app

async function fixture(isDirty = ref(true), options = { guardUpdates: true }) {
  let guard
  const editor = { setup() { guard = useUnsavedChangesGuard(isDirty, options); return () => null } }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/builder/:name', component: editor },
      { path: '/dashboard', component: { render: () => null } },
    ],
  })
  await router.push('/builder/first')
  app = renderer.createApp({ render: () => h(RouterView, { key: router.currentRoute.value.path }) })
  app.use(router)
  app.mount(node())
  return { router, guard, getGuard: () => guard }
}

beforeEach(() => vi.stubGlobal('window', new EventTarget()))
afterEach(() => { app?.unmount(); vi.unstubAllGlobals() })

describe('unsaved changes navigation', () => {
  it('keeps the current document when a same-route page switch is cancelled', async () => {
    const { router, guard } = await fixture()
    const navigation = router.push('/builder/second')
    await vi.waitFor(() => expect(guard.leavePromptOpen.value).toBe(true))
    guard.leavePromptOpen.value = false
    expect(isNavigationFailure(await navigation)).toBe(true)
    expect(router.currentRoute.value.path).toBe('/builder/first')
  })

  it('switches documents only after the user confirms leaving', async () => {
    const { router, guard } = await fixture()
    const navigation = router.push('/builder/second')
    await vi.waitFor(() => expect(guard.leavePromptOpen.value).toBe(true))
    guard.confirmLeave()
    await navigation
    await nextTick()
    expect(router.currentRoute.value.path).toBe('/builder/second')
  })

  it('preserves shared edits between builder pages and guards navigation outside the builder', async () => {
    const { router, guard, getGuard } = await fixture((to) => !to?.path.startsWith('/builder/'))
    await router.push('/builder/second')
    await nextTick()
    expect(guard.leavePromptOpen.value).toBe(false)
    // The keyed view has remounted; its new guard must still protect shared edits.
    const navigation = router.push('/dashboard')
    await vi.waitFor(() => expect(getGuard().leavePromptOpen.value).toBe(true))
    expect(router.currentRoute.value.path).toBe('/builder/second')
    getGuard().confirmLeave()
    await navigation
    expect(router.currentRoute.value.path).toBe('/dashboard')
  })

  it('does not prompt for query-only changes', async () => {
    const { router, guard } = await fixture()
    await router.push('/builder/first?settings=design')
    expect(guard.leavePromptOpen.value).toBe(false)
    expect(router.currentRoute.value.query.settings).toBe('design')
  })

  it('leaves same-route tab navigation unguarded unless update protection is enabled', async () => {
    const { router, guard } = await fixture(ref(true), {})
    await router.push('/builder/second')
    expect(guard.leavePromptOpen.value).toBe(false)
    expect(router.currentRoute.value.path).toBe('/builder/second')
  })
})
