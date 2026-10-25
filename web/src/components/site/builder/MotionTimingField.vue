<template>
  <div>
    <div class="mb-2 flex items-center justify-between gap-2">
      <label :for="id" class="text-xs font-medium text-slate-700">{{ label }}</label>
      <span class="flex w-24 shrink-0 items-center rounded-md border border-slate-300 bg-white focus-within:border-theme-500 focus-within:ring-1 focus-within:ring-theme-500">
        <input :id="id" type="number" inputmode="decimal" :min="min / 1000" :max="max / 1000" :step="step / 1000" :value="modelValue / 1000" class="w-full min-w-0 border-0 bg-transparent px-2 py-1 text-right font-mono text-xs focus:ring-0" @change="update($event.target.value)" />
        <span class="pr-2 text-xs text-slate-400">s</span>
      </span>
    </div>
    <input type="range" :min="min" :max="max" :step="step" :value="modelValue" :aria-label="label" class="block w-full accent-theme-600" @input="emit('update:modelValue', Number($event.target.value))" />
  </div>
</template>

<script setup>
import { useId } from 'vue';
const props = defineProps({
  modelValue: { type: Number, required: true },
  label: { type: String, required: true },
  min: { type: Number, default: 0 },
  max: { type: Number, required: true },
  step: { type: Number, default: 10 },
});
const emit = defineEmits(['update:modelValue']);
const id = `motion-time-${useId()}`;
function update(raw) {
  if (raw === '' || !Number.isFinite(Number(raw))) return;
  emit('update:modelValue', Math.max(props.min, Math.min(props.max, Math.round(Number(raw) * 1000 / props.step) * props.step)));
}
</script>
