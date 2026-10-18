<template>
  <router-link
    :to="to"
    class="card group flex flex-col gap-4 p-6 transition-colors hover:border-slate-300 sm:flex-row sm:items-center sm:gap-6"
    :aria-labelledby="`${headingId} ${actionId}`"
    :aria-describedby="description ? hintId : undefined"
  >
    <div class="flex min-w-0 flex-1 items-start gap-3">
      <span
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-colors group-hover:text-theme-600 group-focus-visible:text-theme-600"
        aria-hidden="true"
      >
        <Icon :icon="icon" class="h-5 w-5" />
      </span>
      <div class="min-w-0">
        <h2 :id="headingId" class="text-sm font-semibold text-slate-700">{{ title }}</h2>
        <p v-if="description" :id="hintId" class="mt-1 text-sm text-slate-500">{{ description }}</p>
      </div>
    </div>

    <span class="btn-secondary shrink-0 justify-center group-hover:bg-slate-100 group-focus-visible:bg-slate-100">
      <span :id="actionId">{{ actionLabel }}</span>
      <Icon icon="mdi:arrow-right" class="h-4 w-4" aria-hidden="true" />
    </span>
  </router-link>
</template>

<script setup>
import { useId } from "vue";
import { Icon } from "@iconify/vue";

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: "" },
  to: { type: [String, Object], required: true },
  actionLabel: { type: String, required: true },
  icon: { type: String, default: "mdi:file-document-edit-outline" },
});

const headingId = `builder-cta-${useId()}`;
const hintId = `${headingId}-hint`;
const actionId = `${headingId}-action`;
</script>
