<template>
  <div class="inline-flex rounded-lg bg-slate-100 p-0.5" role="group" :aria-label="t('site.preview.device')">
    <button
      v-for="device in devices"
      :key="device.value"
      type="button"
      class="rounded-md p-1.5 transition-colors"
      :class="modelValue === device.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
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
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { useI18n } from "../../i18n/index.js";

const DEVICES = [
  { value: "desktop", icon: "mdi:monitor" },
  { value: "tablet", icon: "mdi:tablet" },
  { value: "mobile", icon: "mdi:cellphone" },
];

const props = defineProps({
  modelValue: { type: String, default: "desktop" },
  /** The fluid viewport is an editor tool, not a device preview. */
  allowFullWidth: { type: Boolean, default: false },
});
defineEmits(["update:modelValue"]);
const { t } = useI18n();
const devices = computed(() => props.allowFullWidth
  ? [...DEVICES, { value: "full", icon: "mdi:arrow-expand-horizontal" }]
  : DEVICES);
</script>
