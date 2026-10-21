<template>
  <div :class="compact ? 'space-y-2' : 'space-y-3'">
    <div v-if="compact" class="flex gap-1.5">
      <LinkModeMenu :model-value="mode" :options="modes" :label="t('site.link.type')" @update:model-value="setMode" />
      <button v-if="!['none', 'anchor'].includes(link.type)" type="button"
        class="flex w-8 shrink-0 items-center justify-center rounded-lg border transition-colors"
        :class="link.new_tab ? 'border-theme-200 bg-theme-50 text-theme-700' : 'border-slate-300 bg-white text-slate-400 hover:text-slate-700'"
        :aria-pressed="!!link.new_tab" :title="t('site.link.newTab')" :aria-label="t('site.link.newTab')" @click="update({ new_tab: !link.new_tab })">
        <Icon icon="mdi:open-in-new" class="h-4 w-4" />
      </button>
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
    <MenuLinkSummary v-if="mode === 'home'" :link="link" :types="types" :settings="settings" :context="context" verbose />
    <p v-if="unavailableRoute" class="text-xs text-amber-700" role="status">{{ t('site.link.unavailableRoute') }}</p>

    <label v-if="mode === 'url'" class="block">
      <span :class="labelClass">{{ t('site.link.url') }}</span>
      <input :value="link.url || ''" type="text" :class="fieldClass" class="form-input w-full rounded-lg border-slate-300" :title="compact ? t('site.link.urlHint') : undefined"
        :placeholder="t('site.link.urlPlaceholder')" @input="update({ url: $event.target.value })" />
      <span v-if="!compact" class="mt-1 block text-xs text-slate-500">{{ t('site.link.urlHint') }}</span>
    </label>

    <label v-if="['anchor', 'home', 'entry', 'index'].includes(link.type)" class="block">
      <span :class="labelClass">{{ t(link.type === 'anchor' ? 'site.link.anchor' : 'site.link.optionalAnchor') }}</span>
      <div class="flex items-center rounded-lg border border-slate-300 bg-white focus-within:ring-1 focus-within:ring-theme-500">
        <span class="pl-3 text-slate-400" :class="fieldClass" aria-hidden="true">#</span>
        <input :value="link.anchor || ''" type="text" :class="fieldClass" class="form-input min-w-0 flex-1 rounded-lg border-0 focus:ring-0"
          :title="compact ? t(link.type === 'anchor' ? 'site.link.anchorHint' : 'site.link.optionalAnchorHint') : undefined"
          :placeholder="t('site.link.anchorPlaceholder')" @input="update({ anchor: $event.target.value })" />
      </div>
      <span v-if="!compact" class="mt-1 block text-xs text-slate-500">{{ t(link.type === 'anchor' ? 'site.link.anchorHint' : 'site.link.optionalAnchorHint') }}</span>
    </label>
    <p v-if="issue" class="text-xs text-red-700" role="status">{{ t(`site.link.${issue}`) }}</p>
    <label v-if="!compact && !['none', 'anchor'].includes(link.type)" class="flex items-center gap-2 text-xs text-slate-600">
      <input type="checkbox" class="rounded border-slate-300 text-theme-600" :checked="link.new_tab" @change="update({ new_tab: $event.target.checked })" />
      {{ t('site.link.newTab') }}
    </label>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import ContentLinkPicker from './ContentLinkPicker.vue'
import LinkModeMenu from './LinkModeMenu.vue'
import MenuLinkSummary from './MenuLinkSummary.vue'
import { destinationAvailable, eligibleTypes, entryTitle, linkIssue } from '../../composables/siteLinks.js'
import { resolveLocalized } from "../../composables/localizedValues.js";
import { useI18n } from '../../i18n/index.js'

const props = defineProps({
  modelValue: { type: Object, default: () => ({ type: 'none' }) },
  types: { type: Array, default: () => [] }, settings: { type: Object, default: null },
  context: { type: String, default: '' }, groupHeading: { type: Boolean, default: false },
  allow: { type: Array, default: () => ['none', 'url', 'current', 'home', 'index', 'entry', 'anchor'] },
  /** One-row type menu and smaller fields, for narrow sidebars. */
  compact: { type: Boolean, default: false },
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
  emit('update:modelValue', { type: value, new_tab: value === 'anchor' ? false : !!link.value.new_tab })
  if (value === 'home') emit('destination-label', t('site.link.home'))
}
function chooseCollection(value) {
  update({ collection: value, entry: '', anchor: '' })
  if (mode.value === 'index' && value) emit('destination-label', resolveLocalized(props.settings?.routes?.[value]?.index_title, props.settings?.default_locale, props.settings?.default_locale) || props.types.find((type) => type.name === value)?.label || value)
}
function chosenEntry(entry) { resolvedEntry.value = entry; emit('destination-label', entryTitle(entry, props.settings?.default_locale)) }
</script>
