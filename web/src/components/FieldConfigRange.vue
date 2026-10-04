<template>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div>
      <label :for="`${uid}-min`" class="text-xs text-slate-500 block mb-1">{{ t("fieldConfig.minimum") }}</label>
      <input
        :id="`${uid}-min`"
        v-model.number="field.min"
        type="number"
        step="any"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        @input="normalize"
      />
    </div>

    <div>
      <label :for="`${uid}-step`" class="text-xs text-slate-500 block mb-1">{{ t("fieldConfig.step") }}</label>
      <input
        :id="`${uid}-step`"
        v-model.number="field.step"
        type="number"
        step="any"
        min="0"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        @input="normalize"
      />
    </div>

    <div>
      <label :for="`${uid}-max`" class="text-xs text-slate-500 block mb-1">{{ t("fieldConfig.maximum") }}</label>
      <input
        :id="`${uid}-max`"
        v-model.number="field.max"
        type="number"
        step="any"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        @input="normalize"
      />
    </div>

    <div>
      <label :for="`${uid}-decimals`" class="text-xs text-slate-500 block mb-1">{{ t("fieldConfig.decimals") }}</label>
      <select
        :id="`${uid}-decimals`"
        v-model="field.display_decimals"
        class="form-select w-full rounded-lg border-slate-300 text-sm"
        @change="normalize"
      >
        <option :value="0">0</option>
        <option :value="1">1</option>
        <option :value="2">2</option>
        <option :value="3">3</option>
        <option value="full">{{ t("fieldConfig.decimalsFull") }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { rangeDefaults } from "../composables/fieldBuilderUtils.js";
import { useId } from "vue";
import { useI18n } from "../i18n/index.js";

const { t } = useI18n();
const uid = useId();

const props = defineProps({
  field: { type: Object, required: true },
});

function normalize() {
  Object.assign(props.field, rangeDefaults(props.field));
}
</script>
