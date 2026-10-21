<template>
  <div class="builder-shell flex h-screen flex-col bg-slate-100">
    <!-- Top bar -->
    <header class="flex h-14 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-3">
      <router-link :to="backTo" class="icon-btn" :title="t('site.builder.back')">
        <Icon icon="mdi:arrow-left" class="h-[18px] w-[18px]" />
        <span class="sr-only">{{ t("site.builder.back") }}</span>
      </router-link>

      <div class="min-w-40 max-w-72">
        <BuilderPageSwitcher :heading="heading" :target="target" :name="name" />
        <p class="truncate text-xs leading-tight text-slate-500">{{ subheading }}</p>
      </div>

      <nav v-if="variants.length > 1" class="hidden rounded-lg bg-slate-100 p-0.5 md:flex" :aria-label="t('site.builder.variants')">
        <button
          v-for="item in variants"
          :key="item"
          type="button"
          class="flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors"
          :class="item === variant ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          :aria-pressed="item === variant"
          @click="goToVariant(item)"
        >
          <Icon :icon="item === 'index' ? 'mdi:view-grid-outline' : 'mdi:file-document-outline'" class="h-3.5 w-3.5" />
          {{ t(`site.variant.${item}`) }}
        </button>
      </nav>

      <div class="ml-auto flex items-center gap-2">
        <label
          v-if="showsSamples && !builder.type.value?.singleton && samples.length"
          class="hidden items-center gap-1.5 rounded-lg border border-slate-200 bg-white pl-2.5 shadow-xs lg:flex"
          :title="t('site.builder.sampleEntry')"
        >
          <Icon icon="mdi:eye-outline" class="h-4 w-4 shrink-0 text-slate-400" />
          <span class="text-xs font-medium text-slate-500">{{ t("site.builder.previewWith") }}</span>
        <select
          v-model="sampleId"
          :disabled="saving"
          class="max-w-[12rem] border-0 bg-transparent py-1 pl-1 pr-7 text-xs font-medium text-slate-800 focus:ring-0"
          :aria-label="t('site.builder.sampleEntry')"
        >
          <option v-for="sample in samples" :key="sample.id" :value="sample.id">
            {{ sample.title || sample.id }}{{ sample.status !== "published" ? ` (${t(`status.${sample.status}`)})` : "" }}
          </option>
        </select>
        </label>

        <select
          v-if="locales.length > 1"
          v-model="locale"
          :disabled="saving"
          class="form-select rounded-lg border-slate-300 py-1 text-xs"
          :aria-label="t('site.builder.language')"
        >
          <option v-for="item in locales" :key="item" :value="item">{{ item.toUpperCase() }}</option>
        </select>

        <select v-model="builder.editingMode.value" class="form-select rounded-lg border-slate-300 py-1 text-xs" :aria-label="t('localization.mode')">
          <option value="design">{{ t('localization.design') }}</option>
          <option value="translate">{{ t('localization.translate') }}</option>
        </select>
        <DeviceToggle v-model="device" allow-full-width />

        <div class="flex items-center">
          <button type="button" class="topbar-action" :disabled="!builder.canUndo.value" :title="`${t('site.builder.undo')} (Ctrl+Z)`" @click="builder.undo()">
            <Icon icon="mdi:undo" class="h-5 w-5" />
          </button>
          <button type="button" class="topbar-action" :disabled="!builder.canRedo.value" :title="`${t('site.builder.redo')} (Ctrl+Shift+Z)`" @click="builder.redo()">
            <Icon icon="mdi:redo" class="h-5 w-5" />
          </button>
        </div>

        <span
          v-if="saveStatus !== 'saved'"
          class="hidden items-center gap-1.5 whitespace-nowrap text-xs xl:flex"
          :class="saveStatus === 'unsaved' ? 'font-medium text-amber-700' : 'text-slate-500'"
          role="status"
          :title="saveStatus === 'unsaved' ? t('site.builder.status.unsavedHint') : undefined"
        >
          <Icon v-if="saveStatus === 'saving'" icon="mdi:loading" class="h-3.5 w-3.5 animate-spin" />
          <span v-else class="h-2 w-2 rounded-full bg-amber-400"></span>
          {{ t(`site.builder.status.${saveStatus}`) }}
        </span>

        <button type="button" class="topbar-action" :title="`${t('site.builder.help')} (?)`" @click="guideOpen = true">
          <Icon icon="mdi:help-circle-outline" class="h-5 w-5" />
          <span class="sr-only">{{ t("site.builder.help") }}</span>
        </button>

        <SplitButton
          :primary-disabled="saving || loading || (!layoutSavable && !themeDirty && !entryFields.isDirty.value)"
          :menu-label="t('common.moreActions')"
          :items="saveMenuItems"
          @click="save"
        >
          {{ saving ? t("common.saving") : t("site.builder.save") }}
          <template #badge>
            <span v-if="(builder.isDirty.value || themeDirty || entryFields.isDirty.value) && !saving" class="pointer-events-none absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-white"></span>
          </template>
        </SplitButton>
      </div>
    </header>

    <div v-for="scope in ['layout', 'theme'].filter((scope) => loadedRevisions[scope])" :key="scope" role="status" class="flex items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
      <p>{{ t('site.history.loadedBanner', { scope: t(scope === 'theme' ? 'site.history.scope.design' : target === 'component' ? 'site.history.scope.component' : 'site.history.scope.layout'), date: formatRevisionDate(loadedRevisions[scope].revision.created_at) }) }}</p>
      <button type="button" class="btn-secondary btn-sm shrink-0" :disabled="saving" @click="cancelRevision(scope)">{{ t('common.cancel') }}</button>
    </div>
    <p v-if="upgradeRequired" role="status" class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">{{ t("site.binding.upgrade") }}</p>
    <p v-if="builder.hasSyntaxErrors.value" role="alert" class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ t("site.binding.invalidSyntax") }}</p>
    <div v-if="loadError" class="m-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ loadError }}</div>

    <div v-else-if="loading" class="flex flex-1 items-center justify-center"><LoadingSpinner /></div>

    <div v-else ref="builderAreaRef" class="relative isolate flex min-h-0 flex-1 overflow-hidden">

<!-- Panel rail -->
<nav
  class="flex w-[76px] shrink-0 flex-col items-center gap-0.5 overflow-y-auto border-r border-slate-200 bg-white py-2"
  :aria-label="t('site.builder.panels')"
>
  <template v-for="panel in panels" :key="panel.value">
    <!-- Separator before entry fields -->
    <div
      v-if="panel.value === 'fields'"
      class="my-1.5 h-px w-8 shrink-0 bg-slate-200"
      aria-hidden="true"
    ></div>

    <button
      type="button"
      class="rail-action"
      :class="{ 'is-active': panelWindows[panel.value]?.open }"
      :aria-pressed="panelWindows[panel.value]?.open ?? false"
      :aria-label="panel.label"
      :title="t('site.builder.panelWindowHint', { panel: panel.label })"
      @pointerdown="panelLayout.startMove($event, panel.value, true)"
      @click="panelLayout.toggle(panel.value)"
    >
      <Icon :icon="panel.icon" class="h-5 w-5" />
      <span class="rail-label" aria-hidden="true">{{ panel.short }}</span>
      <span
        v-if="panel.dirty"
        class="pointer-events-none absolute right-1 top-1 h-2 w-2 rounded-full bg-amber-400 ring-2 ring-white"
      ></span>
    </button>

    <!-- Separator before design settings -->
    <div
      v-if="(panel.value === 'outline' && !hasEntryFields) || panel.value === 'fields'"
      class="my-1.5 h-px w-8 shrink-0 bg-slate-200"
      aria-hidden="true"
    ></div>
  </template>
</nav>


      <BuilderPanelWindow
        v-for="panel in visiblePanels"
        :key="panel.value"
        :state="panelWindows[panel.value]"
        :limits="panelLayout.limits(panel.value)"
        :title="panel.label"
        :icon="panel.icon"
        @dock="panelLayout.open(panel.value, $event)"
        @toggle-dock="panelLayout.toggleDock(panel.value)"
        @close="panelLayout.close(panel.value)"
        @focus="panelLayout.focus(panel.value)"
        @resize="(width, height) => panelLayout.resize(panel.value, width, height)"
        @move="panelLayout.startMove($event, panel.value)"
        @resize-start="panelLayout.startResize($event, panel.value)"
      >
        <SiteDesignPanel v-if="panel.value === 'design'" :show-header="false" :draft-nodes="builder.nodes.value" />
        <CustomCssPanel v-else-if="panel.value === 'css'" :show-header="false" />
        <BuilderInspector v-else-if="panel.value === 'inspector'" :template-part="selectedTemplatePart" @edit-template-part="editTemplatePart">
          <template #empty>
            <BuilderOverview
              :title="overview.title"
              :description="overview.description"
              :icon="overview.icon"
              :with-fields="hasEntryFields"
              @open-panel="panelLayout.open($event)"
              @open-guide="guideOpen = true"
            />
          </template>
        </BuilderInspector>
        <template v-else>
          <p v-if="panelHint(panel.value)" class="border-b border-slate-100 px-3 py-2 text-xs leading-relaxed text-slate-500">{{ panelHint(panel.value) }}</p>
          <div v-if="panel.value === 'history'" class="border-b border-slate-200 p-2">
            <SegmentedControl
              v-model="historyScope"
              :options="[
                { value: 'layout', label: t(target === 'component' ? 'site.history.scope.component' : 'site.history.scope.layout') },
                { value: 'theme', label: t('site.history.scope.design') },
              ]"
            />
          </div>
          <div class="relative z-0 min-h-0 flex-1 overflow-y-auto">
            <BuilderPalette v-if="panel.value === 'add'" :with-fields="builder.currentAvailable.value" />
            <BuilderOutline v-else-if="panel.value === 'outline'" />
            <BuilderEntryFields v-else-if="panel.value === 'fields'" :state="entryFields" :busy="saving" />
            <BuilderHistory
              v-else-if="historyScope === 'theme'"
              :key="`theme-${historyVersion}`"
              :fetch-revisions="api.site.themeRevisions"
              :fetch-revision="api.site.themeRevision"
              :busy="saving || site.saving || site.loading"
              @loaded="onThemeRevisionLoaded"
            />
            <BuilderHistory
              v-else
              :key="`layout-${historyVersion}`"
              :fetch-revisions="fetchRevisions"
              :fetch-revision="fetchRevision"
              :busy="saving"
              @loaded="onRevisionLoaded"
            />
          </div>
        </template>
      </BuilderPanelWindow>

      <!-- Canvas -->
      <main class="builder-stage order-1 min-w-0 flex-1" :class="device === 'full' ? 'p-0' : 'p-3 xl:p-5'">
        <BuilderCanvas ref="canvasRef" :body="previewBody" :device="device" :selected-template-part="selectedTemplatePart" @keydown="onKeydown" @select-template-part="selectedTemplatePart = $event" />
      </main>

      <div
        v-if="dropPreview"
        class="pointer-events-none absolute inset-y-0 z-[200] flex flex-col items-center justify-center gap-1 border-2 border-dashed border-theme-500 bg-theme-100/70 p-4 text-center"
        :style="{ [dropPreview.side]: `${dropPreview.offset}px`, width: `${dropPreview.width}px` }"
        aria-hidden="true"
      >
        <Icon :icon="dropPreview.side === 'left' ? 'mdi:dock-left' : 'mdi:dock-right'" class="h-6 w-6 text-theme-600" />
        <p class="text-sm font-semibold text-theme-800">{{ t(dropPreview.side === 'left' ? 'site.builder.dockLeft' : 'site.builder.dockRight') }}</p>
        <p v-if="dropPreview.occupant" class="text-xs text-theme-700">{{ t(`site.builder.dockOutcome.${dropPreview.outcome}`, { panel: panelLabel(dropPreview.occupant) }) }}</p>
      </div>
      <div v-if="panelLayout.dragging.value" class="absolute inset-0 z-[1000] select-none" :class="panelLayout.resizing.value ? 'cursor-se-resize' : 'cursor-move'" aria-hidden="true"></div>

    </div>

    <BuilderContextMenu :device="device" />

    <BuilderGuide v-model="guideOpen" />

    <ComponentNameModal
      :model-value="builder.componentDialog.value !== null"
      :title="t('site.component.saveTitle')"
      :message="t('site.component.saveMessage')"
      :confirm-label="t('site.component.create')"
      :loading="converting"
      @update:model-value="(open) => { if (!open) builder.componentDialog.value = null }"
      @confirm="convert"
    />

    <ComponentNameModal
      :model-value="builder.fieldDialog.value !== null"
      :title="t('site.field.convertTitle')"
      :message="t('site.field.convertMessage', { type: builder.type.value?.label ?? builder.type.value?.name ?? '' })"
      :confirm-label="t('site.field.create')"
      :label="t('site.field.name')"
      :placeholder="t('site.field.namePlaceholder')"
      :initial="fieldDialogInitial"
      icon="mdi:database-plus-outline"
      :loading="creatingField"
      @update:model-value="(open) => { if (!open) builder.fieldDialog.value = null }"
      @confirm="convertField"
    />

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
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import SplitButton from "../components/SplitButton.vue";
import DeviceToggle from "../components/site/DeviceToggle.vue";
import SegmentedControl from "../components/site/SegmentedControl.vue";
import BuilderCanvas from "../components/site/builder/BuilderCanvas.vue";
import BuilderContextMenu from "../components/site/builder/BuilderContextMenu.vue";
import BuilderInspector from "../components/site/builder/BuilderInspector.vue";
import BuilderOutline from "../components/site/builder/BuilderOutline.vue";
import BuilderPageSwitcher from "../components/site/builder/BuilderPageSwitcher.vue";
import BuilderPalette from "../components/site/builder/BuilderPalette.vue";
import BuilderEntryFields from "../components/site/builder/BuilderEntryFields.vue";
import BuilderPanelWindow from "../components/site/builder/BuilderPanelWindow.vue";
import BuilderHistory from "../components/site/builder/BuilderHistory.vue";
import CustomCssPanel from "../components/site/builder/CustomCssPanel.vue";
import SiteDesignPanel from "../components/site/builder/SiteDesignPanel.vue";
import BuilderGuide from "../components/site/builder/BuilderGuide.vue";
import BuilderOverview from "../components/site/builder/BuilderOverview.vue";
import ComponentNameModal from "../components/site/ComponentNameModal.vue";
import { api } from "../api/index.js";
import { createSiteBuilder } from "../composables/useSiteBuilder.js";
import { useBuilderPanels } from "../composables/useBuilderPanels.js";
import { useBuilderEntry } from "../composables/useBuilderEntry.js";
import { usePermissions } from "../composables/usePermissions.js";
import { findNode } from "../composables/layoutTree.js";
import { useUnsavedChangesGuard } from "../composables/useUnsavedChangesGuard.js";
import { useAuthStore } from "../stores/auth.js";
import { useSiteStore } from "../stores/site.js";
import { useToastStore } from "../stores/toast.js";
import { resolveLocalized } from "../composables/localizedValues.js";
import { useLanguages } from "../composables/useLanguages.js";
import { useI18n } from "../i18n/index.js";

const route = useRoute();
const router = useRouter();
const selectedTemplatePart = ref(null);
const site = useSiteStore();
const auth = useAuthStore();
const { canContent } = usePermissions();
const toast = useToastStore();
const { t, locale: uiLocale } = useI18n();
const editorLanguages = useLanguages();
const builder = createSiteBuilder({
  t,
  componentApi: {
    create: async (body) => (await api.site.createComponent(body)).data,
    rename: async (id, name) => (await api.site.renameComponent(id, name)).data,
    remove: async (id) => (await api.site.deleteComponent(id)).data.component,
    load: async (id) => {
      try {
        return (await api.site.component(id)).data.component;
      } catch (err) {
        toast.error(err.message);
        return null;
      }
    },
    // Unsaved changes are guarded like any other way out of the builder.
    edit: (id) => router.push({ path: `/page-builder/builder/component/${id}`, query: { back: route.fullPath } }),
  },
  templateApi: {
    load: async (type) => {
      try {
        return (await api.site.cardTemplate(type)).data;
      } catch (err) {
        toast.error(err.message);
        return null;
      }
    },
  },
  fieldApi: {
    // Only offered to those who may change the content type.
    can: (type) => auth.can("schema.read", `schema:${type}`) && auth.can("schema.update", `schema:${type}`),
    create: async ({ type, key, config }) => {
      const schema = (await api.contentTypes.get(type)).data;
      if (schema.fields?.[key]) throw new Error(t("site.field.exists", { key }));
      return (await api.contentTypes.update(type, { ...schema, fields: { ...schema.fields, [key]: config } })).data;
    },
  },
  // Design edits are undone and redone together with layout edits.
  linked: {
    get: () => site.draft?.theme,
    set: (theme) => {
      if (site.draft) site.draft.theme = theme;
    },
  },
});
// Record design edits together with layout edits, including revision loading.
let changingRevisionTheme = false;
watch(() => site.draft?.theme, () => {
  builder.linkedChanged({ record: !changingRevisionTheme && !site.saving && !site.loading });
}, { deep: true, flush: "sync" });

const loadedRevisions = ref({ layout: null, theme: null });
const clone = (value) => JSON.parse(JSON.stringify(value));

function formatRevisionDate(value) {
  return new Date(value).toLocaleString(uiLocale.value, { dateStyle: "medium", timeStyle: "medium" });
}

function changeRevisionTheme(theme) {
  changingRevisionTheme = true;
  try {
    builder.mutate(() => { site.draft.theme = clone(theme); });
  } finally {
    changingRevisionTheme = false;
  }
}

function onThemeRevisionLoaded(theme, revision) {
  if (!site.draft) return;
  const before = loadedRevisions.value.theme?.before ?? clone(site.draft.theme);
  loadedRevisions.value.theme = { before, revision };
  changeRevisionTheme(theme);
}

function cancelRevision(scope) {
  const pending = loadedRevisions.value[scope];
  if (!pending) return;
  if (scope === "theme") changeRevisionTheme(pending.before);
  else builder.loadRevision(pending.before);
  loadedRevisions.value[scope] = null;
}

/** Pending design edits belong to the whole site: they stay while moving between builder pages. */
const themeDirty = computed(() => site.dirtyParts.includes("theme"));
/** The pending design edits include the custom stylesheet, or are only that. */
const cssDirty = computed(() => themeDirty.value && (site.draft?.theme?.custom_css ?? "") !== (site.data?.theme?.custom_css ?? ""));
const cssOnlyDirty = computed(() => cssDirty.value && JSON.stringify({ ...site.draft.theme, custom_css: "" }) === JSON.stringify({ ...site.data.theme, custom_css: "" }));
const { leavePromptOpen, confirmLeave, bypass } = useUnsavedChangesGuard(
  (to) => builder.isDirty.value || entryFields.isDirty.value || (themeDirty.value && !isBuilderPath(to?.path)),
  { guardUpdates: true },
);

const COMPONENTS_PATH = "/page-builder/components";
const builderAreaRef = ref(null);
const panelLayout = useBuilderPanels({
  area: builderAreaRef,
  ids: ["inspector", "add", "outline", "fields", "design", "css", "history"],
  initial: ["design", "css"].includes(route.query.settings) ? route.query.settings : null,
});
const panelWindows = panelLayout.windows;
const dropPreview = panelLayout.dropPreview;
const historyScope = ref("layout");
/** Bumped after saving so an open history list shows the version the save kept. */
const historyVersion = ref(0);
const panels = computed(() => [
  { value: "inspector", label: t("site.builder.inspector"), icon: "mdi:tune-variant" },
  { value: "add", label: t("site.builder.add"), icon: "mdi:plus-box-outline" },
  { value: "outline", label: t("site.builder.outline"), icon: "mdi:file-tree-outline" },
  ...(hasEntryFields.value ? [{ value: "fields", label: t("site.builder.entryFields"), icon: "mdi:file-document-edit-outline", dirty: entryFields.isDirty.value }] : []),
  { value: "design", label: t("site.builder.designSettings"), icon: "mdi:palette-outline", dirty: themeDirty.value && !cssOnlyDirty.value },
  { value: "css", label: t("site.theme.customCss"), icon: "mdi:language-css3", dirty: cssDirty.value },
  { value: "history", label: t("site.history.title"), icon: "mdi:history" },
].map((panel) => ({ ...panel, short: t(`site.builder.rail.${panel.value}`) })));
const visiblePanels = computed(() => panels.value.filter((panel) => panelWindows.value[panel.value].open));
function panelLabel(value) {
  return panels.value.find((panel) => panel.value === value)?.label ?? value;
}
function panelHint(panel) {
  const key = `site.builder.panelHint.${panel}`;
  const text = t(key);
  return text === key ? "" : text;
}

const GUIDE_SEEN_KEY = "cometcms-builder-guide-seen";
const guideOpen = ref(false);
// First visit: explain the builder once.
try {
  if (!localStorage.getItem(GUIDE_SEEN_KEY)) {
    guideOpen.value = true;
    localStorage.setItem(GUIDE_SEEN_KEY, "1");
  }
} catch {
  // Storage may be unavailable (private mode); the guide stays one click away.
}

const target = route.params.target;
const name = String(route.params.name);
const variant = String(route.params.variant || "detail");

const loading = ref(true);
const upgradeRequired = ref(false);
const layoutRevision = ref(null);
const loadError = ref("");
const saving = ref(false);
const samples = ref([]);
const sampleId = ref("");
const locales = ref([]);
const locale = ref("");
const device = ref("desktop");
const pendingVariant = ref("");
const canvasRef = ref(null);
const indexUrl = ref(null);
const homeUrl = ref(null);
const entryUrl = ref(null);
const component = ref(null);
const usages = ref([]);
const converting = ref(false);

/** The layout has changes that can be saved. */
const layoutSavable = computed(() => (builder.isDirty.value || upgradeRequired.value) && !builder.hasSyntaxErrors.value);

/** The entry the preview shows: the chosen sample, or the single page itself. */
const previewEntry = computed(() => (builder.type.value?.singleton ? builder.type.value.name : sampleId.value));
/** Layouts of single entries and components of a content type preview an entry. */
const showsSamples = computed(() => (target === "layout" && variant !== "index") || (target === "component" && !!builder.type.value));
const hasEntryFields = computed(() => showsSamples.value && !!previewEntry.value);
watch([hasEntryFields, loading], ([available, pending]) => {
  if (!available && !pending) panelLayout.close("fields");
});
const entryFields = useBuilderEntry({
  type: builder.type,
  entryId: computed(() => hasEntryFields.value ? previewEntry.value : ""),
  locale,
  contentApi: api.content,
  canUpdate: (type, entry) => canContent("content.update", type, entry),
});

/** The live page of what is being edited, when there is one. */
const liveUrl = computed(() => {
  if (target === "component") return null;
  const settings = builder.settings.value;
  const prefix = locale.value && locale.value !== settings?.default_locale ? `/${locale.value}` : '';
  const root = (homeUrl.value || '').replace(/\/$/, '');
  if (target === "region") return root + prefix || homeUrl.value;
  if (variant === "index") {
    const home = settings?.homepage;
    const path = home?.type === name && !home.entry ? '/' : resolveLocalized(settings?.routes?.[name]?.index_path, locale.value, settings?.default_locale);
    return path ? root + prefix + (path === '/' && prefix ? '' : path) : indexUrl.value;
  }
  return entryUrl.value;
});
const liveHint = computed(() => (target === "layout" && variant === "detail" && previewEntry.value ? t("site.builder.notLive") : ""));
/** Draft preview of the sample entry with the saved layout, the same one the entry editor used to open. */
const previewUrl = computed(() => (target === "layout" && variant === "detail" && previewEntry.value
  ? api.site.entryPreviewUrl(name, previewEntry.value, locale.value)
  : null));
const saveMenuItems = computed(() => {
  const items = [];
  if (previewUrl.value) {
    items.push({
      key: "preview",
      label: t("site.entry.preview"),
      icon: "mdi:eye-outline",
      href: previewUrl.value,
      target: "_blank",
      title: builder.isDirty.value || entryFields.isDirty.value ? t("site.builder.previewUnsaved") : t("site.entry.previewHint"),
    });
  }
  if (liveUrl.value || liveHint.value) {
    items.push({
      key: "live",
      label: t("site.builder.viewLive"),
      icon: "mdi:open-in-new",
      href: liveUrl.value,
      target: "_blank",
      disabled: !liveUrl.value,
      title: liveUrl.value || liveHint.value,
    });
  }
  return items;
});
watch([previewEntry, locale], async ([entry]) => {
  entryUrl.value = null;
  if (target !== "layout" || !entry) return;
  try {
    entryUrl.value = (await api.site.links(name, entry, { locale: locale.value })).data?.url ?? null;
  } catch {
    entryUrl.value = null;
  }
});

const variants = computed(() => {
  if (target !== "layout" || !builder.type.value) return [];
  return builder.type.value.singleton ? ["detail"] : ["detail", "index"];
});
// Opened from an entry or another builder: go back there. Only internal content and website URLs are followed.
const backTo = computed(() => {
  const back = typeof route.query.back === "string" ? route.query.back : "";
  if (back.startsWith("/content/") || back.startsWith("/page-builder/")) return back;
  if (target === "component") return COMPONENTS_PATH;
  return target === "region" ? "/page-builder/design" : `/content-types/${name}/edit#website`;
});
const heading = computed(() => {
  if (target === "component") return component.value?.name ?? "";
  return target === "region" ? t(`site.region.${name}`) : builder.type.value?.label ?? name;
});
const subheading = computed(() => {
  if (target === "component") {
    const kind = t("site.component.instanceOf", { kind: builder.kindLabel(component.value?.root ?? "section") });
    const type = builder.type.value ? ` · ${builder.type.value.label ?? builder.type.value.name}` : "";
    return `${kind}${type} · ${t("site.component.usedIn", { count: usages.value.length })}`;
  }
  return target === "region" ? t("site.builder.regionSubtitle") : t(`site.variant.${variant}`);
});

const saveStatus = computed(() => {
  if (saving.value) return "saving";
  return builder.isDirty.value || themeDirty.value || entryFields.isDirty.value ? "unsaved" : "saved";
});

/** What the inspector shows while nothing is selected. */
const overview = computed(() => {
  if (target === "component") {
    return {
      title: heading.value,
      description: t("site.builder.overview.component", { count: usages.value.length }),
      icon: "mdi:puzzle-outline",
    };
  }
  if (target === "region") {
    return {
      title: heading.value,
      description: t(`site.region.${name}Hint`),
      icon: name === "footer" ? "mdi:page-layout-footer" : "mdi:page-layout-header",
    };
  }
  const type = builder.type.value?.label ?? name;
  return {
    title: `${type} · ${t(`site.variant.${variant}`)}`,
    description: t(variant === "index" ? "site.builder.overview.index" : "site.builder.overview.detail", { type }),
    icon: builder.type.value?.icon || (variant === "index" ? "mdi:view-grid-outline" : "mdi:file-document-outline"),
  };
});

const previewBody = computed(() => {
  if (loading.value) return null;
  // The canvas always shows the draft design: unsaved edits, and a restored version as soon as it arrives.
  const theme = site.draft?.theme ? { theme: site.draft.theme, font_version: site.data?.font_version } : {};
  if (target === "component") {
    return { target: "component", component: name, nodes: builder.nodes.value, entry: sampleId.value, locale: locale.value, ...theme };
  }
  return target === "region"
    ? { target: "region", region: name, nodes: builder.nodes.value, locale: locale.value, ...theme }
    : { target: "layout", type: name, variant, nodes: builder.nodes.value, entry: sampleId.value, locale: locale.value, ...theme };
});

function isBuilderPath(path) {
  return typeof path === "string" && path.startsWith("/page-builder/builder/");
}

function editTemplatePart(part) {
  if (!["header", "footer"].includes(part)) return;
  router.push({ path: `/page-builder/builder/region/${part}`, query: { back: route.fullPath } });
}

watch(() => builder.selectedId.value, (id) => {
  if (id) {
    selectedTemplatePart.value = null;
    panelLayout.open("inspector");
  }
});
watch(selectedTemplatePart, (part) => {
  if (part) panelLayout.open("inspector");
});

watch(locale, (value) => { editorLanguages.activeLocale.value = value; builder.locale.value = value; if (value !== builder.defaultLocale.value) builder.editingMode.value = "translate"; });

async function load() {
  loading.value = true;
  loadError.value = "";
  loadedRevisions.value.layout = null;

  try {
    const entry = typeof route.query.entry === "string" ? route.query.entry : "";
    const res = target === "region"
      ? await api.site.region(name)
      : target === "component" ? await api.site.component(name, entry) : await api.site.layout(name, variant, entry);
    const data = res.data;
    layoutRevision.value = data.revision;
    builder.types.value = data.types ?? [];
    builder.menus.value = data.menus ?? [];
    builder.forms.value = data.forms ?? [];
    builder.settings.value = data.settings ?? null;
    builder.type.value = data.type ?? null;
    builder.components.value = data.components ?? [];
    if (target === "component") {
      component.value = data.component;
      usages.value = data.usages ?? [];
      builder.componentId.value = data.component.id;
      builder.rootKind.value = data.component.root;
      // A component's tree is its one root node.
      builder.spec.value = { ...data.spec, root: [data.component.root] };
      builder.currentAvailable.value = !!data.type;
    } else {
      builder.spec.value = data.spec;
      builder.currentAvailable.value = target === "layout" && variant !== "index";
    }
    upgradeRequired.value = !!data.upgrade_required;
    samples.value = data.samples ?? [];
    const requested = typeof route.query.entry === "string" ? route.query.entry : "";
    sampleId.value = samples.value.some((sample) => sample.id === requested)
      ? requested
      : samples.value.find((sample) => sample.status === "published")?.id ?? samples.value[0]?.id ?? "";
    indexUrl.value = data.index_url ?? null;
    homeUrl.value = data.home_url ?? null;
    locales.value = data.locales ?? [];
    locale.value = locales.value.includes(editorLanguages.activeLocale.value) ? editorLanguages.activeLocale.value : data.default_locale ?? "";
    builder.load(data.nodes);
  } catch (err) {
    loadError.value = err.message;
  } finally {
    loading.value = false;
  }
}

/** Save pending entry values, layout and site design together. */
async function save() {
  const saveLayout = layoutSavable.value;
  const saveTheme = themeDirty.value;
  const saveContent = entryFields.isDirty.value;
  if (saving.value || (!saveLayout && !saveTheme && !saveContent)) return;
  saving.value = true;

  try {
    if (saveContent) {
      const entries = await entryFields.save();
      for (const entry of entries) {
        const sample = samples.value.find((item) => item.id === entry.id);
        if (sample) sample.title = entry.title;
      }
    }
    if (saveLayout) {
      const nodes = builder.nodes.value;
      const pending = loadedRevisions.value.layout;
      const restoreDefault = pending?.customized === false && JSON.stringify(nodes) === pending.nodes;
      let res;
      if (restoreDefault) {
        res = target === "region" ? await api.site.resetRegion(name) : await api.site.resetLayout(name, variant);
      } else if (target === "region") {
        res = await api.site.saveRegion(name, nodes, layoutRevision.value);
      } else if (target === "component") {
        res = await api.site.saveComponent(name, nodes, layoutRevision.value);
      } else {
        res = await api.site.saveLayout(name, variant, nodes, layoutRevision.value);
      }
      builder.markSaved(res.data.nodes);
      layoutRevision.value = res.data.revision;
      upgradeRequired.value = false;
      loadedRevisions.value.layout = null;
      if (target === "component") component.value = res.data.component;
      else site.markCustomized(target, name, variant, res.data.customized);
    }
    if (saveTheme) {
      await site.save({ parts: ["theme"] });
      loadedRevisions.value.theme = null;
    }
    if (!builder.isDirty.value) loadedRevisions.value.layout = null;
    if (!themeDirty.value) loadedRevisions.value.theme = null;
    historyVersion.value++;
    const message = saveContent ? "site.builder.changesSaved" : !saveLayout ? "site.builder.designSaved" : target === "component" ? "site.component.saved" : "site.builder.saved";
    toast.success(t(message));
  } catch (err) {
    if (err.entryId) {
      sampleId.value = err.entryId;
      locale.value = err.locale;
      panelLayout.open("fields");
    }
    toast.error(err.message);
  } finally {
    if (saveContent) canvasRef.value?.refresh();
    saving.value = false;
  }
}

function fetchRevisions() {
  if (target === "component") return api.site.componentRevisions(name);
  return target === "region" ? api.site.regionRevisions(name) : api.site.layoutRevisions(name, variant);
}

function fetchRevision(id) {
  if (target === "component") return api.site.componentRevision(name, id);
  return target === "region" ? api.site.regionRevision(name, id) : api.site.layoutRevision(name, variant, id);
}

function onRevisionLoaded(data, revision) {
  const before = loadedRevisions.value.layout?.before ?? clone(builder.nodes.value);
  loadedRevisions.value.layout = { before, revision, customized: data.customized, nodes: JSON.stringify(data.nodes) };
  builder.loadRevision(data.nodes);
}

/** Turn the node waiting in the name dialog into a component instance. */
async function convert(componentName) {
  const pending = builder.componentDialog.value;
  if (!pending || converting.value) return;
  converting.value = true;
  try {
    await builder.convertToComponent(pending.id, componentName);
    builder.componentDialog.value = null;
    toast.success(t("site.component.created"));
  } catch (err) {
    toast.error(err.message);
  } finally {
    converting.value = false;
  }
}

const creatingField = ref(false);
/** The element's kind as a starting name, e.g. "Heading". */
const fieldDialogInitial = computed(() => {
  const node = builder.fieldDialog.value ? findNode(builder.nodes.value, builder.fieldDialog.value.id) : null;
  return node ? builder.kindLabel(node.kind) : "";
});

/** Turn the element waiting in the field dialog into a field of the content type. */
async function convertField(label) {
  const pending = builder.fieldDialog.value;
  if (!pending || creatingField.value) return;
  creatingField.value = true;
  try {
    const key = await builder.convertToField(pending.id, label);
    builder.fieldDialog.value = null;
    if (key) toast.success(t("site.field.created", { key }));
  } catch (err) {
    toast.error(err.message);
  } finally {
    creatingField.value = false;
  }
}

function goToVariant(next) {
  if (next === variant) return;
  if (builder.isDirty.value || entryFields.isDirty.value) {
    pendingVariant.value = next;
    return;
  }
  switchVariant(next);
}

function switchVariant(next) {
  pendingVariant.value = "";
  bypass();
  router.push({ path: `/page-builder/builder/layout/${name}/${next}`, query: route.query });
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

  if (event.key === "?" && !modifier) {
    event.preventDefault();
    guideOpen.value = true;
    return;
  }

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
    else {
      builder.select(null);
      selectedTemplatePart.value = null;
    }
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

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  // Design edits the user chose to leave behind must not resurface elsewhere.
  if (!isBuilderPath(router.currentRoute.value.path)) site.discardTheme();
});
</script>

<style scoped>
.topbar-action {
  position: relative;
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  color: rgb(var(--color-slate-600));
}
.topbar-action:hover:not(:disabled) {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
.topbar-action:disabled {
  opacity: 0.35;
}
.rail-action {
  touch-action: none;
  position: relative;
  display: flex;
  width: 4.25rem;
  flex-direction: column;
  align-items: center;
  gap: 0.1875rem;
  border-radius: 0.5rem;
  padding: 0.4375rem 0.125rem 0.375rem;
  color: rgb(var(--color-slate-500));
  transition: background-color 150ms, color 150ms;
}
.rail-action:hover {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
.rail-action.is-active {
  background: rgb(var(--color-theme-50, 239 246 255));
  color: rgb(var(--color-theme-text, 29 78 216));
}
.rail-label {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 10px;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 0.875rem;
}
</style>
