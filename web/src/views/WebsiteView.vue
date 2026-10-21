<template>
  <div>
    <div class="editor-sticky-header flex flex-wrap items-center gap-3">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h1 class="page-title">{{ t("website.title") }}</h1>
          <span
            v-if="site.data && editsSite"
            class="badge badge-dot"
            :class="site.data.settings.enabled ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/15'"
          >
            {{ site.data.settings.enabled ? t("site.status.live") : t("site.status.off") }}
          </span>
        </div>
        <p class="mt-0.5 text-sm text-slate-500">{{ t("website.description") }}</p>
      </div>

      <a
        v-if="editsSite && site.data?.settings.enabled"
        :href="site.data.site_url + '/'"
        target="_blank"
        rel="noopener"
        class="btn-secondary"
      >
        <Icon icon="mdi:open-in-new" class="h-4 w-4" />
        {{ t("site.openWebsite") }}
      </a>
      <button
        v-if="editsSite && site.draft"
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

    <TabNavigation
      v-if="tabs.length > 1"
      class="mb-6"
      :items="tabs"
      :model-value="activeTab"
      :aria-label="t('website.navigation')"
    />

    <WorkspacesPanel v-if="activeTab === 'workspaces'" />

    <template v-else-if="editsSite">
      <div v-if="site.loading && !site.draft" class="flex justify-center py-16">
        <LoadingSpinner />
      </div>

      <div v-else-if="site.error && !site.draft" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
        {{ site.error }}
      </div>

      <template v-else-if="site.draft">
        <div
          v-if="Object.keys(site.fieldErrors).length > 0"
          class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
          <p class="font-medium">{{ t("site.saveErrors") }}</p>
          <ul class="mt-1 list-disc pl-5">
            <li v-for="(message, key) in site.fieldErrors" :key="key">{{ message }}</li>
          </ul>
        </div>

        <SiteSeoPanel v-if="activeTab === 'seo'" />
        <SiteGeneralPanel v-else />
      </template>
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
import { computed, onBeforeUnmount, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import TabNavigation from "../components/TabNavigation.vue";
import WorkspacesPanel from "../components/WorkspacesPanel.vue";
import SiteGeneralPanel from "../components/site/SiteGeneralPanel.vue";
import SiteSeoPanel from "../components/site/SiteSeoPanel.vue";
import { useUnsavedChangesGuard } from "../composables/useUnsavedChangesGuard.js";
import { useAuthStore } from "../stores/auth.js";
import { useSiteStore } from "../stores/site.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

/** Website-wide settings: identity, homepage, SEO defaults and the workspaces behind them. */
const route = useRoute();
const auth = useAuthStore();
const site = useSiteStore();
const toast = useToastStore();
const { t } = useI18n();
const isDirty = computed(() => site.dirtyParts.includes("settings"));
const { leavePromptOpen, confirmLeave } = useUnsavedChangesGuard(isDirty);

const tabs = computed(() =>
  [
    auth.can("site.manage") && { value: "general", label: t("website.tab.general"), icon: "mdi:cog-outline", to: "/website/general" },
    auth.can("site.manage") && { value: "seo", label: t("website.tab.seo"), icon: "mdi:magnify", to: "/website/seo" },
    auth.can("workspaces.manage") && { value: "workspaces", label: t("website.tab.workspaces"), icon: "mdi:folder-multiple-outline", to: "/website/workspaces" },
  ].filter(Boolean),
);
const activeTab = computed(() =>
  tabs.value.some((tab) => tab.value === route.params.tab) ? route.params.tab : (tabs.value[0]?.value ?? "general"),
);
const editsSite = computed(() => activeTab.value !== "workspaces" && auth.can("site.manage"));

async function save() {
  try {
    await site.save({ parts: ["settings"] });
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
  if (!editsSite.value) return;
  if (event.target?.closest?.('[role="dialog"], [role="alertdialog"]')) return;
  if ((event.ctrlKey || event.metaKey) && event.key === "s") {
    event.preventDefault();
    if (isDirty.value && !site.saving) save();
  }
}

function onWorkspaceChanged() {
  site.invalidate();
  if (editsSite.value) site.load().catch(() => {});
}

// Editing a workspace's languages invalidates the site; reload it when coming back.
watch(
  editsSite,
  (edits) => {
    if (edits) site.load().catch(() => {});
  },
  { immediate: true },
);

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
  window.addEventListener("cometcms:workspace-changed", onWorkspaceChanged);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  window.removeEventListener("cometcms:workspace-changed", onWorkspaceChanged);
});
</script>
