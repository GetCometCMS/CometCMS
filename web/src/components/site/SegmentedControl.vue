<template>
  <div class="flex w-full flex-wrap rounded-lg border border-slate-200 bg-slate-50 p-0.5" role="radiogroup">
    <button
      v-for="option in options"
      :key="String(option.value)"
      type="button"
      role="radio"
      :aria-checked="modelValue === option.value"
      :title="option.title || option.label"
      class="flex min-w-0 flex-1 items-center justify-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition-colors"
      :class="modelValue === option.value ? 'bg-white text-theme-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900'"
      @click="$emit('update:modelValue', option.value)"
    >
      <Icon v-if="option.icon" :icon="option.icon" class="h-4 w-4 shrink-0" />
      <span v-if="!option.icon || option.showLabel" class="truncate">{{ option.label }}</span>
      <span v-else class="sr-only">{{ option.label }}</span>
    </button>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

defineProps({
  modelValue: { type: [String, Number, Boolean], default: "" },
  /** [{ value, label, icon?, title?, showLabel? }] */
  options: { type: Array, default: () => [] },
});
defineEmits(["update:modelValue"]);
</script>
