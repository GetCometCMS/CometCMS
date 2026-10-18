<template>
  <div>
    <div class="editor-sticky-header flex flex-wrap items-center gap-3">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-bold text-slate-900">{{ t("site.title") }}</h1>
          <span
            v-if="site.data"
            class="badge"
            :class="site.data.settings.enabled ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'"
          >
            {{ site.data.settings.enabled ? t("site.status.live") : t("site.status.off") }}
          </span>
        </div>
        <p class="mt-0.5 text-sm text-slate-500">{{ t("site.description") }}</p>
      </div>

      <a
        v-if="site.data?.settings.enabled"
        :href="site.data.site_url + '/'"
        target="_blank"
        rel="noopener"
        class="btn-secondary"
      >
        <Icon icon="mdi:open-in-new" class="h-4 w-4" />
        {{ t("site.openWebsite") }}
      </a>
      <router-link :to="DESIGN_PATH" class="btn-secondary">
        <Icon icon="mdi:palette-outline" class="h-4 w-4" />
        {{ t("site.design.open") }}
      </router-link>
      <!-- Forms are saved one at a time in their own tab. -->
      <button
        v-if="site.draft && activeTab !== 'forms'"
        type="button"
        class="btn-primary relative"
        :disabled="site.saving || !isDirty"
        @click="save"
      >
        {{ site.saving ? t("common.saving") : t("site.save") }}
        <span
          v-if="isDirty && !site.saving"
          class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-white"
        ></span>
      </button>
    </div>

    <div v-if="site.loading && !site.draft" class="flex justify-center py-16">
      <LoadingSpinner />
    </div>

    <div v-else-if="site.error && !site.draft" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
      {{ site.error }}
    </div>

    <template v-else-if="site.draft">
      <div
        v-if="!site.live"
        class="mb-6 flex flex-col gap-3 rounded-xl border border-theme-200 bg-theme-50 px-4 py-3 text-sm text-theme-900 sm:flex-row sm:items-center"
      >
        <Icon icon="mdi:information-outline" class="h-5 w-5 shrink-0 text-theme-600" />
        <p class="flex-1">{{ t("site.offNotice") }}</p>
        <router-link v-if="activeTab !== 'overview'" to="/page-builder/overview" class="btn-secondary btn-sm shrink-0">
          {{ t("site.chooseHomepage") }}
        </router-link>
        <router-link to="/content-types" class="btn-secondary btn-sm shrink-0">
          {{ t("site.enablePages") }}
        </router-link>
      </div>

      <div
        v-if="Object.keys(site.fieldErrors).length > 0"
        class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        <p class="font-medium">{{ t("site.saveErrors") }}</p>
        <ul class="mt-1 list-disc pl-5">
          <li v-for="(message, key) in site.fieldErrors" :key="key">{{ message }}</li>
        </ul>
      </div>

      <TabNavigation
        class="mb-6"
        :items="tabs"
        :model-value="activeTab"
        :aria-label="t('site.navigation')"
      />

      <SiteOverviewPanel v-if="activeTab === 'overview'" />
      <SiteSeoPanel v-else-if="activeTab === 'seo'" />
      <SiteFormsPanel v-else-if="activeTab === 'forms'" />
      <SiteMenusPanel v-else />
    </template>

    <ConfirmModal
      v-model="leavePromptOpen"
      :title="t('unsaved.title')"
      :message="t('unsaved.message')"
      :confirm-label="t('unsaved.leave')"
      :cancel-label="t('unsaved.stay')"
      variant="warning"
      @confirm="leave"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted } from "vue";
import { useRoute } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import TabNavigation from "../components/TabNavigation.vue";
import SiteOverviewPanel from "../components/site/SiteOverviewPanel.vue";
import SiteSeoPanel from "../components/site/SiteSeoPanel.vue";
import SiteMenusPanel from "../components/site/SiteMenusPanel.vue";
import SiteFormsPanel from "../components/site/SiteFormsPanel.vue";
import { useUnsavedChangesGuard } from "../composables/useUnsavedChangesGuard.js";
import { useSiteStore } from "../stores/site.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

const route = useRoute();
const site = useSiteStore();
const toast = useToastStore();
const { t } = useI18n();
/** The design is edited and saved in the builder; this screen owns settings and menus. */
const PARTS = ["settings", "menus"];
const DESIGN_PATH = "/page-builder/builder/region/header?settings=design";
const isDirty = computed(() => site.dirtyParts.some((part) => PARTS.includes(part)));
const { leavePromptOpen, confirmLeave } = useUnsavedChangesGuard(isDirty);

const validTabs = new Set(["overview", "seo", "menus", "forms"]);
const activeTab = computed(() => (validTabs.has(route.params.tab) ? route.params.tab : "overview"));
const tabs = computed(() => [
  { value: "overview", label: t("site.tab.overview"), icon: "mdi:cog-outline", to: "/page-builder/overview" },
  { value: "seo", label: t("site.tab.seo"), icon: "mdi:magnify", to: "/page-builder/seo" },
  { value: "menus", label: t("site.tab.menus"), icon: "mdi:menu", to: "/page-builder/menus" },
  { value: "forms", label: t("site.tab.forms"), icon: "mdi:form-select", to: "/page-builder/forms" },
]);

async function save() {
  try {
    await site.save({ parts: PARTS });
    toast.success(t("site.saved"));
  } catch (err) {
    toast.error(err.message);
  }
}

function leave() {
  site.resetDraft();
  confirmLeave();
}

function onKeydown(event) {
  if (event.target?.closest?.('[role="dialog"], [role="alertdialog"]')) return;
  if ((event.ctrlKey || event.metaKey) && event.key === "s") {
    event.preventDefault();
    if (isDirty.value && !site.saving) save();
  }
}

function onWorkspaceChanged() {
  site.invalidate();
  site.load().catch(() => {});
}

onMounted(() => {
  // Tabs are separate routes; the draft survives switching between them.
  site.load().catch(() => {});
  window.addEventListener("keydown", onKeydown);
  window.addEventListener("cometcms:workspace-changed", onWorkspaceChanged);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  window.removeEventListener("cometcms:workspace-changed", onWorkspaceChanged);
});
</script>
