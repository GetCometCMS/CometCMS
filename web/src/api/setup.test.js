import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('../i18n/index.js', () => ({ t: (key) => key }))

afterEach(() => {
  vi.unstubAllGlobals()
  vi.resetModules()
})

describe('setup starting point', () => {
  it.each([undefined, 'empty', 'website'])('sends the selected template %j to setup', async (choice) => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      headers: new Headers(),
      json: async () => ({ data: { id: 'admin' } }),
    })
    vi.stubGlobal('fetch', fetch)
    const { api } = await import('./index.js')

    await api.setup('admin', 'secret-password', 'My Website', 'my-website', choice)

    expect(fetch).toHaveBeenCalledWith(expect.stringMatching(/\/setup$/), expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({
        username: 'admin',
        password: 'secret-password',
        workspace: 'My Website',
        workspace_slug: 'my-website',
        starter_template: choice ?? 'website',
      }),
    }))
  })
})
