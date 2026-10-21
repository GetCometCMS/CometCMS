import { computed, onMounted, reactive } from 'vue'
import { api, getActiveWorkspace } from '../api/index.js'
const states = reactive({})
export function useLanguages() {
  const key = getActiveWorkspace() || 'default'
  states[key] ??= { locales: ['en'], default_locale: 'en', loaded: false, loading: false, active_locale: '' }
  const state = states[key]
  onMounted(async () => {
    if (!state.loaded && !state.loading) {
      state.loading = true
      try {
        Object.assign(state, (await api.languages()).data, { loaded: true })
        if (!state.locales.includes(state.active_locale)) state.active_locale = ''
      } catch { /* The calling editor still reports its own load failure. */ }
      finally { state.loading = false }
    }
  })
  return { loaded: computed(() => state.loaded), locales: computed(() => state.locales), defaultLocale: computed(() => state.default_locale), activeLocale: computed({ get: () => state.active_locale || state.default_locale, set: (value) => { state.active_locale = value } }) }
}

export function updateWorkspaceLanguages(workspace, languages) {
  if (languages && states[workspace]) Object.assign(states[workspace], languages, { loaded: true, active_locale: languages.default_locale })
}
