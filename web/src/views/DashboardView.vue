<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-900">
        {{ t("dashboard.title") }}
      </h1>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.8fr)]">
        <!-- Recent Activity -->
        <ActivityFeed />

        <!-- Status -->
        <section class="card p-5">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-900">
              {{ t("dashboard.status") }}
            </h2>
            <router-link
              to="/update"
              class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-theme-300 hover:bg-theme-50/40"
            >
              <Icon icon="mdi:update" class="h-4 w-4 text-slate-500" />
              <span>{{ t("dashboard.stats.checkUpdates") }}</span>
            </router-link>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <component
              :is="item.href ? 'a' : 'div'"
              v-for="item in statusItems"
              :key="item.label"
              :href="item.href"
              :target="item.href ? '_blank' : undefined"
              :rel="item.href ? 'noopener noreferrer' : undefined"
              :class="[
                'rounded-lg border border-slate-200 bg-slate-50/70 p-4',
                item.href
                  ? 'block transition hover:border-theme-300 hover:bg-theme-50/50'
                  : '',
              ]"
            >
              <div class="mb-3 flex items-center gap-2">
                <Icon
                  :icon="item.icon"
                  class="h-5 w-5 shrink-0 text-theme-600"
                />
                <p
                  class="min-w-0 truncate text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                  {{ item.label }}
                </p>
              </div>
              <p class="text-2xl font-bold text-slate-900">
                {{ item.value }}
              </p>
              <p class="mt-1 text-sm text-slate-500">{{ item.caption }}</p>
            </component>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import LoadingSpinner from "../components/LoadingSpinner.vue";
import ActivityFeed from "../components/ActivityFeed.vue";
import { computed, onMounted, ref } from "vue";
import { Icon } from "@iconify/vue";
import { api } from "../api/index.js";
import { useI18n } from "../i18n/index.js";

const loading = ref(true);
const stats = ref({ collections: 0, entries: 0, content_types: 0 });
const appVersion = ref("");
const { t } = useI18n();

const statusItems = computed(() => [
  {
    label: t("dashboard.stats.collections"),
    value: stats.value.collections,
    caption: t("dashboard.stats.totalCollections"),
    icon: "mdi:folder-outline",
  },
  {
    label: t("dashboard.stats.entries"),
    value: stats.value.entries,
    caption: t("dashboard.stats.totalEntries"),
    icon: "mdi:file-document-outline",
  },
  {
    label: t("dashboard.stats.contentTypes"),
    value: stats.value.content_types,
    caption: t("dashboard.stats.totalContentTypes"),
    icon: "mdi:table",
  },
  {
    label: t("dashboard.stats.cmsVersion"),
    value: `v${appVersion.value || "..."}`,
    caption: t("dashboard.stats.currentVersion"),
    icon: "mdi:rocket-launch-outline",
  },
  {
    label: t("dashboard.stats.documentation"),
    value: t("dashboard.stats.openDocs"),
    caption: t("dashboard.stats.docsCaption"),
    icon: "mdi:book-open-page-variant-outline",
    href: "https://andreasjhagen.github.io/CometCMS/",
  },
]);

onMounted(async () => {
  try {
    const [dashboard, app] = await Promise.allSettled([
      api.dashboard(),
      api.appInfo(),
    ]);
    if (dashboard.status === "fulfilled") stats.value = dashboard.value.data;
    if (app.status === "fulfilled") appVersion.value = app.value.data?.version ?? "";
  } finally {
    loading.value = false;
  }
});
</script>
