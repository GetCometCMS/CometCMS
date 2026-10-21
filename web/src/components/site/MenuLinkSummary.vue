<template>
  <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs">
    <span class="min-w-0 truncate text-slate-500">{{ selectedTitle ? `${selectedTitle}${link.anchor ? ' #' + anchorId(link.anchor) : ''}` : linkSummary(link, types, t) }}</span>
    <span v-if="selected" :class="statusPillClass(effectiveStatus(selected))">{{ t(`status.${effectiveStatus(selected)}`) }}</span>
    <span v-if="selectedError || unavailable" class="text-amber-700" :title="explainUnavailable ? warning : t('site.link.unavailableRoute')">{{ verbose ? (explainUnavailable ? warning : t(selectedError?.status === 403 ? 'site.link.noPermission' : 'site.link.unavailable')) : t('site.link.unavailableShort') }}</span>
    <button v-if="verbose && selectedError" type="button" class="text-amber-700 underline" @click="retrySelected">{{ t('site.link.retry') }}</button>
    <span v-else-if="selected && effectiveStatus(selected) !== 'published'" class="text-amber-700">{{ t(verbose ? 'site.link.notLive' : 'site.link.notLiveShort') }}</span>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useLinkEntries } from '../../composables/useLinkEntries.js'
import { anchorId, destinationAvailable, linkSummary } from '../../composables/siteLinks.js'
import { effectiveStatus, statusPillClass } from '../../composables/contentDisplay.js'
import { useI18n } from '../../i18n/index.js'
const props = defineProps({ link: { type: Object, required: true }, types: { type: Array, default: () => [] }, settings: { type: Object, default: null }, context: { type: String, default: '' }, verbose: { type: Boolean, default: false }, explainUnavailable: { type: Boolean, default: false } })
const emit = defineEmits(['resolved'])
const { t } = useI18n()
const target = computed(() => {
  if (props.link.type === 'entry') return props.link
  const home = props.settings?.homepage
  if (props.link.type === 'home' && home?.type && home.entry) return { collection: home.type, entry: home.entry }
  return {}
})
const { selected, selectedTitle, selectedError, retrySelected } = useLinkEntries({
  collection: () => target.value.collection,
  identifier: () => target.value.entry,
  locale: () => props.settings?.default_locale, context: () => props.context,
}, { browse: false })
const warning = computed(() => {
  if (selectedError.value) {
    if (selectedError.value.status === 403) return t('site.link.noPermission')
    if (selectedError.value.status === 404) return t('site.menus.missingDestination')
    return t('site.link.loadFailed')
  }
  const type = props.types.find((type) => type.name === props.link.collection)
  if (!type) return t('site.menus.missingCollection')
  if (type.visibility === 'private') return t('site.menus.privateCollection')
  return t('site.link.unavailableRoute')
})
const unavailable = computed(() => !destinationAvailable(props.link, props.types, props.settings, selected.value))
watch(selected, (entry) => { if (entry) emit('resolved', entry) })
</script>
