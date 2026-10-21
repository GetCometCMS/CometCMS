<template>
  <div class="space-y-4 p-3">
    <div v-if="state.record.value?.loading" class="flex justify-center py-8"><LoadingSpinner /></div>
    <div v-else-if="state.record.value?.error" role="alert" class="space-y-3 text-sm text-red-600">
      <p>{{ state.record.value.error }}</p>
      <button type="button" class="btn-secondary btn-sm" @click="state.load()">{{ t("site.builder.entryFieldsRetry") }}</button>
    </div>
    <template v-else-if="state.draft.value">
      <div class="flex items-start gap-2.5 rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">
        <Icon icon="mdi:file-document-outline" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
        <div class="min-w-0">
          <p class="break-words text-[13px] font-semibold text-slate-800">{{ state.record.value.entry.title || state.record.value.identifier }}</p>
          <p class="text-[11px] leading-snug text-slate-500">{{ t("site.builder.entryFieldsHint") }}</p>
        </div>
      </div>
      <p v-if="state.readonly.value" class="text-xs text-slate-500">{{ t("contentEdit.readOnlyDescription") }}</p>
      <p v-if="state.draft.value.error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ state.draft.value.error }}</p>
      <fieldset :disabled="busy" class="min-w-0 space-y-4">
        <BaseField
          v-for="(config, name) in state.fields.value"
          :key="name"
          :name="name"
          :config="fieldConfig(name, config)"
          :locale="state.activeLocale.value"
          :error="state.draft.value.errors[name]"
          :readonly="state.fieldReadonly(name)"
          compact
        >
          <TranslationInfo v-if="config.localized !== false" :value="state.record.value.entry.values?.[name]" :locale="state.activeLocale.value" :default-locale="state.defaultLocale.value" :readonly="state.fieldReadonly(name)" @reset="state.reset(name)" />
          <FieldInput
            :name="name"
            :config="config"
            :locale="state.activeLocale.value"
            :model-value="state.valueFor(name)"
            :readonly="state.fieldReadonly(name)"
            @update:model-value="state.update(name, $event)"
          />
        </BaseField>
      </fieldset>
    </template>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import TranslationInfo from "../../TranslationInfo.vue";
import BaseField from "../../BaseField.vue";
import FieldInput from "../../FieldInput.vue";
import LoadingSpinner from "../../LoadingSpinner.vue";
import { useI18n } from "../../../i18n/index.js";

defineProps({
  state: { type: Object, required: true },
  busy: { type: Boolean, default: false },
});
const { t } = useI18n();

function fieldConfig(name, config) {
  return name === "title" && !config.label ? { ...config, label: t("contentEdit.title") } : config;
}
</script>
