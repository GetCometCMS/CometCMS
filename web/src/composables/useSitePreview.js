import { ref } from 'vue'
import { api } from '../api/index.js'
import { t } from '../i18n/index.js'
import { withPreviewReturn } from '../previewNavigation.js'

/** Open synchronously to keep the user's click gesture, then load a private snapshot. */
export function useSitePreview() {
  const opening = ref(false)
  async function open(body) {
    if (opening.value) return
    const source = window.location?.href
    const tab = window.open('about:blank', '_blank')
    if (!tab) throw new Error(t('site.preview.popupBlocked'))
    tab.opener = null
    tab.document.title = t('site.preview.title')
    tab.document.body.textContent = t('site.preview.updating')
    opening.value = true
    try {
      const response = await api.site.createPreview(JSON.parse(JSON.stringify(body)))
      if (!tab.closed) tab.location.replace(withPreviewReturn(response.data.url, source))
    } catch (error) {
      tab.close()
      throw error
    } finally {
      opening.value = false
    }
  }
  return { opening, open }
}
