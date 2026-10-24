<template>
  <TabNavigation
    v-if="items.length > 1"
    variant="pills"
    :items="items"
    :model-value="current"
    :aria-label="t('site.builder.language')"
    @update:model-value="select"
  />
</template>
<script setup>
import { computed } from 'vue'
import TabNavigation from './TabNavigation.vue'
import { useLanguages } from '../composables/useLanguages.js'
import { languageTabItem } from '../composables/localeOptions.js'
import { orderLocales } from '../composables/contentDisplay.js'
import { useI18n } from '../i18n/index.js'
// One switcher per screen; without v-model it drives the workspace-wide active language.
const props = defineProps({ modelValue: { type: String, default: '' }, locales: { type: Array, default: null }, compact: Boolean, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const languages = useLanguages()
const current = computed(() => props.modelValue || languages.activeLocale.value)
const items = computed(() => orderLocales(props.locales ?? languages.locales.value, languages.defaultLocale.value)
  .map((locale) => ({ ...languageTabItem(locale, languages.defaultLocale.value, { compact: props.compact, defaultLabel: t('contentEdit.default') }), disabled: props.disabled })))
function select(locale) {
  languages.activeLocale.value = locale
  emit('update:modelValue', locale)
}
</script>
