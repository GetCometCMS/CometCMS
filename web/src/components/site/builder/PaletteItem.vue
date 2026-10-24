<template>
  <button
    type="button"
    :draggable="!disabled"
    :disabled="disabled"
    class="group/item flex w-full min-w-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-medium text-slate-700 shadow-xs transition hover:border-theme-300 hover:bg-theme-50/40 hover:text-theme-700"
    :title="hint || undefined"
    :style="{ cursor: disabled ? 'not-allowed' : 'grab' }"
    :class="[{ 'opacity-50': disabled }, stacked ? 'min-h-16 flex-col justify-center text-center' : 'text-left']"
    @click="$emit('add')"
    @dragstart="$emit('drag', $event)"
    @dragend="$emit('dragend')"
  >
    <KindIcon :icon="icon" class="shrink-0 text-slate-400 transition-colors group-hover/item:text-theme-500" :class="stacked ? 'h-5 w-5' : 'h-4 w-4'" />
    <span class="min-w-0" :class="stacked ? 'w-full break-words' : 'flex-1 truncate'">{{ label }}</span>
    <span v-if="wide && meta" class="shrink-0 font-mono text-[10px] text-slate-400">{{ meta }}</span>
  </button>
</template>

<script setup>
import KindIcon from "./KindIcon.vue";

defineProps({
  icon: { type: String, default: "mdi:shape-outline" },
  label: { type: String, default: "" },
  /** What the item is for, shown on hover. */
  hint: { type: String, default: "" },
  meta: { type: String, default: "" },
  wide: { type: Boolean, default: false },
  stacked: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});
defineEmits(["add", "drag", "dragend"]);
</script>
