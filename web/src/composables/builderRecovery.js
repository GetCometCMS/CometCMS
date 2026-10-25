/** Failed saves survive a reload in this tab, isolated by user, workspace and document. */
export function createBuilderRecovery({ workspace, user, storage } = {}) {
  if (storage === undefined) {
    try { storage = globalThis.sessionStorage } catch { storage = null }
  }
  const key = (context) => 'cometcms.builder-recovery:' + JSON.stringify([
    workspace, user, ...['target', 'name', 'variant', 'pageEntry', 'field', 'template'].map((part) => context[part] || ''),
  ])

  return {
    read(context) {
      try {
        const saved = JSON.parse(storage?.getItem(key(context)) ?? 'null')
        return saved?.version === 1 && saved.draft && typeof saved.draft === 'object' ? saved.draft : null
      } catch { return null }
    },
    write(context, draft) {
      try {
        if (!storage) return false
        storage.setItem(key(context), JSON.stringify({ version: 1, draft }))
        return true
      } catch { return false }
    },
    remove(context) {
      try { storage?.removeItem(key(context)) } catch { /* Keep the editor usable without storage. */ }
    },
  }
}
