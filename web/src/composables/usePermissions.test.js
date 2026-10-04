import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '../stores/auth.js'
import { usePermissions } from './usePermissions.js'

function signInWith(permissions) {
  useAuthStore().user = { id: 'u', capabilities: { permissions } }
  return usePermissions()
}

describe('usePermissions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('hides writes from read-only roles', () => {
    const { canContent, canAnyMedia, canSchema } = signInWith([
      { effect: 'allow', actions: ['content.read', 'media.read', 'schema.read'], resources: ['*'] },
    ])

    expect(canContent('content.read', 'posts')).toBe(true)
    expect(canContent('content.create', 'posts')).toBe(false)
    expect(canAnyMedia('media.upload')).toBe(false)
    expect(canSchema('schema.create')).toBe(false)
  })

  it('respects collection scopes and entry-level denies', () => {
    const { canContent } = signInWith([
      { effect: 'allow', actions: ['content.update'], resources: ['content:posts:*'] },
      { effect: 'deny', actions: ['content.update'], resources: ['content:posts:locked'] },
    ])

    expect(canContent('content.update', 'posts')).toBe(true)
    expect(canContent('content.update', 'pages')).toBe(false)
    expect(canContent('content.update', 'posts', { id: 'abc', slug: 'locked' })).toBe(false)
  })

  it('treats category-scoped media grants as access to some media', () => {
    const { canMedia, canAnyMedia } = signInWith([
      { effect: 'allow', actions: ['media.update'], resources: ['media:category:hero'] },
    ])

    expect(canAnyMedia('media.update')).toBe(true)
    expect(canMedia('media.update', 'hero')).toBe(true)
    expect(canMedia('media.update', 'other')).toBe(false)
    expect(canAnyMedia('media.delete')).toBe(false)
  })
})
