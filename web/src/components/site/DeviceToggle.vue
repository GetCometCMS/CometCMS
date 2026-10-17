<template>
  <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5" role="group" :aria-label="t('site.preview.device')">
    <button
      v-for="device in DEVICES"
      :key="device.value"
      type="button"
      class="rounded-md p-1.5 transition-colors"
      :class="modelValue === device.value ? 'bg-theme-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800'"
      :aria-pressed="modelValue === device.value"
      :title="t(`site.device.${device.value}`)"
      @click="$emit('update:modelValue', device.value)"
    >
      <Icon :icon="device.icon" class="h-4 w-4" />
      <span class="sr-only">{{ t(`site.device.${device.value}`) }}</span>
    </button>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { useI18n } from "../../i18n/index.js";

const DEVICES = [
  { value: "desktop", icon: "mdi:monitor" },
  { value: "tablet", icon: "mdi:tablet" },
  { value: "mobile", icon: "mdi:cellphone" },
];

defineProps({ modelValue: { type: String, default: "desktop" } });
defineEmits(["update:modelValue"]);
const { t } = useI18n();
</script>
