<template>
  <div class="space-y-3">
    <div role="group" class="grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1" :aria-label="t('site.link.type')">
      <button v-for="option in modes" :key="option.value" type="button"
        class="rounded-md px-2 py-1.5 text-left text-xs font-medium transition-colors"
        :class="mode === option.value ? 'bg-white text-theme-700 shadow-sm' : 'text-slate-600 hover:bg-white/60'"
        :aria-pressed="mode === option.value" @click="setMode(option.value)">
        <Icon :icon="option.icon" class="mr-1 inline h-4 w-4" />{{ option.label }}
      </button>
    </div>

    <label v-if="mode === 'page'" class="block">
      <span class="form-label">{{ t('site.link.page') }}</span>
      <select :value="pageValue" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="choosePage($event.target.value)">
        <option v-if="allow.includes('home')" value="home">{{ t('site.link.home') }}</option>
        <option v-if="link.type === 'entry' && !pages.some((type) => type.name === link.collection)" :value="link.collection" disabled>{{ link.collection }} · {{ t('site.link.unavailableShort') }}</option>
        <option v-for="page in pages" :key="page.name" :value="page.name">{{ page.label }}</option>
      </select>
    </label>

    <label v-if="mode === 'entry' || mode === 'index'" class="block">
      <span class="form-label">{{ t('site.link.collection') }}</span>
      <select :value="link.collection || ''" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="chooseCollection($event.target.value)">
        <option value="">{{ t('site.link.chooseType') }}</option>
        <option v-if="link.collection && !collections.some((type) => type.name === link.collection)" :value="link.collection" disabled>{{ link.collection }} · {{ t('site.link.unavailableShort') }}</option>
        <option v-for="type in collections" :key="type.name" :value="type.name">{{ type.label }}</option>
      </select>
    </label>

    <ContentLinkPicker v-if="mode === 'entry' && link.collection" :key="`${context}:${link.collection}`"
      :model-value="link.entry || ''" :collection="link.collection" :locale="settings?.default_locale || ''" :context="context" :only-identifier="onlyIdentifier"
      @update:model-value="update({ entry: $event })" @select="chosenEntry" @resolved="resolvedEntry = $event" />
    <MenuLinkSummary v-if="mode === 'page'" :link="link" :types="types" :settings="settings" :context="context" verbose @resolved="resolvedPage" />
    <p v-if="unavailableRoute" class="text-xs text-amber-700" role="status">{{ t('site.link.unavailableRoute') }}</p>

    <label v-if="mode === 'url'" class="block">
      <span class="form-label">{{ t('site.link.url') }}</span>
      <input :value="link.url || ''" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm"
        :placeholder="t('site.link.urlPlaceholder')" @input="update({ url: $event.target.value })" />
      <span class="mt-1 block text-xs text-slate-500">{{ t('site.link.urlHint') }}</span>
    </label>

    <label v-if="['anchor', 'home', 'entry', 'index'].includes(link.type)" class="block">
      <span class="form-label">{{ t(link.type === 'anchor' ? 'site.link.anchor' : 'site.link.optionalAnchor') }}</span>
      <div class="flex items-center rounded-lg border border-slate-300 bg-white focus-within:ring-1 focus-within:ring-theme-500">
        <span class="pl-3 text-sm text-slate-400" aria-hidden="true">#</span>
        <input :value="link.anchor || ''" type="text" class="form-input min-w-0 flex-1 rounded-lg border-0 text-sm focus:ring-0"
          :placeholder="t('site.link.anchorPlaceholder')" @input="update({ anchor: $event.target.value })" />
      </div>
      <span class="mt-1 block text-xs text-slate-500">{{ t(link.type === 'anchor' ? 'site.link.anchorHint' : 'site.link.optionalAnchorHint') }}</span>
    </label>
    <p v-if="issue" class="text-xs text-red-700" role="status">{{ t(`site.link.${issue}`) }}</p>
    <label v-if="!['none', 'anchor'].includes(link.type)" class="flex items-center gap-2 text-xs text-slate-600">
      <input type="checkbox" class="rounded border-slate-300 text-theme-600" :checked="link.new_tab" @change="update({ new_tab: $event.target.checked })" />
      {{ t('site.link.newTab') }}
    </label>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import ContentLinkPicker from './ContentLinkPicker.vue'
import MenuLinkSummary from './MenuLinkSummary.vue'
import { destinationAvailable, eligibleTypes, entryTitle, linkIssue } from '../../composables/siteLinks.js'
import { useI18n } from '../../i18n/index.js'

const props = defineProps({
  modelValue: { type: Object, default: () => ({ type: 'none' }) },
  types: { type: Array, default: () => [] }, settings: { type: Object, default: null },
  context: { type: String, default: '' }, groupHeading: { type: Boolean, default: false },
  allow: { type: Array, default: () => ['none', 'url', 'current', 'home', 'index', 'entry', 'anchor'] },
})
const emit = defineEmits(['update:modelValue', 'destination-label'])
const { t } = useI18n()
const link = computed(() => props.modelValue ?? { type: 'none' })
const resolvedEntry = ref(null)
const pendingPage = ref('')
watch(() => props.context, () => { pendingPage.value = '' })
watch(() => [link.value.collection, link.value.entry], () => { resolvedEntry.value = null })
const onlyIdentifier = computed(() => !props.settings?.routes?.[link.value.collection]?.enabled && props.settings?.homepage?.type === link.value.collection ? props.settings.homepage.entry || '' : '')
const pages = computed(() => props.allow.includes('entry') ? eligibleTypes(props.types, props.settings, 'page') : [])
const mode = computed(() => link.value.type === 'home' || (link.value.type === 'entry' && props.types.find((type) => type.name === link.value.collection)?.singleton) ? 'page' : link.value.type)
const pageValue = computed(() => link.value.type === 'home' ? 'home' : link.value.collection)
const collections = computed(() => eligibleTypes(props.types, props.settings, mode.value))
const modes = computed(() => [
  { value: 'page', label: t('site.link.pages'), icon: 'mdi:file-document-outline', enabled: props.allow.includes('home') || pages.value.length > 0 },
  { value: 'entry', label: t('site.link.content'), icon: 'mdi:database-outline', enabled: props.allow.includes('entry') },
  { value: 'index', label: t('site.link.listing'), icon: 'mdi:format-list-bulleted', enabled: props.allow.includes('index') },
  { value: 'url', label: t('site.link.custom'), icon: 'mdi:link', enabled: props.allow.includes('url') },
  { value: 'anchor', label: t('site.link.section'), icon: 'mdi:pound', enabled: props.allow.includes('anchor') },
  { value: 'current', label: t('site.link.current'), icon: 'mdi:file-link-outline', enabled: props.allow.includes('current') },
  { value: 'none', label: t(props.groupHeading ? 'site.link.group' : 'site.link.none'), icon: 'mdi:folder-outline', enabled: props.allow.includes('none') },
].filter((option) => option.enabled))
const unavailableRoute = computed(() => !destinationAvailable(link.value, props.types, props.settings, resolvedEntry.value))
const issue = computed(() => ['none', 'current'].includes(link.value.type) ? '' : linkIssue(link.value))
function update(patch) { emit('update:modelValue', { ...link.value, ...patch }) }
function setMode(value) {
  if (value === mode.value) return
  if (value === 'page') {
    choosePage(props.allow.includes('home') ? 'home' : pages.value[0]?.name)
  } else {
    emit('update:modelValue', { type: value, new_tab: value === 'anchor' ? false : !!link.value.new_tab })
  }
}
function choosePage(value) {
  pendingPage.value = value === 'home' ? '' : value
  emit('update:modelValue', value === 'home' ? { type: 'home', new_tab: !!link.value.new_tab } : { type: 'entry', collection: value, entry: value, new_tab: !!link.value.new_tab })
  if (value === 'home') emit('destination-label', t('site.link.home'))
}
function chooseCollection(value) {
  update({ collection: value, entry: '', anchor: '' })
  if (mode.value === 'index' && value) emit('destination-label', props.settings?.routes?.[value]?.index_title || props.types.find((type) => type.name === value)?.label || value)
}
function resolvedPage(entry) {
  if (pendingPage.value && pendingPage.value === link.value.collection && link.value.type === 'entry') {
    emit('destination-label', entryTitle(entry, props.settings?.default_locale))
    pendingPage.value = ''
  }
}
function chosenEntry(entry) { resolvedEntry.value = entry; emit('destination-label', entryTitle(entry, props.settings?.default_locale)) }
</script>
