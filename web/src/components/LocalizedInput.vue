<template>
  <fieldset :disabled="readonly" class="min-w-0 space-y-2">
    <div v-if="label || (!locale && languages.locales.value.length > 1)" class="flex flex-wrap items-center justify-between gap-2">
      <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">{{ label }}</label>
      <select
        v-if="!locale && languages.locales.value.length > 1"
        v-model="selected"
        class="form-select max-w-full rounded-lg border-slate-200 bg-slate-50 py-1.5 pl-3 pr-8 text-xs font-medium text-slate-600"
        :aria-label="t('site.builder.language')"
      >
        <option v-for="code in languages.locales.value" :key="code" :value="code">{{ languageLabel(code) }}</option>
      </select>
    </div>
    <slot :value="value" :update="update">
      <textarea v-if="multiline" :id="id" :value="value ?? ''" :rows="rows" :maxlength="maxlength" :aria-invalid="invalid || undefined" class="form-textarea w-full rounded-lg text-sm" :class="invalid ? 'border-red-400' : 'border-slate-300'" @input="update($event.target.value)"></textarea>
      <input v-else :id="id" :value="value ?? ''" :maxlength="maxlength" :placeholder="placeholder" :aria-invalid="invalid || undefined" type="text" class="form-input w-full rounded-lg text-sm" :class="invalid ? 'border-red-400' : 'border-slate-300'" @input="update($event.target.value)" />
    </slot>
    <div v-if="languages.locales.value.length > 1 || active !== defaultLocale" class="space-y-2 text-xs" :class="active !== defaultLocale ? 'rounded-lg bg-slate-50 px-3 py-2.5' : ''">
      <div v-if="active !== defaultLocale" class="space-y-1">
        <p class="font-medium text-slate-500">{{ t('localization.source') }} · {{ defaultLocale.toUpperCase() }}</p>
        <p class="whitespace-pre-wrap break-words text-slate-700">{{ source }}</p>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 font-medium" :class="statusClass">
          <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
          {{ t(`localization.${state}`) }}
        </span>
        <button v-if="!readonly && active !== defaultLocale && state !== 'inherited'" type="button" class="inline-flex items-center gap-1.5 rounded text-theme-700 underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-theme-500" @click="emit('update:modelValue', removeLocalized(modelValue, active))">
          <Icon icon="mdi:restore" class="h-3.5 w-3.5 shrink-0" />
          {{ t('localization.reset') }}
        </button>
      </div>
    </div>
  </fieldset>
</template>
<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useLanguages } from '../composables/useLanguages.js'
import { LOCALE_OPTIONS } from '../composables/localeOptions.js'
import { localizedState, removeLocalized, resolveLocalized, setLocalized } from '../composables/localizedValues.js'
import { useI18n } from '../i18n/index.js'
const props = defineProps({ modelValue: { default: '' }, locale: { type: String, default: '' }, id: { type: String, default: undefined }, label: { type: String, default: '' }, invalid: Boolean, multiline: Boolean, readonly: Boolean, rows: { type: [Number, String], default: 2 }, maxlength: { type: [Number, String], default: undefined }, placeholder: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const languages = useLanguages()
const defaultLocale = languages.defaultLocale
const selected = languages.activeLocale
const active = computed(() => props.locale || selected.value || defaultLocale.value)
const value = computed(() => resolveLocalized(props.modelValue, active.value, defaultLocale.value))
const source = computed(() => { const value = resolveLocalized(props.modelValue, defaultLocale.value, defaultLocale.value); return typeof value === 'string' ? value.replace(/<[^>]*>/g, '') : JSON.stringify(value) })
const state = computed(() => localizedState(props.modelValue, active.value, defaultLocale.value))
const statusClass = computed(() => state.value === 'review' ? 'bg-amber-100 text-amber-800' : state.value === 'translated' ? 'bg-theme-50 text-theme-700' : 'bg-slate-100 text-slate-600')
function languageLabel(code) { return LOCALE_OPTIONS.find((option) => option.value === code)?.label ?? code.toUpperCase() }
function update(value) { emit('update:modelValue', setLocalized(props.modelValue, active.value, value, defaultLocale.value)) }
defineExpose({ focus: () => document.getElementById(props.id)?.focus() })
</script>
