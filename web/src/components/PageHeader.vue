<template>
  <header class="page-header">
    <div class="min-w-0 flex-1">
      <nav
        v-if="back || breadcrumbs.length"
        class="page-eyebrow"
        :aria-label="t('app.breadcrumbs')"
      >
        <router-link v-if="back" :to="back.to">
          <Icon icon="mdi:arrow-left" class="h-4 w-4" aria-hidden="true" />
          {{ back.label }}
        </router-link>
        <template v-for="(crumb, index) in breadcrumbs" :key="index">
          <Icon
            v-if="index > 0 || back"
            icon="mdi:chevron-right"
            class="h-4 w-4 text-slate-300"
            aria-hidden="true"
          />
          <router-link v-if="crumb.to" :to="crumb.to">{{ crumb.label }}</router-link>
          <span v-else class="text-slate-700">{{ crumb.label }}</span>
        </template>
      </nav>

      <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
        <span
          v-if="icon"
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600 ring-1 ring-inset ring-theme-600/10"
          aria-hidden="true"
        >
          <Icon :icon="icon" class="h-5 w-5" />
        </span>
        <h1 class="page-title min-w-0 truncate">
          <slot name="title">{{ title }}</slot>
        </h1>
        <slot name="badges" />
      </div>

      <p v-if="description || $slots.description" class="page-description">
        <slot name="description">{{ description }}</slot>
      </p>
    </div>

    <div v-if="$slots.actions" class="page-header-actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { useI18n } from "../i18n/index.js";

defineProps({
  title: { type: String, default: "" },
  description: { type: String, default: "" },
  icon: { type: String, default: "" },
  // { to, label } rendered as a "← label" link above the title.
  back: { type: Object, default: null },
  // [{ label, to? }]
  breadcrumbs: { type: Array, default: () => [] },
});

const { t } = useI18n();
</script>
