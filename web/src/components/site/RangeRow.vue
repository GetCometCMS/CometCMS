<template>
  <label class="block">
    <span class="mb-1 flex items-center justify-between text-sm font-medium text-slate-700">
      {{ label }}
      <span class="font-mono text-xs text-slate-500">{{ modelValue }}{{ unit }}</span>
    </span>
    <input
      type="range"
      class="w-full accent-theme-600"
      :min="min"
      :max="max"
      :step="step"
      :value="modelValue"
      @input="$emit('update:modelValue', Number($event.target.value))"
    />
  </label>
</template>

<script setup>
defineProps({
  modelValue: { type: Number, default: 0 },
  label: { type: String, default: "" },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  step: { type: Number, default: 1 },
  unit: { type: String, default: "" },
});
defineEmits(["update:modelValue"]);
</script>
