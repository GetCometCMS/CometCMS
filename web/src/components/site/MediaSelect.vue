<template>
  <FieldInput
    :name="name"
    :config="{ type: 'media', multiple: false }"
    :model-value="modelValue ? [modelValue] : []"
    @update:model-value="onUpdate"
  />
</template>

<script setup>
import FieldInput from "../FieldInput.vue";

defineProps({
  modelValue: { type: String, default: "" },
  name: { type: String, default: "media" },
});

const emit = defineEmits(["update:modelValue"]);

// Media fields store a list of filenames; website settings hold just one.
function onUpdate(value) {
  emit("update:modelValue", Array.isArray(value) ? (value[0] ?? "") : (value ?? ""));
}
</script>
