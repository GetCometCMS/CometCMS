<template>
  <div
    class="flex flex-col items-center justify-center text-center"
    :class="compact ? 'px-4 py-8' : 'px-6 py-14'"
  >
    <span
      class="mb-4 flex items-center justify-center rounded-xl bg-slate-100 text-slate-400 ring-1 ring-inset ring-slate-200"
      :class="compact ? 'h-10 w-10' : 'h-12 w-12'"
      aria-hidden="true"
    >
      <Icon :icon="icon" :class="compact ? 'h-5 w-5' : 'h-6 w-6'" />
    </span>
    <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
    <p v-if="description || $slots.description" class="mt-1 max-w-sm text-sm text-slate-500">
      <slot name="description">{{ description }}</slot>
    </p>
    <div v-if="$slots.default" class="mt-5 flex flex-wrap items-center justify-center gap-2">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

defineProps({
  icon: { type: String, default: "mdi:tray" },
  title: { type: String, required: true },
  description: { type: String, default: "" },
  compact: { type: Boolean, default: false },
});
</script>
