<template>
  <span
    v-if="hints.length"
    class="inline-flex shrink-0"
    @mouseenter="open = true"
    @mouseleave="open = false"
    @focusin="open = true"
    @focusout="open = false"
    @keydown.esc.stop="open = false"
  >
    <button
      type="button"
      :aria-label="label"
      :aria-describedby="open ? helpId : undefined"
      class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-theme-50 hover:text-theme-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
      @click="open = true"
    >
      <Icon icon="mdi:help-circle-outline" class="h-4 w-4" aria-hidden="true" />
    </button>
    <span v-if="open" class="absolute inset-x-3 top-[calc(100%-1.5rem)] pt-8">
      <span :id="helpId" role="tooltip" class="block space-y-2 rounded-xl border border-slate-700 bg-slate-900 px-3.5 py-3 text-xs font-normal leading-relaxed text-slate-100 shadow-xl shadow-slate-900/20">
        <span v-for="hint in hints" :key="hint" class="block">{{ hint }}</span>
      </span>
    </span>
  </span>
</template>

<script setup>
import { ref, useId } from "vue";
import { Icon } from "@iconify/vue";

/** Place inside a relative header so help spans the panel width. */
defineProps({
  label: { type: String, required: true },
  hints: { type: Array, default: () => [] },
});

const helpId = useId();
const open = ref(false);
</script>
