<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <section v-for="region in REGIONS" :key="region.key" class="card flex flex-col p-5">
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600">
            <Icon :icon="region.icon" class="h-5 w-5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <h2 class="font-semibold text-slate-900">{{ t(`site.region.${region.key}`) }}</h2>
              <span class="badge" :class="site.data.regions[region.key] ? 'bg-theme-100 text-theme-700' : 'bg-slate-100 text-slate-600'">
                {{ site.data.regions[region.key] ? t("site.pages.customized") : t("site.pages.default") }}
              </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ t(`site.region.${region.key}Hint`) }}</p>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <router-link :to="`/website/builder/region/${region.key}`" class="btn-primary btn-sm">
            <Icon icon="mdi:pencil-ruler" class="h-4 w-4" />
            {{ t("site.region.edit") }}
          </router-link>
          <button
            v-if="site.data.regions[region.key]"
            type="button"
            class="btn-secondary btn-sm"
            @click="resetTarget = region.key"
          >
            {{ t("site.region.reset") }}
          </button>
        </div>
      </section>
    </div>

    <div class="card overflow-hidden">
      <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
        <span class="text-sm font-semibold text-slate-700">{{ t("site.preview.title") }}</span>
        <DeviceToggle v-model="device" class="ml-auto" />
      </div>
      <div class="h-[60vh] bg-slate-100 p-3">
        <SitePreviewFrame ref="previewRef" :body="previewBody" :device="device" :title="t('site.preview.title')" />
      </div>
    </div>

    <ConfirmModal
      :model-value="resetTarget !== ''"
      :title="t('site.region.resetTitle')"
      :message="t('site.region.resetMessage')"
      :confirm-label="t('site.region.reset')"
      variant="warning"
      :loading="resetting"
      @update:model-value="(open) => { if (!open) resetTarget = '' }"
      @confirm="reset"
    />
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../ConfirmModal.vue";
import DeviceToggle from "./DeviceToggle.vue";
import SitePreviewFrame from "./SitePreviewFrame.vue";
import { api } from "../../api/index.js";
import { useSiteStore } from "../../stores/site.js";
import { useToastStore } from "../../stores/toast.js";
import { useI18n } from "../../i18n/index.js";

const REGIONS = [
  { key: "header", icon: "mdi:page-layout-header" },
  { key: "footer", icon: "mdi:page-layout-footer" },
];

const site = useSiteStore();
const toast = useToastStore();
const { t } = useI18n();
const device = ref("desktop");
const previewRef = ref(null);
const resetTarget = ref("");
const resetting = ref(false);

const previewBody = computed(() => ({
  target: "page",
  path: "/",
  theme: site.draft.theme,
  menus: site.draft.menus,
}));

async function reset() {
  const region = resetTarget.value;
  resetting.value = true;
  try {
    await api.site.resetRegion(region);
    site.markCustomized("region", region, null, false);
    previewRef.value?.refresh();
    toast.success(t("site.region.resetDone"));
  } catch (err) {
    toast.error(err.message);
  } finally {
    resetting.value = false;
    resetTarget.value = "";
  }
}
</script>
