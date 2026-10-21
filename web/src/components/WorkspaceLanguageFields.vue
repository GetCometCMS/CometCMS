<template>
  <fieldset class="space-y-2">
    <legend class="sr-only">{{ t('localization.languages') }}</legend>
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="min-w-0">
        <div class="form-label">{{ t('localization.languages') }}</div>
        <SearchableSelect
          :model-value="modelValue.locales"
          :options="LOCALE_OPTIONS"
          :aria-label="t('localization.languages')"
          multiple
          @update:model-value="setLocales"
        />
      </div>
      <div class="min-w-0">
        <div class="form-label">{{ t('localization.default') }}</div>
        <SearchableSelect
          :model-value="modelValue.default_locale"
          :options="defaultLocaleOptions"
          :aria-label="t('localization.default')"
          :clearable="false"
          :disabled="!modelValue.locales.length"
          @update:model-value="emit('update:modelValue', { ...modelValue, default_locale: $event })"
        />
      </div>
    </div>
    <p class="text-xs text-slate-500">{{ t('setup.languagesHelp') }}</p>
  </fieldset>
</template>

<script setup>
import { computed } from 'vue';
import SearchableSelect from './SearchableSelect.vue';
import { LOCALE_OPTIONS } from '../composables/localeOptions.js';
import { useI18n } from '../i18n/index.js';

const props = defineProps({ modelValue: { type: Object, required: true } });
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const defaultLocaleOptions = computed(() => LOCALE_OPTIONS.filter((option) => props.modelValue.locales.includes(option.value)));

function setLocales(locales) {
  emit('update:modelValue', {
    locales,
    default_locale: locales.includes(props.modelValue.default_locale) ? props.modelValue.default_locale : (locales[0] ?? ''),
  });
}
</script>
