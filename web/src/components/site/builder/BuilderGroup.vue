<template>
  <section class="group">
    <component :is="headingTag">
      <button
        type="button"
        class="flex w-full items-center justify-between gap-2 rounded px-1 py-1 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
        :aria-expanded="open"
        :aria-controls="contentId"
        @click="open = !open"
      >
        {{ title }}
        <!-- Shown while collapsed so the state is visible without hovering. -->
        <Icon
          icon="mdi:chevron-right"
          class="h-4 w-4 shrink-0 transition-[opacity,transform]"
          :class="open ? 'rotate-90 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100' : ''"
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
  headingTag: { type: String, default: "h3" },
  contentClass: { type: [String, Array, Object], default: "mt-2" },
});

const open = defineModel("open", { type: Boolean, default: true });
const contentId = `builder-group-${useId()}`;
</script>
