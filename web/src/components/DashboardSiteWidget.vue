<template>
  <section v-if="visible" class="card overflow-hidden" aria-labelledby="dashboard-site-title">
    <div class="flex items-start gap-3 p-5">
      <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600 ring-1 ring-inset ring-theme-600/10">
        <Icon icon="mdi:monitor-edit" class="h-5 w-5" />
      </div>
      <div class="min-w-0 flex-1">
        <h2 id="dashboard-site-title" class="card-title">{{ t("dashboard.site.title") }}</h2>
        <p class="card-description">{{ t("dashboard.site.description") }}</p>
      </div>
    </div>
    <div v-if="status.homepage_editor" class="border-t border-slate-200/80 bg-slate-50/60 px-5 py-3">
      <router-link :to="status.homepage_editor" class="btn-primary w-full">
        <Icon icon="mdi:pencil-outline" class="h-4 w-4" />
        {{ t("dashboard.site.edit") }}
      </router-link>
    </div>
    <div v-else class="mx-5 mb-5 flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
      <Icon icon="mdi:information-outline" class="h-5 w-5 shrink-0" />
      <p class="flex-1">{{ t("dashboard.site.noHomepage") }}</p>
      <router-link to="/page-builder/overview#homepage" class="font-medium underline">{{ t("dashboard.site.chooseHomepage") }}</router-link>
    </div>
  </section>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { useI18n } from "../i18n/index.js";

const props = defineProps({ status: { type: Object, default: null } });
const { t } = useI18n();
const visible = computed(() => props.status?.can_manage && (
  props.status.enabled || Object.values(props.status.types ?? {}).some((type) => type.pages_enabled)
));
</script>
