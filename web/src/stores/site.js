import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, getActiveWorkspace } from '../api/index.js'
import { isSiteLive, menuErrors } from '../composables/siteLinks.js'
import { DEFAULT_MENU_LIMITS } from '../composables/menuTree.js'
import { t } from '../i18n/index.js'

const clone = (value) => JSON.parse(JSON.stringify(value))

/**
 * Website configuration of the active workspace. `data` is what the server
 * last returned; `draft` holds the editable copies of settings, theme and
 * menus that the Website screen saves together.
 */
export const useSiteStore = defineStore('site', () => {
  const data = ref(null)
  const draft = ref(null)
  const loading = ref(false)
  const error = ref('')
  const saving = ref(false)
  const fieldErrors = ref({})
  let workspace = ''
  let inFlight = null

  const dirtyParts = computed(() => {
    if (!data.value || !draft.value) return []
    return ['settings', 'theme', 'menus'].filter(
      (part) => JSON.stringify(draft.value[part]) !== JSON.stringify(data.value[part]),
    )
  })
  const isDirty = computed(() => dirtyParts.value.length > 0)
  /** Whether the draft settings publish anything; the saved state is `data.settings.enabled`. */
  const live = computed(() => !!(draft.value && data.value) && isSiteLive(draft.value.settings, data.value.types))

  function resetDraft() {
    draft.value = data.value
      ? { settings: clone(data.value.settings), theme: clone(data.value.theme), menus: clone(data.value.menus) }
      : null
    fieldErrors.value = {}
  }

  async function load({ force = false } = {}) {
    const active = getActiveWorkspace()
    if (!force && data.value && workspace === active) return data.value
    if (inFlight && workspace === active) return inFlight

    workspace = active
    loading.value = true
    error.value = ''
    inFlight = api.site.get()
      .then((res) => {
        if (workspace !== getActiveWorkspace()) return data.value
        data.value = res.data
        resetDraft()
        return data.value
      })
      .catch((err) => {
        error.value = err.message
        throw err
      })
      .finally(() => {
        loading.value = false
        inFlight = null
      })

    return inFlight
  }

  /** Save every changed part of the draft; field errors are kept for display. */
  async function save() {
    if (!draft.value) return
    saving.value = true
    fieldErrors.value = {}

    try {
      if (dirtyParts.value.includes('menus')) {
        const fields = menuErrors(draft.value.menus, t, { ...DEFAULT_MENU_LIMITS, ...data.value.menu_limits })
        if (Object.keys(fields).length) {
          throw Object.assign(new Error(t('site.saveErrors')), { fields })
        }
      }
      for (const part of dirtyParts.value) {
        if (part === 'settings') {
          data.value.settings = (await api.site.saveSettings(draft.value.settings)).data
        } else if (part === 'theme') {
          data.value.theme = (await api.site.saveTheme(draft.value.theme)).data
        } else {
          data.value.menus = (await api.site.saveMenus(draft.value.menus)).data
        }
        draft.value[part] = clone(data.value[part])
      }
    } catch (err) {
      fieldErrors.value = err.fields ?? {}
      throw err
    } finally {
      saving.value = false
    }
  }

  /**
   * Save the website settings of one content type (from its editor). Other
   * pending edits on the Website screen stay untouched.
   */
  async function saveRoute(name, route) {
    await load()
    const settings = clone(data.value.settings)
    settings.routes = { ...settings.routes, [name]: { ...(settings.routes?.[name] ?? {}), ...route } }
    const saved = (await api.site.saveSettings(settings)).data
    data.value.settings = saved
    if (draft.value) draft.value.settings.routes[name] = clone(saved.routes[name])
    // New types bring new layouts and routes; refresh what the Website screen lists.
    if (!isDirty.value) await load({ force: true })
    return saved.routes[name]
  }

  /** Mark a layout or region as customized/default after the builder saved it. */
  function markCustomized(target, name, variant, customized) {
    if (!data.value) return
    if (target === 'region') {
      data.value.regions[name] = customized
    } else if (data.value.layouts?.[name]) {
      data.value.layouts[name][variant] = customized
    }
  }

  /** Restoring the design must preserve pending settings and menu edits. */
  function applyRestoredTheme(theme) {
    if (!data.value || !draft.value) return;
    data.value.theme = clone(theme);
    draft.value.theme = clone(theme);
  }

  function invalidate() {
    data.value = null
    draft.value = null
    workspace = ''
  }

  return { data, draft, loading, error, saving, fieldErrors, dirtyParts, isDirty, live, load, save, saveRoute, resetDraft, markCustomized, applyRestoredTheme, invalidate }
})
