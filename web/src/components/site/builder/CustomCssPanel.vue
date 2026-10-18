<template>
  <div class="flex h-full flex-col">
    <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
      <Icon icon="mdi:language-css3" class="h-5 w-5 shrink-0 text-theme-600" />
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold text-slate-900">{{ t("site.theme.customCss") }}</h2>
        <p class="truncate text-xs text-slate-500">{{ t("site.builder.customCssPanelHint") }}</p>
      </div>
      <button type="button" class="panel-action" :title="t('site.builder.closePanel')" @click="emit('close')">
        <Icon icon="mdi:chevron-double-left" class="h-4 w-4" />
        <span class="sr-only">{{ t("site.builder.closePanel") }}</span>
      </button>
    </div>

    <p v-if="!theme && site.error" role="alert" class="m-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ site.error }}</p>
    <div v-else-if="!theme" class="flex flex-1 items-center justify-center"><LoadingSpinner /></div>

    <div v-else class="flex min-h-0 flex-1 flex-col gap-3 p-3">
      <p class="text-xs text-slate-500">
        {{ site.data.can_edit_css ? t("site.theme.customCssHint") : t("site.theme.customCssAdminOnly") }}
      </p>
      <CssCodeEditor
        v-model="theme.custom_css"
        class="flex-1"
        :disabled="!site.data.can_edit_css"
        :label="t('site.theme.customCss')"
        placeholder=".c-site-name { letter-spacing: 0.05em; }"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import CssCodeEditor from "../../CssCodeEditor.vue";
import LoadingSpinner from "../../LoadingSpinner.vue";
import { useSiteStore } from "../../../stores/site.js";
import { useI18n } from "../../../i18n/index.js";

/** The site's own stylesheet, part of the design and saved with it. */
const emit = defineEmits(["close"]);

const site = useSiteStore();
const { t } = useI18n();
const theme = computed(() => site.draft?.theme ?? null);
</script>

<style scoped>
.panel-action {
  display: inline-flex;
  height: 1.75rem;
  width: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.375rem;
  color: rgb(var(--color-slate-500));
}
.panel-action:hover {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
</style>
