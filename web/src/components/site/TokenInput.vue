<template>
  <label class="flex items-center rounded-lg border border-slate-300 bg-white focus-within:border-theme-500 focus-within:ring-1 focus-within:ring-theme-500">
    <span v-if="prefix" class="pl-2 text-[10px] font-semibold text-slate-500">{{ prefix }}</span>
    <input
      type="number"
      min="0"
      :max="max"
      :value="modelValue"
      :aria-label="label"
      class="w-full min-w-0 border-0 bg-transparent px-2 py-1 text-right text-xs focus:ring-0"
      @input="update($event.target.value)"
    />
    <span class="pr-2 text-[10px] text-slate-400">px</span>
  </label>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Number, default: 0 },
  max: { type: Number, default: 400 },
  label: { type: String, default: "" },
  prefix: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

function update(raw) {
  if (raw === "") return;
  const value = Math.round(Number(raw));
  if (Number.isFinite(value)) emit("update:modelValue", Math.max(0, Math.min(props.max, value)));
}
</script>
