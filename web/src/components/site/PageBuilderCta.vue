<template>
  <section
    class="relative overflow-hidden"
    :class="card ? 'card p-6' : 'rounded-xl border border-slate-200 bg-slate-50 p-5'"
    :aria-labelledby="headingId"
  >
    <!-- Soft glow behind the illustration. -->
    <div
      class="pointer-events-none absolute -right-24 -top-24 hidden h-96 w-96 rounded-full bg-theme-500/10 blur-3xl lg:block"
      aria-hidden="true"
    ></div>

    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center">
      <div class="min-w-0 flex-1 space-y-5">
        <div class="flex items-start gap-4">
          <div
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-theme-500 to-theme-700 text-white shadow-md shadow-theme-500/25"
          >
            <Icon :icon="icon" class="h-6 w-6" />
          </div>
          <div class="min-w-0">
            <h2 :id="headingId" class="text-base font-semibold text-slate-900">{{ title }}</h2>
            <p v-if="description" :id="hintId" class="mt-1 max-w-xl text-sm text-slate-500">{{ description }}</p>
          </div>
        </div>

        <ul v-if="features" class="grid grid-cols-2 gap-x-4 gap-y-4 xl:grid-cols-4">
          <li
            v-for="(feature, index) in FEATURES"
            :key="feature.key"
            class="min-w-0"
            :class="{ 'xl:border-l xl:border-slate-200 xl:pl-4': index > 0 }"
          >
            <Icon :icon="feature.icon" class="h-5 w-5 text-theme-600" />
            <p class="mt-2 text-sm font-medium text-slate-800">{{ t(`site.builderCta.${feature.key}`) }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ t(`site.builderCta.${feature.key}Hint`) }}</p>
          </li>
        </ul>

        <div class="flex flex-wrap items-center gap-2">
          <slot name="actions">
            <router-link
              v-if="to"
              :to="to"
              class="btn-primary group"
              :aria-describedby="description ? hintId : undefined"
            >
              <Icon :icon="icon" class="h-4 w-4" />
              {{ actionLabel }}
              <Icon icon="mdi:chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
            </router-link>
          </slot>
        </div>
      </div>

      <!-- A sketch of the builder: element rail, canvas and an element being dropped. -->
      <div
        v-if="preview"
        class="hidden w-full max-w-sm shrink-0 select-none rounded-xl border border-slate-200 bg-slate-50 shadow-sm lg:block"
        aria-hidden="true"
      >
        <div class="flex gap-1.5 border-b border-slate-200 px-3 py-2">
          <span v-for="dot in 3" :key="dot" class="h-2 w-2 rounded-full bg-slate-300"></span>
        </div>
        <div class="flex">
          <div class="flex flex-col gap-2 border-r border-slate-200 p-2.5">
            <span
              v-for="item in RAIL"
              :key="item"
              class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-slate-100 text-theme-500"
            >
              <Icon :icon="item" class="h-4 w-4" />
            </span>
          </div>
          <div class="relative flex-1 space-y-3 p-3">
            <div class="relative flex h-24 items-end justify-center overflow-hidden rounded-md border-2 border-dashed border-theme-400/70 bg-theme-500/5">
              <span class="absolute right-5 top-3 h-4 w-4 rounded-full bg-slate-300"></span>
              <Icon icon="mdi:image-filter-hdr" class="-mb-3 h-20 w-20 text-slate-200" />
            </div>
            <div class="space-y-1.5">
              <span class="block h-1.5 w-11/12 rounded-full bg-slate-200"></span>
              <span class="block h-1.5 w-3/4 rounded-full bg-slate-200"></span>
            </div>
            <span class="block h-4 w-16 rounded bg-theme-500"></span>
            <div class="grid grid-cols-3 gap-2">
              <span v-for="cell in 3" :key="cell" class="flex h-10 flex-col justify-center gap-1 rounded border border-slate-200 bg-slate-100 px-2">
                <span class="block h-1 w-3/4 rounded-full bg-slate-200"></span>
                <span class="block h-1 w-1/2 rounded-full bg-slate-200"></span>
              </span>
            </div>
            <!-- The element being dragged onto the canvas. -->
            <span class="absolute -left-4 top-14 flex h-10 w-12 items-center justify-center rounded-md border border-slate-300 bg-slate-100 text-theme-500 shadow-lg">
              <Icon icon="mdi:image-outline" class="h-5 w-5" />
              <Icon icon="mdi:cursor-default" class="absolute -bottom-3 -right-2 h-5 w-5 text-slate-700" />
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useId } from "vue";
import { Icon } from "@iconify/vue";
import { useI18n } from "../../i18n/index.js";

const FEATURES = [
  { key: "dragDrop", icon: "mdi:cursor-move" },
  { key: "layouts", icon: "mdi:view-dashboard-outline" },
  { key: "preview", icon: "mdi:eye-outline" },
  { key: "reusable", icon: "mdi:layers-outline" },
];
const RAIL = ["mdi:image-outline", "mdi:format-text", "mdi:view-dashboard-outline", "mdi:format-align-left"];

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: "" },
  /** Builder route for the default action; leave empty to supply #actions. */
  to: { type: [String, Object], default: null },
  actionLabel: { type: String, default: "" },
  icon: { type: String, default: "mdi:pencil-ruler" },
  /** Show the feature highlights. */
  features: { type: Boolean, default: true },
  /** Show the builder sketch on wide screens. */
  preview: { type: Boolean, default: true },
  /** A standalone card; otherwise an inset panel inside another card. */
  card: { type: Boolean, default: true },
});

const { t } = useI18n();
const headingId = `builder-cta-${useId()}`;
const hintId = `${headingId}-hint`;
</script>
