<template>
  <div class="flex h-screen flex-col bg-slate-100">
    <!-- Top bar -->
    <header class="flex h-14 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3">
      <router-link :to="backTo" class="rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900" :title="t('site.builder.back')">
        <Icon icon="mdi:arrow-left" class="h-5 w-5" />
        <span class="sr-only">{{ t("site.builder.back") }}</span>
      </router-link>

      <div class="min-w-0">
        <p class="truncate text-sm font-semibold leading-tight text-slate-900">{{ heading }}</p>
        <p class="truncate text-xs leading-tight text-slate-500">
          {{ subheading }}
          <span v-if="!customized" class="ml-1 rounded bg-slate-100 px-1.5 py-px text-[10px] font-medium text-slate-500">{{ t("site.pages.default") }}</span>
        </p>
      </div>

      <nav v-if="variants.length > 1" class="ml-3 hidden rounded-lg border border-slate-200 bg-slate-50 p-0.5 md:flex" :aria-label="t('site.builder.variants')">
        <button
          v-for="item in variants"
          :key="item"
          type="button"
          class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
          :class="item === variant ? 'bg-white text-theme-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900'"
          @click="goToVariant(item)"
        >
          {{ t(`site.variant.${item}`) }}
        </button>
      </nav>

      <div class="ml-auto flex items-center gap-2">
        <select
          v-if="target === 'layout' && variant !== 'index' && !builder.type.value?.singleton && samples.length"
          v-model="sampleId"
          class="form-select hidden max-w-[14rem] rounded-lg border-slate-300 py-1 text-xs lg:block"
          :aria-label="t('site.builder.sampleEntry')"
          :title="t('site.builder.sampleEntry')"
        >
          <option v-for="sample in samples" :key="sample.id" :value="sample.id">
            {{ sample.title || sample.id }}{{ sample.status !== "published" ? ` (${t(`status.${sample.status}`)})` : "" }}
          </option>
        </select>

        <select
          v-if="locales.length > 1"
          v-model="locale"
          class="form-select rounded-lg border-slate-300 py-1 text-xs"
          :aria-label="t('site.builder.language')"
        >
          <option v-for="item in locales" :key="item" :value="item">{{ item.toUpperCase() }}</option>
        </select>

        <router-link
          v-if="contentLink"
          :to="contentLink"
          class="topbar-action"
          :title="t('site.builder.editContent')"
        >
          <Icon icon="mdi:file-document-edit-outline" class="h-5 w-5" />
          <span class="sr-only">{{ t("site.builder.editContent") }}</span>
        </router-link>
        <a
          v-if="liveUrl"
          :href="liveUrl"
          target="_blank"
          rel="noopener"
          class="topbar-action"
          :title="`${t('site.builder.viewLive')} – ${liveUrl}`"
        >
          <Icon icon="mdi:open-in-new" class="h-5 w-5" />
          <span class="sr-only">{{ t("site.builder.viewLive") }}</span>
        </a>
        <span v-else-if="liveHint" class="topbar-action cursor-default opacity-35" :title="liveHint">
          <Icon icon="mdi:open-in-new" class="h-5 w-5" />
        </span>

        <DeviceToggle v-model="device" />

        <div class="flex items-center">
          <button type="button" class="topbar-action" :disabled="!builder.canUndo.value" :title="`${t('site.builder.undo')} (Ctrl+Z)`" @click="builder.undo()">
            <Icon icon="mdi:undo" class="h-5 w-5" />
          </button>
          <button type="button" class="topbar-action" :disabled="!builder.canRedo.value" :title="`${t('site.builder.redo')} (Ctrl+Shift+Z)`" @click="builder.redo()">
            <Icon icon="mdi:redo" class="h-5 w-5" />
          </button>
        </div>

        <button v-if="customized" type="button" class="btn-secondary btn-sm hidden sm:inline-flex" @click="resetOpen = true">
          {{ t("site.builder.reset") }}
        </button>

        <SiteHistory
          :title="t('site.history.layoutTitle')"
          :fetch-revisions="fetchRevisions"
          :restore-revision="restoreRevision"
          :dirty="builder.isDirty.value"
          :busy="loading || saving || resetting"
          @restored="onRevisionRestored"
        />

        <button type="button" class="btn-primary relative" :disabled="saving || loading || !builder.isDirty.value" @click="save">
          {{ saving ? t("common.saving") : t("site.builder.save") }}
          <span v-if="builder.isDirty.value && !saving" class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-white"></span>
        </button>
      </div>
    </header>

    <div v-if="loadError" class="m-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ loadError }}</div>

    <div v-else-if="loading" class="flex flex-1 items-center justify-center"><LoadingSpinner /></div>

    <div v-else class="flex min-h-0 flex-1">
      <!-- Palette & outline -->
      <aside class="flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white xl:w-72">
        <div class="border-b border-slate-200 p-2">
          <SegmentedControl
            v-model="leftTab"
            :options="[
              { value: 'add', label: t('site.builder.add'), icon: 'mdi:plus-box-outline', showLabel: true },
              { value: 'outline', label: t('site.builder.outline'), icon: 'mdi:file-tree-outline', showLabel: true },
            ]"
          />
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto">
          <BuilderPalette v-if="leftTab === 'add'" :with-fields="target === 'layout'" />
          <BuilderOutline v-else />
        </div>
      </aside>

      <!-- Canvas -->
      <main class="min-w-0 flex-1 p-3 xl:p-5">
        <BuilderCanvas ref="canvasRef" :body="previewBody" :device="device" @keydown="onKeydown" />
      </main>

      <!-- Inspector -->
      <aside class="w-72 shrink-0 border-l border-slate-200 bg-white xl:w-80">
        <BuilderInspector />
      </aside>
    </div>

    <BuilderContextMenu />

    <ConfirmModal
      v-model="leavePromptOpen"
      :title="t('unsaved.title')"
      :message="t('unsaved.message')"
      :confirm-label="t('unsaved.leave')"
      :cancel-label="t('unsaved.stay')"
      variant="warning"
      @confirm="confirmLeave"
    />
    <ConfirmModal
      :model-value="pendingVariant !== ''"
      :title="t('unsaved.title')"
      :message="t('unsaved.message')"
      :confirm-label="t('unsaved.leave')"
      :cancel-label="t('unsaved.stay')"
      variant="warning"
      @update:model-value="(open) => { if (!open) pendingVariant = '' }"
      @confirm="switchVariant(pendingVariant)"
    />
    <ConfirmModal
      v-model="resetOpen"
      :title="t('site.builder.resetTitle')"
      :message="t('site.builder.resetMessage')"
      :confirm-label="t('site.builder.reset')"
      variant="warning"
      :loading="resetting"
      @confirm="reset"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import DeviceToggle from "../components/site/DeviceToggle.vue";
import SegmentedControl from "../components/site/SegmentedControl.vue";
import BuilderCanvas from "../components/site/builder/BuilderCanvas.vue";
import BuilderContextMenu from "../components/site/builder/BuilderContextMenu.vue";
import BuilderInspector from "../components/site/builder/BuilderInspector.vue";
import BuilderOutline from "../components/site/builder/BuilderOutline.vue";
import BuilderPalette from "../components/site/builder/BuilderPalette.vue";
import SiteHistory from "../components/site/SiteHistory.vue";
import { api } from "../api/index.js";
import { createSiteBuilder } from "../composables/useSiteBuilder.js";
import { useUnsavedChangesGuard } from "../composables/useUnsavedChangesGuard.js";
import { useSiteStore } from "../stores/site.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

const route = useRoute();
const router = useRouter();
const site = useSiteStore();
const toast = useToastStore();
const { t } = useI18n();
const builder = createSiteBuilder({ t });
const { leavePromptOpen, confirmLeave, bypass } = useUnsavedChangesGuard(builder.isDirty);

const target = route.params.target;
const name = String(route.params.name);
const variant = String(route.params.variant || "detail");

const loading = ref(true);
const loadError = ref("");
const saving = ref(false);
const resetting = ref(false);
const resetOpen = ref(false);
const customized = ref(false);
const samples = ref([]);
const sampleId = ref("");
const locales = ref([]);
const locale = ref("");
const device = ref("desktop");
const leftTab = ref("add");
const pendingVariant = ref("");
const canvasRef = ref(null);
const indexUrl = ref(null);
const homeUrl = ref(null);
const entryUrl = ref(null);

/** The entry the preview shows: the chosen sample, or the single page itself. */
const previewEntry = computed(() => (builder.type.value?.singleton ? name : sampleId.value));

/** The live page of what is being edited, when there is one. */
const liveUrl = computed(() => {
  if (target === "region") return homeUrl.value;
  if (variant === "index") return indexUrl.value;
  if (variant === "card") return null;
  return entryUrl.value;
});
const liveHint = computed(() => (target === "layout" && variant === "detail" && previewEntry.value ? t("site.builder.notLive") : ""));
const contentLink = computed(() => {
  if (target !== "layout" || variant === "index" || !previewEntry.value) return null;
  return builder.type.value?.singleton ? `/content/${name}` : `/content/${name}/${previewEntry.value}`;
});

watch(previewEntry, async (entry) => {
  entryUrl.value = null;
  if (target !== "layout" || !entry) return;
  try {
    entryUrl.value = (await api.site.links(name, entry)).data?.url ?? null;
  } catch {
    entryUrl.value = null;
  }
});

const variants = computed(() => {
  if (target !== "layout" || !builder.type.value) return [];
  return builder.type.value.singleton ? ["detail"] : ["detail", "card", "index"];
});
// Opened from an entry: go back there. Only internal content URLs are followed.
const backTo = computed(() => {
  const back = typeof route.query.back === "string" ? route.query.back : "";
  if (back.startsWith("/content/")) return back;
  return target === "region" ? "/website/header-footer" : `/content-types/${name}/edit#website`;
});
const heading = computed(() => (target === "region" ? t(`site.region.${name}`) : builder.type.value?.label ?? name));
const subheading = computed(() => (target === "region" ? t("site.builder.regionSubtitle") : t(`site.variant.${variant}`)));

const previewBody = computed(() => {
  if (loading.value) return null;
  return target === "region"
    ? { target: "region", region: name, nodes: builder.nodes.value, locale: locale.value }
    : { target: "layout", type: name, variant, nodes: builder.nodes.value, entry: sampleId.value, locale: locale.value };
});

async function load() {
  loading.value = true;
  loadError.value = "";

  try {
    const res = target === "region"
      ? await api.site.region(name)
      : await api.site.layout(name, variant, typeof route.query.entry === "string" ? route.query.entry : "");
    const data = res.data;
    builder.spec.value = data.spec;
    builder.types.value = data.types ?? [];
    builder.menus.value = data.menus ?? [];
    builder.type.value = data.type ?? null;
    samples.value = data.samples ?? [];
    const requested = typeof route.query.entry === "string" ? route.query.entry : "";
    sampleId.value = samples.value.some((sample) => sample.id === requested)
      ? requested
      : samples.value.find((sample) => sample.status === "published")?.id ?? samples.value[0]?.id ?? "";
    indexUrl.value = data.index_url ?? null;
    homeUrl.value = data.home_url ?? null;
    locales.value = data.locales ?? [];
    locale.value = data.default_locale ?? "";
    customized.value = !!data.customized;
    builder.load(data.nodes);
  } catch (err) {
    loadError.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (saving.value || !builder.isDirty.value) return;
  saving.value = true;

  try {
    const nodes = builder.nodes.value;
    const res = target === "region"
      ? await api.site.saveRegion(name, nodes)
      : await api.site.saveLayout(name, variant, nodes);
    builder.markSaved(res.data.nodes);
    customized.value = true;
    site.markCustomized(target, name, variant, true);
    toast.success(t("site.builder.saved"));
  } catch (err) {
    toast.error(err.message);
  } finally {
    saving.value = false;
  }
}

async function reset() {
  resetting.value = true;

  try {
    const res = target === "region" ? await api.site.resetRegion(name) : await api.site.resetLayout(name, variant);
    builder.mutate((tree) => {
      tree.splice(0, tree.length, ...res.data.nodes);
    });
    builder.markSaved(res.data.nodes);
    builder.select(null);
    customized.value = false;
    site.markCustomized(target, name, variant, false);
    toast.success(t("site.builder.resetDone"));
  } catch (err) {
    toast.error(err.message);
  } finally {
    resetting.value = false;
    resetOpen.value = false;
  }
}

function fetchRevisions() {
  return target === "region" ? api.site.regionRevisions(name) : api.site.layoutRevisions(name, variant);
}

function restoreRevision(id) {
  return target === "region" ? api.site.restoreRegionRevision(name, id) : api.site.restoreLayoutRevision(name, variant, id);
}

function onRevisionRestored(data) {
  builder.load(data.nodes);
  customized.value = data.customized;
  site.markCustomized(target, name, variant, data.customized);
}

function goToVariant(next) {
  if (next === variant) return;
  if (builder.isDirty.value) {
    pendingVariant.value = next;
    return;
  }
  switchVariant(next);
}

function switchVariant(next) {
  pendingVariant.value = "";
  bypass();
  router.push({ path: `/website/builder/layout/${name}/${next}`, query: route.query });
}

function onKeydown(event) {
  if (event.target?.closest?.('[role="dialog"], [role="alertdialog"]')) return;
  const element = event.target;
  const editing = ["INPUT", "TEXTAREA", "SELECT"].includes(element?.tagName) || element?.isContentEditable;
  const modifier = event.ctrlKey || event.metaKey;
  const key = event.key.toLowerCase();

  if (modifier && key === "s") {
    event.preventDefault();
    save();
    return;
  }

  if (editing) return;

  // Shift+F10 and the context-menu key open the menu for the selection.
  if ((event.key === "ContextMenu" || (event.shiftKey && event.key === "F10")) && builder.selectedId.value) {
    event.preventDefault();
    const point = canvasRef.value?.positionFor(builder.selectedId.value) ?? { x: window.innerWidth / 2, y: 120 };
    builder.openContextMenu(builder.selectedId.value, point.x, point.y);
    return;
  }

  const hasTextSelection = !!window.getSelection()?.toString();

  if (modifier && key === "c" && builder.selectedId.value && !hasTextSelection) {
    builder.copy();
  } else if (modifier && key === "v" && builder.clipboard.value) {
    event.preventDefault();
    builder.paste();
  } else if (modifier && key === "z") {
    event.preventDefault();
    if (event.shiftKey) builder.redo();
    else builder.undo();
  } else if (modifier && key === "y") {
    event.preventDefault();
    builder.redo();
  } else if (modifier && key === "d" && builder.selectedId.value) {
    event.preventDefault();
    builder.duplicate();
  } else if ((event.key === "Delete" || event.key === "Backspace") && builder.selectedId.value) {
    event.preventDefault();
    builder.remove();
  } else if (event.key === "Escape") {
    if (builder.contextMenu.value) builder.closeContextMenu();
    else builder.select(null);
  } else if (event.altKey && (event.key === "ArrowUp" || event.key === "ArrowDown") && builder.selectedId.value) {
    event.preventDefault();
    builder.shift(builder.selectedId.value, event.key === "ArrowUp" ? -1 : 1);
  }
}

onMounted(() => {
  load();
  // Swatches and token hints show the saved theme; refresh unless edits are pending.
  site.load({ force: !site.isDirty }).catch(() => {});
  window.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));
</script>

<style scoped>
.topbar-action {
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  color: rgb(71 85 105);
}
.topbar-action:hover:not(:disabled) {
  background: rgb(241 245 249);
  color: rgb(15 23 42);
}
.topbar-action:disabled {
  opacity: 0.35;
}
</style>
