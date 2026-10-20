<template>
  <FieldInput
    :name="name"
    :config="{ type: 'media', multiple }"
    :compact="compact"
    :model-value="multiple ? (Array.isArray(modelValue) ? modelValue : []) : (modelValue ? [modelValue] : [])"
    @update:model-value="onUpdate"
  />
</template>

<script setup>
import FieldInput from "../FieldInput.vue";

const props = defineProps({
  modelValue: { type: [String, Array], default: "" },
  multiple: { type: Boolean, default: false },
  name: { type: String, default: "media" },
  /** Small thumbnail and buttons, for narrow sidebars. */
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

// Media fields store a list of filenames; website settings hold just one.
function onUpdate(value) {
  emit("update:modelValue", props.multiple ? (Array.isArray(value) ? value : []) : (Array.isArray(value) ? (value[0] ?? "") : (value ?? "")));
}
</script>
