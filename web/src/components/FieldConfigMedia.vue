<template>
  <label class="inline-flex items-center gap-2">
    <input
      type="checkbox"
      v-model="field.multiple"
      class="form-checkbox rounded border-slate-300 text-theme-600"
    />
    <span class="text-sm text-slate-600">{{ t("fieldConfig.allowMultiple") }}</span>
  </label>
</template>

<script setup>
import { useI18n } from "../i18n/index.js";

const { t } = useI18n();
defineProps({
  field: { type: Object, required: true },
});
</script>
