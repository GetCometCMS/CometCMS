<template>
  <div :class="compact ? 'space-y-2' : 'space-y-3'">
    <div v-if="compact" class="flex gap-1.5">
      <LinkModeMenu :model-value="mode" :options="modes" :label="t('site.link.type')" @update:model-value="setMode" />
      <div v-if="targets.length" role="group" :aria-label="t('site.link.target')" class="flex shrink-0 divide-x divide-slate-300 overflow-hidden rounded-lg border border-slate-300 bg-white">
        <button v-for="target in targets" :key="target.key" type="button"
          class="flex w-8 items-center justify-center transition-colors"
          :class="link[target.key] ? 'bg-theme-50 text-theme-700' : 'text-slate-400 hover:text-slate-700'"
          :aria-pressed="!!link[target.key]" :title="target.label" :aria-label="target.label" @click="toggleTarget(target.key)">
          <Icon :icon="target.icon" class="h-4 w-4" />
        </button>
      </div>
    </div>
    <div v-else role="group" class="grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1" :aria-label="t('site.link.type')">
      <button v-for="option in modes" :key="option.value" type="button"
        class="rounded-md px-2 py-1.5 text-left text-xs font-medium transition-colors"
        :class="mode === option.value ? 'bg-white text-theme-700 shadow-sm' : 'text-slate-600 hover:bg-white/60'"
        :aria-pressed="mode === option.value" @click="setMode(option.value)">
        <Icon :icon="option.icon" class="mr-1 inline h-4 w-4" />{{ option.label }}
      </button>
    </div>

    <label v-if="mode === 'entry' || mode === 'index'" class="block">
      <span :class="labelClass">{{ t('site.link.collection') }}</span>
      <select :value="link.collection || ''" :class="fieldClass" class="form-select w-full rounded-lg border-slate-300" @change="chooseCollection($event.target.value)">
        <option value="">{{ t('site.link.chooseType') }}</option>
        <option v-if="link.collection && !collections.some((type) => type.name === link.collection)" :value="link.collection" disabled>{{ link.collection }} · {{ t('site.link.unavailableShort') }}</option>
        <option v-for="type in collections" :key="type.name" :value="type.name">{{ type.label }}</option>
      </select>
    </label>

    <ContentLinkPicker v-if="mode === 'entry' && link.collection" :key="`${context}:${link.collection}`"
      :model-value="link.entry || ''" :collection="link.collection" :locale="settings?.default_locale || ''" :context="context" :only-identifier="onlyIdentifier"
      @update:model-value="update({ entry: $event })" @select="chosenEntry" @resolved="resolvedEntry = $event" />
    <MenuLinkSummary v-if="mode === 'home'" :explain-unavailable="taskFocused" :link="link" :types="types" :settings="settings" :context="context" verbose />
    <p v-if="unavailableRoute" class="text-xs text-amber-700" role="status">{{ t('site.link.unavailableRoute') }}</p>

    <label v-if="mode === 'url'" class="block">
      <span :class="labelClass">{{ t('site.link.url') }}</span>
      <input :value="link.url || ''" type="text" :class="fieldClass" class="form-input w-full rounded-lg border-slate-300" :title="compact ? t('site.link.urlHint') : undefined"
        :placeholder="t('site.link.urlPlaceholder')" @input="update({ url: $event.target.value })" />
      <span v-if="!compact" class="mt-1 block text-xs text-slate-500">{{ t('site.link.urlHint') }}</span>
    </label>

    <LinkOptions :link="link" :compact="compact" :task-focused="taskFocused" @update="update" />
    <p v-if="issue" class="text-xs text-red-700" role="status">{{ t(`site.link.${issue}`) }}</p>

  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import ContentLinkPicker from './ContentLinkPicker.vue'
import LinkModeMenu from './LinkModeMenu.vue'
import LinkOptions from './LinkOptions.vue'
import MenuLinkSummary from './MenuLinkSummary.vue'
import { MODAL_LINK_TYPES, destinationAvailable, eligibleTypes, entryTitle, linkIssue } from '../../composables/siteLinks.js'
import { resolveLocalized } from "../../composables/localizedValues.js";
import { useI18n } from '../../i18n/index.js'

const props = defineProps({
  modelValue: { type: Object, default: () => ({ type: 'none' }) },
  types: { type: Array, default: () => [] }, settings: { type: Object, default: null },
  context: { type: String, default: '' }, groupHeading: { type: Boolean, default: false },
  allow: { type: Array, default: () => ['none', 'url', 'current', 'home', 'index', 'entry', 'anchor'] },
  /** One-row type menu and smaller fields, for narrow sidebars. */
  compact: { type: Boolean, default: false },
  taskFocused: { type: Boolean, default: false },
  /** Offer opening website pages in the modal (compact layout). */
  allowModal: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'destination-label'])
const { t } = useI18n()
const link = computed(() => props.modelValue ?? { type: 'none' })
const labelClass = computed(() => props.compact ? 'mb-1 block text-xs font-medium text-slate-600' : 'form-label')
const fieldClass = computed(() => props.compact ? 'py-1.5 text-xs' : 'text-sm')
const resolvedEntry = ref(null)
watch(() => [link.value.collection, link.value.entry], () => { resolvedEntry.value = null })
const onlyIdentifier = computed(() => !props.settings?.routes?.[link.value.collection]?.enabled && props.settings?.homepage?.type === link.value.collection ? props.settings.homepage.entry || '' : '')
const mode = computed(() => link.value.type)
const collections = computed(() => eligibleTypes(props.types, props.settings, mode.value))
const modes = computed(() => [
  { value: 'home', label: t('site.link.home'), icon: 'mdi:home-outline', enabled: props.allow.includes('home') },
  { value: 'entry', label: t(props.taskFocused ? 'site.menus.page' : 'site.link.content'), icon: 'mdi:database-outline', enabled: props.allow.includes('entry') },
  { value: 'index', label: t(props.taskFocused ? 'site.menus.overview' : 'site.link.listing'), icon: 'mdi:format-list-bulleted', enabled: props.allow.includes('index') && (mode.value === 'index' || eligibleTypes(props.types, props.settings, 'index').length > 0) },
  { value: 'url', label: t('site.link.custom'), icon: 'mdi:link', enabled: props.allow.includes('url') },
  { value: 'anchor', label: t('site.link.section'), icon: 'mdi:pound', enabled: props.allow.includes('anchor') },
  { value: 'current', label: t('site.link.current'), icon: 'mdi:file-link-outline', enabled: props.allow.includes('current') },
  { value: 'none', label: t(props.groupHeading ? 'site.link.group' : 'site.link.none'), icon: 'mdi:folder-outline', enabled: props.allow.includes('none') },
].filter((option) => option.enabled))
const unavailableRoute = computed(() => !destinationAvailable(link.value, props.types, props.settings, resolvedEntry.value))
const issue = computed(() => ['none', 'current'].includes(link.value.type) ? '' : linkIssue(link.value))
/** Where the link opens besides the current tab; the choices exclude each other. */
const targets = computed(() => [
  !['none', 'anchor'].includes(link.value.type) && { key: 'new_tab', icon: 'mdi:open-in-new', label: t('site.link.newTab') },
  props.allowModal && MODAL_LINK_TYPES.includes(link.value.type) && { key: 'open_modal', icon: 'mdi:dock-window', label: t('site.link.openModal') },
].filter(Boolean))
function update(patch) { emit('update:modelValue', { ...link.value, ...patch }) }
function toggleTarget(key) {
  const next = { ...link.value, new_tab: false }
  delete next.open_modal
  if (!link.value[key]) next[key] = true
  emit('update:modelValue', next)
}
function setMode(value) {
  if (value === mode.value) return
  const modal = !!link.value.open_modal && MODAL_LINK_TYPES.includes(value)
  emit('update:modelValue', { type: value, new_tab: value === 'anchor' || modal ? false : !!link.value.new_tab, ...(modal ? { open_modal: true } : {}) })
  if (value === 'home') emit('destination-label', t('site.link.home'))
}
function chooseCollection(value) {
  update({ collection: value, entry: '', anchor: '' })
  if (mode.value === 'index' && value) emit('destination-label', resolveLocalized(props.settings?.routes?.[value]?.index_title, props.settings?.default_locale, props.settings?.default_locale) || props.types.find((type) => type.name === value)?.label || value)
}
function chosenEntry(entry) { resolvedEntry.value = entry; emit('destination-label', entryTitle(entry, props.settings?.default_locale)) }
</script>
