<template>
  <!-- A divider between groups keeps them apart from the controls they hold. -->
  <section class="group border-t border-slate-200 pt-3 first:border-t-0 first:pt-0">
    <component :is="headingTag">
      <button
        type="button"
        class="flex w-full items-center justify-between gap-2 rounded px-1 py-1 text-left text-[13px] font-semibold text-slate-800 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
        :aria-expanded="open"
        :aria-controls="contentId"
        @click="open = !open"
      >
        <span class="flex min-w-0 flex-1 items-center gap-2">
          <Icon v-if="icon" :icon="icon" class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
          <span>{{ title }}</span>
        </span>
        <Icon
          icon="mdi:chevron-right"
          class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
          :class="open ? 'rotate-90' : ''"
          aria-hidden="true"
        />
      </button>
    </component>
    <div v-show="open" :id="contentId" :class="contentClass">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { useId } from "vue";
import { Icon } from "@iconify/vue";

/** A collapsible group of the builder sidebars (palette categories, style groups). */
defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: "" },
  headingTag: { type: String, default: "h3" },
  contentClass: { type: [String, Array, Object], default: "mt-2" },
});

const open = defineModel("open", { type: Boolean, default: true });
const contentId = `builder-group-${useId()}`;
</script>
