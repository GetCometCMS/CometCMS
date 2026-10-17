<template>
  <div class="flex w-full rounded-lg border border-slate-200 bg-slate-50 p-0.5" :role="multiple ? 'group' : 'radiogroup'">
    <button
      v-for="option in options"
      :key="String(option.value)"
      type="button"
      :role="multiple ? undefined : 'radio'"
      :aria-checked="multiple ? undefined : isActive(option)"
      :aria-pressed="multiple ? isActive(option) : undefined"
      :aria-label="option.title"
      :title="option.title"
      class="flex h-7 min-w-0 flex-1 items-center justify-center rounded-md text-[11px] font-semibold transition-colors"
      :class="buttonClass(option)"
      @click="select(option)"
    >
      <span
        v-if="option.swatch"
        class="h-4 w-4 rounded-full ring-1 ring-black/15"
        :style="{ background: option.swatch }"
      ></span>
      <Icon v-else-if="option.icon" :icon="option.icon" class="h-4 w-4" />
      <span v-else class="truncate px-0.5" :style="option.style">{{ option.label }}</span>
    </button>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

const props = defineProps({
  modelValue: { default: null },
  /** [{ value, title, label?, icon?, swatch?, style? }] */
  options: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false },
  /** Highlight active options as "off" (e.g. hidden on a device). */
  danger: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

function isActive(option) {
  return props.multiple ? (props.modelValue ?? []).includes(option.value) : props.modelValue === option.value;
}

function buttonClass(option) {
  if (!isActive(option)) return "text-slate-500 hover:bg-white/70 hover:text-slate-900";
  return props.danger
    ? "bg-red-50 text-red-600 shadow-sm ring-1 ring-red-200"
    : "bg-white text-theme-700 shadow-sm ring-1 ring-slate-200";
}

function select(option) {
  if (!props.multiple) {
    emit("update:modelValue", option.value);
    return;
  }

  const current = new Set(props.modelValue ?? []);
  if (current.has(option.value)) current.delete(option.value);
  else current.add(option.value);
  emit("update:modelValue", props.options.map((item) => item.value).filter((value) => current.has(value)));
}
</script>
