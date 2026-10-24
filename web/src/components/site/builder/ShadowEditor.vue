<template>
  <div class="space-y-2">
    <select :id="id" :value="mode" class="form-select w-full rounded-lg border-slate-300 py-1.5 text-xs" @change="select($event.target.value)">
      <option value="">{{ t('site.option.default') }}</option>
      <option value="none">{{ t('site.option.none') }}</option>
      <optgroup :label="t(text ? 'site.shadow.presets' : 'site.shadow.sizes')">
        <option v-for="preset in fixed" :key="preset" :value="preset">{{ presetLabel(preset) }}</option>
      </optgroup>
      <optgroup v-if="themePresets.length" :label="t('site.shadow.themePresets')">
        <option v-for="[key, preset] in themePresets" :key="key" :value="key">{{ shadowLabel(key, preset, t) }}</option>
      </optgroup>
      <option v-if="missing" :value="mode">{{ `${mode} (${t('site.builder.missing')})` }}</option>
      <option value="custom">{{ t('site.shadow.custom') }}</option>
    </select>
    <template v-if="mode && mode !== 'none'">
      <div class="flex h-20 items-center justify-center overflow-hidden rounded-lg bg-slate-100" aria-hidden="true">
        <span class="rounded-md bg-white px-4 py-1.5 text-sm font-semibold text-slate-800" :style="preview">{{ text ? 'Aa' : t('site.style.shadow') }}</span>
      </div>
      <details :open="mode === 'custom'" class="group">
        <summary class="cursor-pointer text-xs text-slate-500 hover:text-theme-700">{{ t('site.shadow.customize') }}</summary>
        <ShadowFields class="mt-2" :id="id" :model-value="settings" :text="text" :options="options" @update:model-value="emit('update:modelValue', $event)" />
      </details>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ShadowFields from './ShadowFields.vue';
import { useI18n } from '../../../i18n/index.js';
import { FIXED_SHADOWS, TEXT_SHADOWS, shadowLabel, shadowSettings, shadowPreview } from '../../../composables/shadows.js';

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: [String, Object], default: '' },
  text: { type: Boolean, default: false },
  options: { type: Array, default: () => [] },
  /** The theme's box shadow presets, by token. */
  presets: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const mode = computed(() => typeof props.modelValue === 'string' ? props.modelValue : 'custom');
const fixed = computed(() => props.text ? TEXT_SHADOWS : FIXED_SHADOWS);
const themePresets = computed(() => props.text ? [] : Object.entries(props.presets ?? {}));
/** A preset deleted from the theme renders nothing; say so instead of showing another choice. */
const missing = computed(() => !['', 'none', 'custom', ...fixed.value].includes(mode.value) && !themePresets.value.some(([key]) => key === mode.value));
const settings = computed(() => shadowSettings(props.modelValue, props.text, props.presets));
const preview = computed(() => shadowPreview(props.modelValue, props.text, props.options, props.presets));
function presetLabel(preset) {
  return ['hard', 'glow'].includes(preset) ? t(`site.shadow.${preset}`) : t(`site.option.${preset}`);
}
function select(value) {
  emit('update:modelValue', value === 'custom' ? { ...settings.value } : value);
}
</script>
