<template>
  <div v-if="locale !== defaultLocale" class="mb-2 space-y-1 text-xs text-slate-500">
    <p>{{ t('localization.source') }}: {{ source }}</p>
    <span>{{ t(`localization.${state}`) }}</span>
    <button v-if="!readonly && state !== 'inherited'" type="button" class="ml-2 text-theme-700 underline" @click="$emit('reset')">{{ t('localization.reset') }}</button>
  </div>
</template>
<script setup>
import { computed } from 'vue'
import { localizedState, resolveLocalized } from '../composables/localizedValues.js'
import { useI18n } from '../i18n/index.js'
const props = defineProps({ value: { default: null }, locale: { type: String, required: true }, defaultLocale: { type: String, required: true }, readonly: Boolean })
defineEmits(['reset'])
const { t } = useI18n()
const state = computed(() => localizedState(props.value, props.locale, props.defaultLocale))
const source = computed(() => { const value = resolveLocalized(props.value, props.defaultLocale, props.defaultLocale); return typeof value === 'string' ? value.replace(/<[^>]*>/g, '') : JSON.stringify(value) })
</script>
