<template>
  <div class="space-y-2">
    <select :id="id" :value="modelValue?.type || ''" class="form-select w-full rounded-lg border-slate-300 py-1.5 text-xs" @change="setType($event.target.value)">
      <option value="">{{ t('site.option.none') }}</option>
      <option value="linear">{{ t('site.gradient.linear') }}</option>
      <option value="radial">{{ t('site.gradient.radial') }}</option>
    </select>
    <template v-if="modelValue?.type">
      <div class="h-8 rounded-md ring-1 ring-black/10" :style="{ backgroundImage: preview }" aria-hidden="true"></div>
      <div v-for="key in ['from', 'to']" :key="key">
        <label :for="`${id}-${key}`" class="mb-1 block text-xs text-slate-600">{{ t(`site.gradient.${key}`) }}</label>
        <ThemeColorPicker :id="`${id}-${key}`" :label="t(`site.gradient.${key}`)" :model-value="modelValue[key]" :options="options" :allow-empty="false" @update:model-value="update({ [key]: $event })" />
      </div>
      <div v-if="modelValue.type === 'linear'">
        <label :for="`${id}-angle`" class="mb-1 block text-xs text-slate-600">{{ t('site.gradient.angle') }}</label>
        <div class="flex items-center gap-2">
          <input type="range" min="0" max="360" :value="modelValue.angle" :aria-label="t('site.gradient.angle')" class="min-w-0 flex-1 accent-theme-600" @input="setAngle($event.target.value)" />
          <input :id="`${id}-angle`" type="number" min="0" max="360" :value="modelValue.angle" class="form-input w-20 rounded-lg border-slate-300 py-1.5 text-xs" @change="setAngle($event.target.value)" />
          <span class="text-xs text-slate-500">°</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from "vue";
import ThemeColorPicker from "./ThemeColorPicker.vue";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: [Object, Array], default: () => [] },
  options: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const preview = computed(() => {
  const color = (key, fallback) => props.options.find((option) => option.value === props.modelValue[key])?.swatch
    ?? props.options.find((option) => option.value === fallback)?.swatch ?? '#cccccc';
  const colors = `${color('from', 'primary')}, ${color('to', 'accent')}`;
  return props.modelValue.type === 'radial' ? `radial-gradient(ellipse at center, ${colors})` : `linear-gradient(${props.modelValue.angle}deg, ${colors})`;
});

function update(values) {
  emit("update:modelValue", { ...props.modelValue, ...values });
}

function setType(type) {
  if (!type) return emit("update:modelValue", []);
  const value = { type, from: props.modelValue.from || 'primary', to: props.modelValue.to || 'accent' };
  if (type === 'linear') value.angle = props.modelValue.angle ?? 135;
  emit("update:modelValue", value);
}

function setAngle(value) {
  update({ angle: Math.max(0, Math.min(360, Math.trunc(Number(value) || 0))) });
}
</script>
