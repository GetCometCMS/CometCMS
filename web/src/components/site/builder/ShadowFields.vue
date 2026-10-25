<template>
  <div class="space-y-2">
    <ThemeColorPicker :id="`${id}-color`" :label="t('site.shadow.color')" :model-value="modelValue.color" :options="colorOptions" :allow-empty="false" @update:model-value="update({ color: $event })" />
    <div class="grid grid-cols-2 gap-2">
      <div v-for="field in fields" :key="field.key">
        <label :for="`${id}-${field.key}`" class="mb-1 block text-[11px] text-slate-500">{{ t(`site.shadow.${field.key}`) }} <span class="text-slate-400">{{ field.key === 'opacity' ? '%' : 'px' }}</span></label>
        <input :id="`${id}-${field.key}`" type="number" :min="field.min" :max="field.max" step="1" :value="modelValue[field.key]" class="form-input w-full rounded-lg border-slate-300 py-1.5 text-xs" @input="setNumber(field, $event.target.value)" />
      </div>
    </div>
    <label v-if="!text" class="flex items-center gap-2 text-xs text-slate-600">
      <input type="checkbox" :checked="modelValue.inset" class="form-checkbox rounded border-slate-300 text-theme-600" @change="update({ inset: $event.target.checked })" />
      {{ t('site.shadow.inset') }}
    </label>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ThemeColorPicker from './ThemeColorPicker.vue';
import { useI18n } from '../../../i18n/index.js';
import { shadowColorOptions } from '../../../composables/shadows.js';

/** Color and geometry of one shadow, shared by the builder and the theme presets. */
const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: Object, required: true },
  text: { type: Boolean, default: false },
  options: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const colorOptions = computed(() => shadowColorOptions(props.options, t('site.shadow.black')));
const fields = computed(() => [
  { key: 'x', min: -100, max: 100 }, { key: 'y', min: -100, max: 100 },
  { key: 'blur', min: 0, max: 200 }, ...(!props.text ? [{ key: 'spread', min: -100, max: 100 }] : []),
  { key: 'opacity', min: 0, max: 100 },
]);
function update(values) {
  emit('update:modelValue', { ...props.modelValue, ...values });
}
function setNumber(field, value) {
  if (value === '' || !Number.isFinite(Number(value))) return;
  update({ [field.key]: Math.max(field.min, Math.min(field.max, Math.trunc(Number(value)))) });
}
</script>
