<template>
  <fieldset :disabled="readonly" class="min-w-0 space-y-2">
    <select v-if="!locale && languages.locales.value.length > 1" v-model="selected" class="form-select rounded-lg border-slate-300 py-1 text-xs" :aria-label="t('site.builder.language')">
      <option v-for="code in languages.locales.value" :key="code" :value="code">{{ code.toUpperCase() }}</option>
    </select>
    <p v-if="active !== defaultLocale" class="break-words text-xs text-slate-500">{{ t('localization.source') }}: {{ source }}</p>
    <slot :value="value" :update="update">
      <textarea v-if="multiline" :id="id" :value="value ?? ''" :rows="rows" :maxlength="maxlength" class="form-textarea w-full rounded-lg border-slate-300 text-sm" @input="update($event.target.value)"></textarea>
      <input v-else :id="id" :value="value ?? ''" :maxlength="maxlength" :placeholder="placeholder" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" @input="update($event.target.value)" />
    </slot>
    <div v-if="languages.locales.value.length > 1" class="flex flex-wrap gap-2 text-xs text-slate-500">
      <span>{{ t(`localization.${state}`) }}</span>
      <button v-if="active !== defaultLocale && state !== 'inherited'" type="button" class="text-theme-700 underline" @click="emit('update:modelValue', removeLocalized(modelValue, active))">{{ t('localization.reset') }}</button>
    </div>
  </fieldset>
</template>
<script setup>
import { computed } from 'vue'
import { useLanguages } from '../composables/useLanguages.js'
import { localizedState, removeLocalized, resolveLocalized, setLocalized } from '../composables/localizedValues.js'
import { useI18n } from '../i18n/index.js'
const props = defineProps({ modelValue: { default: '' }, locale: { type: String, default: '' }, id: { type: String, default: undefined }, multiline: Boolean, readonly: Boolean, rows: { type: [Number, String], default: 2 }, maxlength: { type: [Number, String], default: undefined }, placeholder: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const languages = useLanguages()
const defaultLocale = languages.defaultLocale
const selected = languages.activeLocale
const active = computed(() => props.locale || selected.value || defaultLocale.value)
const value = computed(() => resolveLocalized(props.modelValue, active.value, defaultLocale.value))
const source = computed(() => { const value = resolveLocalized(props.modelValue, defaultLocale.value, defaultLocale.value); return typeof value === 'string' ? value.replace(/<[^>]*>/g, '') : JSON.stringify(value) })
const state = computed(() => localizedState(props.modelValue, active.value, defaultLocale.value))
function update(value) { emit('update:modelValue', setLocalized(props.modelValue, active.value, value, defaultLocale.value)) }
defineExpose({ focus: () => document.getElementById(props.id)?.focus() })
</script>
