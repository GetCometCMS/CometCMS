<template>
  <div class="space-y-2">
    <select :id="id" :value="mode" class="form-select w-full rounded-lg border-slate-300 py-1.5 text-xs" @change="select($event.target.value)">
      <option v-for="preset in presets" :key="preset" :value="preset">{{ presetLabel(preset) }}</option>
    </select>
    <template v-if="mode && mode !== 'none'">
      <div class="flex h-20 items-center justify-center overflow-hidden rounded-lg bg-slate-100" aria-hidden="true">
        <span class="rounded-md bg-white px-4 py-1.5 text-sm font-semibold text-slate-800" :style="preview">{{ text ? 'Aa' : t('site.style.shadow') }}</span>
      </div>
      <details :open="mode === 'custom'" class="group">
        <summary class="cursor-pointer text-xs text-slate-500 hover:text-theme-700">{{ t('site.shadow.customize') }}</summary>
        <div class="mt-2 space-y-2">
          <ThemeColorPicker :id="`${id}-color`" :label="t('site.shadow.color')" :model-value="settings.color" :options="options" :allow-empty="false" @update:model-value="update({ color: $event })" />
          <div class="grid grid-cols-2 gap-2">
            <div v-for="field in fields" :key="field.key">
              <label :for="`${id}-${field.key}`" class="mb-1 block text-[11px] text-slate-500">{{ t(`site.shadow.${field.key}`) }} <span class="text-slate-400">{{ field.key === 'opacity' ? '%' : 'px' }}</span></label>
              <input :id="`${id}-${field.key}`" type="number" :min="field.min" :max="field.max" step="1" :value="settings[field.key]" class="form-input w-full rounded-lg border-slate-300 py-1.5 text-xs" @input="setNumber(field, $event.target.value)" />
            </div>
          </div>
          <label v-if="!text" class="flex items-center gap-2 text-xs text-slate-600">
            <input type="checkbox" :checked="settings.inset" class="form-checkbox rounded border-slate-300 text-theme-600" @change="update({ inset: $event.target.checked })" />
            {{ t('site.shadow.inset') }}
          </label>
        </div>
      </details>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ThemeColorPicker from './ThemeColorPicker.vue';
import { useI18n } from '../../../i18n/index.js';
import { shadowSettings, shadowPreview } from '../../../composables/shadows.js';

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: [String, Object], default: '' },
  text: { type: Boolean, default: false },
  options: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const mode = computed(() => typeof props.modelValue === 'string' ? props.modelValue : 'custom');
const presets = computed(() => ['', 'none', 'sm', 'md', 'lg', 'hard', 'glow', ...(!props.text ? ['inset'] : []), 'custom']);
const settings = computed(() => shadowSettings(props.modelValue, props.text));
const fields = computed(() => [
  { key: 'x', min: -100, max: 100 }, { key: 'y', min: -100, max: 100 },
  { key: 'blur', min: 0, max: 200 }, ...(!props.text ? [{ key: 'spread', min: -100, max: 100 }] : []),
  { key: 'opacity', min: 0, max: 100 },
]);
const preview = computed(() => shadowPreview(props.modelValue, props.text, props.options));
function presetLabel(preset) {
  return ['', 'none', 'sm', 'md', 'lg'].includes(preset) ? t(`site.option.${preset || 'default'}`) : t(`site.shadow.${preset}`);
}
function select(value) {
  emit('update:modelValue', value === 'custom' ? { ...settings.value } : value);
}
function update(values) {
  emit('update:modelValue', { ...settings.value, ...values });
}
function setNumber(field, value) {
  if (value === '' || !Number.isFinite(Number(value))) return;
  update({ [field.key]: Math.max(field.min, Math.min(field.max, Math.trunc(Number(value)))) });
}
</script>
