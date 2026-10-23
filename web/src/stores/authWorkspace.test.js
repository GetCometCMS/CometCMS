import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('../theme.js', () => ({ applyTheme: vi.fn(), DEFAULT_THEME: 'blue' }))
vi.mock('../i18n/index.js', () => ({ setLocale: vi.fn(), DEFAULT_ADMIN_LOCALE: 'en', t: (key) => key }))

const user = {
  id: 'admin',
  default_workspace: 'preferred-workspace',
  capabilities: { permissions: [{ effect: 'allow', actions: ['*'], resources: ['*'] }] },
}

describe('workspace selection at sign-in', () => {
  let auth, api, getActiveWorkspace, setActiveWorkspace, storage

  beforeEach(async () => {
    vi.resetModules()
    storage = new Map([['cometcms.workspace', 'first-workspace']])
    vi.stubGlobal('localStorage', {
      getItem: (key) => storage.get(key) ?? null,
      setItem: (key, value) => storage.set(key, value),
    })
    setActivePinia(createPinia())
    ;({ api, getActiveWorkspace, setActiveWorkspace } = await import('../api/index.js'))
    const { useAuthStore } = await import('./auth.js')
    auth = useAuthStore()
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('uses the server default instead of the remembered workspace after login', async () => {
    vi.spyOn(api, 'login').mockResolvedValue({ data: user })

    await auth.login('admin', 'password', true)

    expect(api.login).toHaveBeenCalledWith('admin', 'password', true)
    expect(getActiveWorkspace()).toBe('preferred-workspace')
    expect(storage.get('cometcms.workspace')).toBe('preferred-workspace')
  })

  it('preserves the selected workspace when refreshing the user after a switch', async () => {
    vi.spyOn(api, 'login').mockResolvedValue({ data: user })
    vi.spyOn(api, 'me').mockResolvedValue({ data: user })
    await auth.login('admin', 'password')
    setActiveWorkspace('another-workspace')

    await auth.refresh()

    expect(getActiveWorkspace()).toBe('another-workspace')
  })

  it('preserves the selected workspace when restoring an existing session', async () => {
    vi.spyOn(api, 'me').mockResolvedValue({ data: user })

    await auth.init()

    expect(getActiveWorkspace()).toBe('first-workspace')
  })

  it('leaves the workspace unchanged when login fails', async () => {
    vi.spyOn(api, 'login').mockRejectedValue(new Error('Invalid credentials'))

    await expect(auth.login('admin', 'wrong')).rejects.toThrow('Invalid credentials')

    expect(getActiveWorkspace()).toBe('first-workspace')
  })
})
