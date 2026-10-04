<template>
  <div class="space-y-2">
    <label :for="optionsId" class="text-xs text-slate-500 block mb-1">
      {{ t("fieldConfig.options") }}
      <span class="text-slate-400"
        >{{ t("fieldConfig.optionsHint") }} <code>key:Label</code>)</span
      >
    </label>
    <textarea
      :id="optionsId"
      v-model="field._optionsText"
      rows="4"
      placeholder="draft:Draft&#10;published:Published&#10;archived"
      class="form-textarea w-full rounded-lg border-slate-300 text-sm font-mono"
      @input="syncOptions"
    />
    <label class="inline-flex items-center gap-2">
      <input
        type="checkbox"
        v-model="field.multiple"
        class="form-checkbox rounded border-slate-300 text-theme-600"
      />
      <span class="text-sm text-slate-600">{{ t("fieldConfig.allowMultiple") }}</span>
    </label>
  </div>
</template>

<script setup>
import {
  parseSelectOptions,
  serializeSelectOptions,
} from "../composables/fieldBuilderUtils.js";
import { useId } from "vue";
import { useI18n } from "../i18n/index.js";

const { t } = useI18n();
const optionsId = useId();

const props = defineProps({
  field: { type: Object, required: true },
});

function syncOptions() {
  props.field.options = serializeSelectOptions(
    parseSelectOptions(props.field._optionsText),
  );
}
</script>
