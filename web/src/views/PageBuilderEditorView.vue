<template>
  <div class="builder-shell flex h-screen flex-col bg-slate-100">
    <!-- Top bar -->
    <header class="builder-topbar relative flex h-12 shrink-0 items-center gap-2 border-b px-2">
      <router-link :to="backTo" class="topbar-action" :title="t('site.builder.back')">
        <Icon icon="mdi:arrow-left" class="h-[18px] w-[18px]" />
        <span class="sr-only">{{ t("site.builder.back") }}</span>
      </router-link>
      <span class="topbar-divider" aria-hidden="true"></span>

      <div class="min-w-32 max-w-64 pl-1">
        <BuilderPageSwitcher :heading="heading" :target="target" :name="name" />
        <p class="topbar-muted truncate text-[11px] leading-tight">{{ subheading }}</p>
      </div>

      <!-- The layers of one content type: an entry's own page content, the entry page around it, the list page. -->
      <nav v-if="views.length || (showsSamples && samples.length)" class="topbar-variants hidden min-w-0 rounded-lg p-0.5 md:flex" :aria-label="t('site.builder.variants')">
        <BuilderEntrySwitcher
          v-if="showsSamples && !views.some((item) => item.key === 'page') && samples.length"
          :label="samples.find((entry) => entry.id === sampleId)?.title || sampleId"
          :model-value="sampleId"
          :entries="samples"
          :navigable="false"
          :disabled="saving || loading"
          @update:model-value="selectEntry"
        />
        <template v-for="item in views" :key="item.key">
          <BuilderEntrySwitcher
            v-if="item.key === 'page'"
            :label="item.label"
            :title="item.title"
            :icon="item.icon"
            :active="currentView === 'page'"
            :model-value="target === 'page' ? pageEntryId : sampleId"
            :entries="samples"
            :disabled="saving || loading"
            @navigate="goToView(item)"
            @update:model-value="selectEntry"
          />
          <button
            v-else
            type="button"
            class="topbar-view flex min-w-0 max-w-[16rem] items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
            :aria-pressed="item.key === currentView"
            :title="item.title"
            @click="goToView(item)"
          >
            <Icon :icon="item.icon" class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ item.label }}</span>
          </button>
        </template>
      </nav>

      <div class="ml-auto flex min-w-0 items-center gap-1.5">
        <div v-if="locales.length > 1" class="topbar-group hidden md:flex">
          <!-- The language also sets the mode: other languages only translate texts. -->
          <div class="flex items-center gap-2 px-1.5">
            <LanguageTabs v-model="locale" class="topbar-languages" :locales="locales" :disabled="saving" compact />
            <span
              v-if="builder.translating.value"
              class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800"
              :title="t('localization.translateHint')"
            >
              <Icon icon="mdi:translate" class="h-3.5 w-3.5 shrink-0" />
              <span class="hidden xl:inline">{{ t('localization.translate') }}</span>
            </span>
          </div>
        </div>

        <DeviceToggle v-model="device" class="topbar-devices" allow-full-width />

        <span class="topbar-divider" aria-hidden="true"></span>
        <button type="button" class="topbar-action" :disabled="!builder.canUndo.value" :title="`${t('site.builder.undo')} (Ctrl+Z)`" @click="builder.undo()">
          <Icon icon="mdi:undo" class="h-[18px] w-[18px]" />
          <span class="sr-only">{{ t("site.builder.undo") }}</span>
        </button>
        <button type="button" class="topbar-action" :disabled="!builder.canRedo.value" :title="`${t('site.builder.redo')} (Ctrl+Shift+Z)`" @click="builder.redo()">
          <Icon icon="mdi:redo" class="h-[18px] w-[18px]" />
          <span class="sr-only">{{ t("site.builder.redo") }}</span>
        </button>
        <BuilderHelpMenu @guide="guideOpen = true" @reset-layout="panelLayout.reset()" />
        <span class="topbar-divider" aria-hidden="true"></span>

        <span
          v-if="saveStatus !== 'saved'"
          class="hidden items-center gap-1.5 whitespace-nowrap px-1 text-xs xl:flex"
          :class="saveStatus === 'unsaved' ? 'font-medium text-white' : 'topbar-muted'"
          role="status"
          :title="saveStatus === 'unsaved' ? t('site.builder.status.unsavedHint') : undefined"
        >
          <Icon v-if="saveStatus === 'saving'" icon="mdi:loading" class="h-3.5 w-3.5 animate-spin" />
          <span v-else class="h-2 w-2 rounded-full bg-amber-400"></span>
          {{ t(`site.builder.status.${saveStatus}`) }}
        </span>

        <SplitButton
          variant="inverse"
          :primary-disabled="saving || loading || switching || (!layoutSavable && !themeDirty && !entryFields.isDirty.value)"
          :menu-label="t('common.moreActions')"
          :items="saveMenuItems"
          @click="save"
          @select="onSaveMenu"
        >
          {{ saving ? t("common.saving") : t("site.builder.save") }}
          <template #badge>
            <span v-if="(builder.isDirty.value || themeDirty || entryFields.isDirty.value) && !saving" class="topbar-save-badge pointer-events-none absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2"></span>
          </template>
        </SplitButton>
      </div>
      <div v-if="switchingSlow" class="builder-progress pointer-events-none absolute inset-x-0 bottom-0 h-0.5 overflow-hidden" role="progressbar" :aria-label="t('site.builder.switching')"></div>
    </header>

    <div v-for="scope in ['layout', 'theme'].filter((scope) => loadedRevisions[scope])" :key="scope" role="status" class="flex items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
      <p v-if="!loadedRevisions[scope].revision">{{ t('site.builder.resetPending') }}</p>
      <p v-else>{{ t('site.history.loadedBanner', { scope: t(scope === 'theme' ? 'site.history.scope.design' : historyScopeKey), date: formatRevisionDate(loadedRevisions[scope].revision.created_at) }) }}</p>
      <button type="button" class="btn-secondary btn-sm shrink-0" :disabled="saving" @click="cancelRevision(scope)">{{ t('common.cancel') }}</button>
    </div>
    <p v-if="upgradeRequired" role="status" class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">{{ t("site.binding.upgrade") }}</p>
    <p v-if="target === 'page' && !loading && !loadError && !pagePlaced" role="status" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
      {{ t("site.page.notPlaced") }}
      <router-link :to="layoutLink" class="font-medium underline">{{ t("site.page.editLayout") }}</router-link>
    </p>
    <p v-if="builder.hasSyntaxErrors.value" role="alert" class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ t("site.binding.invalidSyntax") }}</p>
    <div v-if="loadError" class="m-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ loadError }}</div>

    <div v-else-if="loading" class="flex flex-1 items-center justify-center"><LoadingSpinner /></div>

    <!-- Another view keeps this one on screen, untouchable, until its content arrives. -->
    <div
      v-else
      ref="builderAreaRef"
      class="relative isolate flex min-h-0 flex-1 overflow-hidden transition-opacity duration-150"
      :class="{ 'opacity-60': switchingSlow }"
      :inert="switching || null"
      :aria-busy="switching"
    >

      <!-- Panel rail -->
      <nav
        class="relative z-[150] flex w-[52px] shrink-0 flex-col items-center gap-1 border-r border-slate-200 bg-white py-2"
        :aria-label="t('site.builder.panels')"
      >
        <template v-for="panel in panels" :key="panel.value">
          <!-- Separator before entry fields -->
          <div
            v-if="panel.value === 'fields'"
            class="my-1 h-px w-6 shrink-0 bg-slate-200"
            aria-hidden="true"
          ></div>

          <button
            type="button"
            class="rail-action group"
            :class="{ 'is-active': panelWindows[panel.value]?.open, 'is-tooltip-off': clickedRailPanel === panel.value }"
            :aria-pressed="panelWindows[panel.value]?.open ?? false"
            :aria-label="panel.label"
            @pointerdown="panelLayout.startMove($event, panel.value, true)"
            @click="clickedRailPanel = panel.value; panelLayout.toggle(panel.value)"
            @pointerleave="clickedRailPanel = null"
          >
            <Icon :icon="panel.icon" class="h-5 w-5" />
            <span
              v-if="panel.dirty"
              class="pointer-events-none absolute right-1 top-1 h-2 w-2 rounded-full bg-amber-400 ring-2 ring-white"
            ></span>
            <!-- Instant label: the rail is icon-only to leave room for the canvas. -->
            <span class="rail-tooltip" role="tooltip" aria-hidden="true">
              <span class="font-semibold">{{ panel.label }}</span>
              <span class="block text-[10px] font-normal text-ink-300">{{ t('site.builder.panelWindowHint') }}</span>
            </span>
          </button>

          <!-- Separator before design settings -->
          <div
            v-if="(panel.value === 'outline' && !hasEntryFields) || panel.value === 'fields'"
            class="my-1 h-px w-6 shrink-0 bg-slate-200"
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
          <p v-if="panelHint(panel.value)" class="flex gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2 text-[11px] leading-snug text-slate-500">
            <Icon icon="mdi:information-outline" class="mt-px h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
            <span>{{ panelHint(panel.value) }}</span>
          </p>
          <div v-if="panel.value === 'history'" class="border-b border-slate-200 p-2">
            <SegmentedControl
              v-model="historyScope"
              :options="[
                { value: 'layout', label: t(historyScopeKey) },
                { value: 'theme', label: t('site.history.scope.design') },
              ]"
            />
          </div>
          <div class="relative z-0 min-h-0 flex-1 overflow-y-auto overflow-x-hidden">
            <BuilderPalette v-if="panel.value === 'add'" :with-fields="builder.currentAvailable.value" />
            <BuilderOutline v-else-if="panel.value === 'outline'" />
            <BuilderEntryFields v-else-if="panel.value === 'fields'" :state="entryFields" :busy="saving" :reveal="revealedField" />
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
      <main class="builder-stage order-1 min-w-0 flex-1" :class="device === 'full' ? 'p-0' : 'p-2 xl:p-4'">
        <BuilderCanvas ref="canvasRef" :body="previewBody" :device="device" :selected-template-part="selectedTemplatePart" @keydown="onKeydown" @select-template-part="selectedTemplatePart = $event" @edit-field="editEntryField" @switch-page-area="selectPageArea" />
      </main>

      <div
        v-if="dropPreview"
        class="pointer-events-none absolute inset-y-0 z-[200] flex flex-col items-center justify-center gap-1 border-2 border-dashed border-theme-500 bg-theme-100/70 p-4 text-center"
        :style="{ [dropPreview.side]: `${dropPreview.offset}px`, width: `${dropPreview.width}px` }"
        aria-hidden="true"
      >
        <Icon :icon="dropPreview.side === 'left' ? 'mdi:dock-left' : 'mdi:dock-right'" class="h-6 w-6 text-theme-600" />
        <p class="text-sm font-semibold text-theme-800">{{ t(dropPreview.side === 'left' ? 'site.builder.dockLeft' : 'site.builder.dockRight') }}</p>
        <p v-if="dropPreview.occupant" class="text-xs text-theme-700">{{ t('site.builder.dockOutcome.replace', { panel: panelLabel(dropPreview.occupant) }) }}</p>
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
      :model-value="pendingView !== null"
      :title="t('unsaved.title')"
      :message="t('unsaved.message')"
      :confirm-label="t('unsaved.leave')"
      :cancel-label="t('unsaved.stay')"
      variant="warning"
      @update:model-value="(open) => { if (!open) pendingView = null }"
      @confirm="switchView(pendingView)"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import { useSitePreview } from "../composables/useSitePreview.js";
import SplitButton from "../components/SplitButton.vue";
import DeviceToggle from "../components/site/DeviceToggle.vue";
import SegmentedControl from "../components/site/SegmentedControl.vue";
import BuilderCanvas from "../components/site/builder/BuilderCanvas.vue";
import BuilderContextMenu from "../components/site/builder/BuilderContextMenu.vue";
import BuilderInspector from "../components/site/builder/BuilderInspector.vue";
import BuilderOutline from "../components/site/builder/BuilderOutline.vue";
import BuilderPageSwitcher from "../components/site/builder/BuilderPageSwitcher.vue";
import BuilderEntrySwitcher from "../components/site/builder/BuilderEntrySwitcher.vue";
import BuilderPalette from "../components/site/builder/BuilderPalette.vue";
import BuilderEntryFields from "../components/site/builder/BuilderEntryFields.vue";
import BuilderPanelWindow from "../components/site/builder/BuilderPanelWindow.vue";
import BuilderHistory from "../components/site/builder/BuilderHistory.vue";
import CustomCssPanel from "../components/site/builder/CustomCssPanel.vue";
import SiteDesignPanel from "../components/site/builder/SiteDesignPanel.vue";
import BuilderGuide from "../components/site/builder/BuilderGuide.vue";
import BuilderHelpMenu from "../components/site/builder/BuilderHelpMenu.vue";
import BuilderOverview from "../components/site/builder/BuilderOverview.vue";
import ComponentNameModal from "../components/site/ComponentNameModal.vue";
import LanguageTabs from "../components/LanguageTabs.vue";
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
const fullPreview = useSitePreview();
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
    update: async ({ type, key, config }) => {
      const schema = (await api.contentTypes.get(type)).data;
      if (!schema.fields?.[key]) throw new Error(t("site.field.missing", { key }));
      return (await api.contentTypes.update(type, { ...schema, fields: { ...schema.fields, [key]: { ...schema.fields[key], label: config.label } } })).data;
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
]);
/** The rail button just clicked hides its tooltip until the pointer leaves, so it doesn't cover the panel. */
const clickedRailPanel = ref(null);
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

/** What a builder route asks to edit. */
function contextFor(to) {
  const target = String(to.params.target);
  const page = target === "page";
  return {
    target,
    name: String(to.params.name),
    /** The page builder of one entry edits its page content inside the type's detail layout. */
    pageEntry: page ? String(to.params.variant ?? "") : "",
    variant: page ? "detail" : String(to.params.variant || "detail"),
    field: page && typeof to.query.field === "string" ? to.query.field : "",
  };
}
const sameContext = (a, b) => ["target", "name", "pageEntry", "variant", "field"].every((key) => a[key] === b[key]);
/** What the builder shows. Moving to another builder page keeps this one until the next one's content arrives. */
const shown = ref(contextFor(route));
/** What the builder shows or is loading: the latest request wins. */
let requested = shown.value;
const target = computed(() => shown.value.target);
const name = computed(() => shown.value.name);
const pageEntry = computed(() => shown.value.pageEntry);
const variant = computed(() => shown.value.variant);
/** The page field being edited; the type's first until the page loads. */
const pageField = ref(shown.value.field);
/** The entry has page content of its own (not the layout's default content). */
const pageCustomized = ref(false);
/** The detail layout has a place for the page field. */
const pagePlaced = ref(true);
const pageTitle = ref("");
/** The default design (generated layout, header or footer, or the layout's default page content) that Reset brings back. */
const defaultNodes = ref(null);
/** A layout, header or footer has a saved design of its own (not the generated default). */
const layoutCustomized = ref(false);
/** The entry's stable ID: builders name entries by it, as slugs differ between languages. */
const pageEntryId = ref(pageEntry.value);
const layoutLink = computed(() => ({ path: `/page-builder/builder/layout/${name.value}/detail`, query: { entry: pageEntryId.value, back: route.fullPath } }));
const historyScopeKey = computed(() => {
  if (target.value === "page") return "site.history.scope.page";
  return target.value === "component" ? "site.history.scope.component" : "site.history.scope.layout";
});

/** Nothing to show yet: the first load. */
const loading = ref(true);
/** Another view of this builder loads while the current one stays on screen. */
const switching = ref(false);
/** The switch takes long enough to say so; quick ones show nothing. */
const switchingSlow = ref(false);
let switchingTimer = null;
watch(switching, (value) => {
  clearTimeout(switchingTimer);
  if (value) switchingTimer = setTimeout(() => { switchingSlow.value = true; }, 150);
  else switchingSlow.value = false;
});
/** Only the latest load applies; slower earlier ones are dropped. */
let loadSequence = 0;
const upgradeRequired = ref(false);
const layoutRevision = ref(null);
const loadError = ref("");
const saving = ref(false);
const samples = ref([]);
const sampleId = ref("");
const locales = ref([]);
const locale = ref("");
const device = ref("desktop");
/** A layer to switch to once unsaved changes may be left behind. */
const pendingView = ref(null);
const canvasRef = ref(null);
const indexUrl = ref(null);
const homeUrl = ref(null);
const entryUrl = ref(null);
const component = ref(null);
const usages = ref([]);
const converting = ref(false);

/** The layout has changes that can be saved. */
const layoutSavable = computed(() => (builder.isDirty.value || upgradeRequired.value) && !builder.hasSyntaxErrors.value);

/** The entry the preview shows: the chosen sample. */
const previewEntry = computed(() => sampleId.value);
/** Layouts of single entries and components of a content type preview an entry. */
const showsSamples = computed(() => (target.value === "layout" && variant.value !== "index") || (target.value === "component" && !!builder.type.value));
const hasEntryFields = computed(() => (showsSamples.value || target.value === "page") && !!previewEntry.value);
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
  if (target.value === "component") return null;
  const settings = builder.settings.value;
  const prefix = locale.value && locale.value !== settings?.default_locale ? `/${locale.value}` : '';
  const root = (homeUrl.value || '').replace(/\/$/, '');
  if (target.value === "region") return root + prefix || homeUrl.value;
  if (variant.value === "index") {
    const home = settings?.homepage;
    const path = home?.type === name.value && !home.entry ? '/' : resolveLocalized(settings?.routes?.[name.value]?.index_path, locale.value, settings?.default_locale);
    return path ? root + prefix + (path === '/' && prefix ? '' : path) : indexUrl.value;
  }
  return entryUrl.value;
});
/** Why the live page cannot be opened, shown on the disabled menu item. */
const liveHint = computed(() => {
  if (target.value === "component") return t("site.builder.componentNoPage");
  if (target.value === "region") return t("site.builder.siteOff");
  if (variant.value === "index") return t("site.builder.noLiveIndex");
  return previewEntry.value ? t("site.builder.notLive") : t("site.builder.noEntry");
});
/** Full-page previews use the same draft as the canvas. */
const canPreview = computed(() => !loading.value && (target.value !== "layout" || variant.value === "index" || !!previewEntry.value));
async function onSaveMenuSelect(item) {
  if (item.key !== "preview" || !canPreview.value || !previewBody.value) return;
  try {
    await fullPreview.open(previewBody.value);
  } catch (error) {
    toast.error(error.message);
  }
}
const previewHint = computed(() => (target.value === "component" ? t("site.builder.componentNoPage") : t("site.builder.noEntry")));
// Both actions are always offered, so the menu looks the same everywhere; unavailable ones say why.
const saveMenuItems = computed(() => [
  ...(target.value === "page" ? [
    {
      key: "reset-page",
      label: t("site.page.reset"),
      icon: "mdi:restore",
      disabled: saving.value || (!pageCustomized.value && !builder.isDirty.value),
      title: pageCustomized.value || builder.isDirty.value ? t("site.page.resetHint") : t("site.page.usesDefault"),
    },
  ] : []),
  ...(["layout", "region"].includes(target.value) ? [{
    key: "reset-layout",
    label: t("site.builder.resetDefault"),
    icon: "mdi:restore",
    disabled: saving.value || (!layoutCustomized.value && !builder.isDirty.value),
    title: layoutCustomized.value || builder.isDirty.value ? t("site.builder.resetDefaultHint") : t("site.builder.usesDefault"),
  }] : []),
  {
    key: "preview",
    label: t("site.entry.preview"),
    icon: "mdi:eye-outline",
    disabled: !canPreview.value || fullPreview.opening.value,
    title: !canPreview.value ? previewHint.value : t("site.builder.previewUnsaved"),
  },
  {
    key: "live",
    label: t("site.builder.viewLive"),
    icon: "mdi:open-in-new",
    href: liveUrl.value,
    target: "_blank",
    disabled: !liveUrl.value,
    title: liveUrl.value || liveHint.value,
  },
]);
watch([previewEntry, locale], async ([entry]) => {
  entryUrl.value = null;
  if (!["layout", "page"].includes(target.value) || !entry) return;
  try {
    entryUrl.value = (await api.site.links(name.value, entry, { locale: locale.value })).data?.url ?? null;
  } catch {
    entryUrl.value = null;
  }
});

/**
 * The layers of the edited content type, to move between them without
 * losing the entry or where the builder was opened from: the entry's own
 * page content (when the type has a page field), its entry page and the list page.
 */
const views = computed(() => {
  const type = builder.type.value;
  if (!["layout", "page"].includes(target.value) || !type) return [];
  const back = typeof route.query.back === "string" ? { back: route.query.back } : {};
  const entry = target.value === "page" ? pageEntryId.value : sampleId.value;
  const entryTitle = target.value === "page" ? pageTitle.value : samples.value.find((sample) => sample.id === entry)?.title ?? "";
  const hasPage = Object.values(type.fields ?? {}).some((config) => config?.type === "page");
  return [
    ...(hasPage && entry ? [{
      key: "page",
      label: t("site.view.page", { title: entryTitle || entry }),
      title: t("site.view.pageHint", { title: entryTitle || entry }),
      icon: "mdi:file-document-edit-outline",
      to: { path: `/page-builder/builder/page/${name.value}/${entry}`, query: { ...(target.value === "page" && pageField.value ? { field: pageField.value } : {}), ...back } },
    }] : []),
    {
      key: "detail",
      label: t("site.variant.detail"),
      title: hasPage ? t("site.view.detailHint") : undefined,
      icon: "mdi:file-document-outline",
      to: { path: `/page-builder/builder/layout/${name.value}/detail`, query: { ...(entry ? { entry } : {}), ...back } },
    },
    { key: "index", label: t("site.variant.index"), icon: "mdi:view-grid-outline", to: { path: `/page-builder/builder/layout/${name.value}/index`, query: { ...(entry ? { entry } : {}), ...back } } },
  ];
});
const currentView = computed(() => (target.value === "page" ? "page" : variant.value));
// Opened from an entry or another builder: go back there. Only internal content and website URLs are followed.
const backTo = computed(() => {
  const back = typeof route.query.back === "string" ? route.query.back : "";
  if (back.startsWith("/content/") || back.startsWith("/page-builder/")) return back;
  if (target.value === "component") return COMPONENTS_PATH;
  if (target.value === "page") return `/content/${name.value}/${pageEntry.value}`;
  return target.value === "region" ? "/page-builder/design" : `/content-types/${name.value}/edit#website`;
});
const heading = computed(() => {
  if (target.value === "component") return component.value?.name ?? "";
  if (target.value === "page") return pageTitle.value || pageEntry.value;
  return target.value === "region" ? t(`site.region.${name.value}`) : builder.type.value?.label ?? name.value;
});
const subheading = computed(() => {
  if (target.value === "component") {
    const kind = t("site.component.instanceOf", { kind: builder.kindLabel(component.value?.root ?? "section") });
    const type = builder.type.value ? ` · ${builder.type.value.label ?? builder.type.value.name}` : "";
    return `${kind}${type} · ${t("site.component.usedIn", { count: usages.value.length })}`;
  }
  if (target.value === "page") {
    const fields = builder.pageFields();
    const area = fields.length > 1 ? ` · ${fields.find((field) => field.key === pageField.value)?.label ?? pageField.value}` : "";
    return `${builder.type.value?.label ?? name.value}${area} · ${t(pageCustomized.value ? "site.page.own" : "site.page.default")}`;
  }
  return target.value === "region" ? t("site.builder.regionSubtitle") : t(`site.variant.${variant.value}`);
});

const saveStatus = computed(() => {
  if (saving.value) return "saving";
  return builder.isDirty.value || themeDirty.value || entryFields.isDirty.value ? "unsaved" : "saved";
});

/** What the inspector shows while nothing is selected. */
const overview = computed(() => {
  if (target.value === "component") {
    return {
      title: heading.value,
      description: t("site.builder.overview.component", { count: usages.value.length }),
      icon: "mdi:puzzle-outline",
    };
  }
  if (target.value === "page") {
    return {
      title: heading.value,
      description: t("site.builder.overview.page", { type: builder.type.value?.label ?? name.value }),
      icon: "mdi:file-document-edit-outline",
    };
  }
  if (target.value === "region") {
    return {
      title: heading.value,
      description: t(`site.region.${name.value}Hint`),
      icon: name.value === "footer" ? "mdi:page-layout-footer" : "mdi:page-layout-header",
    };
  }
  const type = builder.type.value?.label ?? name.value;
  return {
    title: `${type} · ${t(`site.variant.${variant.value}`)}`,
    description: t(variant.value === "index" ? "site.builder.overview.index" : "site.builder.overview.detail", { type }),
    icon: builder.type.value?.icon || (variant.value === "index" ? "mdi:view-grid-outline" : "mdi:file-document-outline"),
  };
});

const previewBody = computed(() => {
  if (loading.value) return null;
  // The canvas always shows the draft design: unsaved edits, and a restored version as soon as it arrives.
  const theme = {
    ...(site.draft?.theme ? { theme: site.draft.theme, font_version: site.data?.font_version } : {}),
    ...(site.draft?.menus ? { menus: site.draft.menus } : {}),
    entries: entryFields.previewEntries(),
  };
  if (target.value === "component") {
    return { target: "component", component: name.value, nodes: builder.nodes.value, entry: sampleId.value, locale: locale.value, ...theme };
  }
  if (target.value === "page") {
    return { target: "entry_page", type: name.value, entry: pageEntry.value, field: pageField.value, nodes: builder.nodes.value, locale: locale.value, ...theme };
  }
  return target.value === "region"
    ? { target: "region", region: name.value, nodes: builder.nodes.value, locale: locale.value, ...theme }
    : { target: "layout", type: name.value, variant: variant.value, nodes: builder.nodes.value, entry: sampleId.value, locale: locale.value, ...theme };
});

function isBuilderPath(path) {
  return typeof path === "string" && path.startsWith("/page-builder/builder/");
}

function editTemplatePart(part) {
  if (!["header", "footer"].includes(part)) return;
  router.push({ path: `/page-builder/builder/region/${part}`, query: { back: route.fullPath } });
}

watch(() => builder.pageFieldError.value, (message) => {
  if (!message) return;
  toast.error(message);
  builder.pageFieldError.value = "";
});

watch(() => builder.selectedId.value, (id) => {
  if (id) {
    selectedTemplatePart.value = null;
    panelLayout.open("inspector");
  }
});
watch(selectedTemplatePart, (part) => {
  if (part) panelLayout.open("inspector");
});

/** The field the Content fields panel brings into view: `{ name }`. */
const revealedField = ref(null);
/** Double-clicked smart tag content edits its field of the previewed entry. */
function editEntryField({ fields, text }) {
  const names = hasEntryFields.value ? fields.filter((field) => field in entryFields.fields.value) : [];
  if (!names.length) return;
  const plain = (field) => String(entryFields.valueFor(field) ?? "").replace(/<[^>]+>/g, " ");
  // In text mixing several tags, the clicked word picks the field that contains it.
  const name = (text && names.find((field) => plain(field).includes(text))) || names[0];
  panelLayout.open("fields");
  revealedField.value = { name };
}

watch(locale, (value) => { editorLanguages.activeLocale.value = value; builder.locale.value = value; });

async function load(next = requested) {
  const current = ++loadSequence;
  // Once the builder shows, later loads keep it on screen until their content arrives.
  if (loading.value || loadError.value) loading.value = true;
  else switching.value = true;
  loadError.value = "";
  requested = next;
  const { target, name, pageEntry, variant, field } = next;

  try {
    const entry = typeof route.query.entry === "string" ? route.query.entry : "";
    let res;
    if (target === "page") res = await api.site.page(name, pageEntry, field);
    else if (target === "region") res = await api.site.region(name);
    else res = target === "component" ? await api.site.component(name, entry) : await api.site.layout(name, variant, entry);
    if (current !== loadSequence) return;
    const data = res.data;
    const moved = !sameContext(next, shown.value);
    shown.value = next;
    loadedRevisions.value.layout = null;
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
      builder.currentAvailable.value = ["layout", "page"].includes(target) && variant !== "index";
      builder.componentId.value = null;
      builder.rootKind.value = null;
    }
    if (target === "page") {
      pageField.value = data.field;
      // The server picks the first area when none was asked for.
      requested = shown.value = { ...next, field: data.field };
      pageCustomized.value = !!data.customized;
      pagePlaced.value = data.placed !== false;
      pageTitle.value = data.entry?.title ?? "";
      pageEntryId.value = data.entry?.id || pageEntry;
    }
    layoutCustomized.value = !!data.customized;
    defaultNodes.value = data.default_nodes ?? (target === "page" ? data.nodes : null);
    upgradeRequired.value = !!data.upgrade_required;
    samples.value = data.samples ?? [];
    // The server names the requested entry by its stable ID, whatever the link used.
    const wanted = data.sample || (typeof route.query.entry === "string" ? route.query.entry : "");
    sampleId.value = target === "page" ? data.entry.id
      : samples.value.some((sample) => sample.id === wanted) ? wanted
      : samples.value.find((sample) => sample.status === "published")?.id ?? samples.value[0]?.id ?? "";
    indexUrl.value = data.index_url ?? null;
    homeUrl.value = data.home_url ?? null;
    locales.value = data.locales ?? [];
    locale.value = locales.value.includes(route.query.locale) ? route.query.locale
      : locales.value.includes(editorLanguages.activeLocale.value) ? editorLanguages.activeLocale.value : data.default_locale ?? "";
    builder.load(data.nodes);
    if (moved) leftView();
  } catch (err) {
    if (current !== loadSequence) return;
    loadError.value = err.message;
    // The error stands for the requested page: nothing of the previous one may be saved there.
    shown.value = next;
    leftView();
    builder.load([]);
  } finally {
    if (current === loadSequence) {
      loading.value = false;
      switching.value = false;
    }
  }
}

/** Save pending entry values, layout and site design together. */
async function save() {
  const saveLayout = layoutSavable.value;
  const saveTheme = themeDirty.value;
  const saveContent = entryFields.isDirty.value;
  if (saving.value || switching.value || (!saveLayout && !saveTheme && !saveContent)) return;
  saving.value = true;

  try {
    if (saveContent) {
      const entries = await entryFields.save();
      for (const entry of entries) {
        const sample = samples.value.find((item) => item.id === (entry.uid || entry.id));
        if (sample) sample.title = entry.title;
        // The page content is saved on top of the version the fields just saved.
        if (target.value === "page" && (entry.uid || entry.id) === sampleId.value) {
          layoutRevision.value = entry.revision;
          pageTitle.value = entry.title;
        }
      }
    }
    if (saveLayout) {
      const nodes = builder.nodes.value;
      const pending = loadedRevisions.value.layout;
      const restoreDefault = pending?.customized === false && JSON.stringify(nodes) === pending.nodes;
      let res;
      if (target.value === "page") {
        res = restoreDefault
          ? await api.site.resetPage(name.value, pageEntry.value, pageField.value, layoutRevision.value)
          : await api.site.savePage(name.value, pageEntry.value, pageField.value, nodes, layoutRevision.value);
      } else if (restoreDefault) {
        res = target.value === "region" ? await api.site.resetRegion(name.value) : await api.site.resetLayout(name.value, variant.value);
      } else if (target.value === "region") {
        res = await api.site.saveRegion(name.value, nodes, layoutRevision.value);
      } else if (target.value === "component") {
        res = await api.site.saveComponent(name.value, nodes, layoutRevision.value);
      } else {
        res = await api.site.saveLayout(name.value, variant.value, nodes, layoutRevision.value);
      }
      builder.markSaved(res.data.nodes);
      layoutRevision.value = res.data.revision;
      upgradeRequired.value = false;
      loadedRevisions.value.layout = null;
      if (target.value === "component") component.value = res.data.component;
      else if (target.value === "page") savedPage(res.data);
      else {
        site.markCustomized(target.value, name.value, variant.value, res.data.customized);
        layoutCustomized.value = res.data.customized;
      }
    }
    if (saveTheme) {
      await site.save({ parts: ["theme"] });
      loadedRevisions.value.theme = null;
    }
    if (!builder.isDirty.value) loadedRevisions.value.layout = null;
    if (!themeDirty.value) loadedRevisions.value.theme = null;
    historyVersion.value++;
    const message = saveContent ? "site.builder.changesSaved" : !saveLayout ? "site.builder.designSaved"
      : target.value === "component" ? "site.component.saved" : target.value === "page" ? "site.page.saved" : "site.builder.saved";
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
  if (target.value === "page") return api.site.pageRevisions(name.value, pageEntry.value, pageField.value);
  if (target.value === "component") return api.site.componentRevisions(name.value);
  return target.value === "region" ? api.site.regionRevisions(name.value) : api.site.layoutRevisions(name.value, variant.value);
}

function fetchRevision(id) {
  if (target.value === "page") return api.site.pageRevision(name.value, pageEntry.value, pageField.value, id);
  if (target.value === "component") return api.site.componentRevision(name.value, id);
  return target.value === "region" ? api.site.regionRevision(name.value, id) : api.site.layoutRevision(name.value, variant.value, id);
}

function onRevisionLoaded(data, revision) {
  const before = loadedRevisions.value.layout?.before ?? clone(builder.nodes.value);
  loadedRevisions.value.layout = { before, revision, customized: data.customized, nodes: JSON.stringify(data.nodes) };
  builder.loadRevision(data.nodes);
}

/** After the page content was saved or reset: the entry's new version, also for the fields panel. */
function savedPage(data) {
  layoutRevision.value = data.revision;
  pageCustomized.value = data.customized;
  entryFields.setRevision(data.revision);
}

/**
 * Show the default design again as an ordinary edit: it can be undone or
 * cancelled, and saving it removes the custom version (see save()).
 */
function resetToDefault() {
  if (!defaultNodes.value) return;
  const nodes = clone(defaultNodes.value);
  const before = loadedRevisions.value.layout?.before ?? clone(builder.nodes.value);
  loadedRevisions.value.layout = { before, revision: null, customized: false, nodes: JSON.stringify(nodes) };
  builder.loadRevision(nodes);
}

function onSaveMenu(item) {
  if (item.key === "preview") return onSaveMenuSelect(item);
  if (item.key === "reset-page" || item.key === "reset-layout") resetToDefault();
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

/** Entry selection changes the content context without leaving a shared template. */
function selectEntry(entry) {
  if (saving.value || loading.value) return;
  if (target.value !== "page") {
    sampleId.value = entry;
    return;
  }
  if (entry === pageEntryId.value) return;
  goToView({
    key: `entry:${entry}`,
    to: {
      path: `/page-builder/builder/page/${name.value}/${entry}`,
      query: {
        ...(pageField.value ? { field: pageField.value } : {}),
        ...(typeof route.query.back === "string" ? { back: route.query.back } : {}),
      },
    },
  });
}

/** Edit another page content area of the entry, in place. */
function selectPageArea(field) {
  if (target.value !== "page" || saving.value || loading.value || field === requested.field) return;
  goToView({ key: `field:${field}`, field, to: { path: route.path, query: { ...route.query, field } } });
}

function goToView(view) {
  if (view.key === currentView.value) return;
  if (!switching.value && (builder.isDirty.value || entryFields.isDirty.value)) {
    pendingView.value = view;
    return;
  }
  switchView(view);
}

function switchView(view) {
  pendingView.value = null;
  // Another area of the same entry replaces the URL; the route watcher loads it.
  if (view.field) {
    router.replace(view.to);
    return;
  }
  bypass();
  router.push(view.to);
}

/**
 * Reset what belongs to the page that was left: the builder stays mounted
 * between builder pages, so nothing of it is recreated. Pending design edits,
 * open panels, the device and the language carry over.
 */
function leftView() {
  builder.select(null);
  builder.closeContextMenu();
  builder.componentDialog.value = null;
  builder.fieldDialog.value = null;
  selectedTemplatePart.value = null;
  revealedField.value = null;
  pendingView.value = null;
  component.value = null;
  usages.value = [];
  entryFields.discard();
  // The history list belongs to the page that was left.
  historyVersion.value++;
}

// Moving between builder pages (header to footer, layout to page content, back and forward) loads in place.
watch(() => route.fullPath, () => {
  if (!isBuilderPath(route.path)) return;
  const next = contextFor(route);
  if (!sameContext(next, requested)) {
    load(next);
  } else if (typeof route.query.entry === "string" && route.query.entry !== sampleId.value && samples.value.some((sample) => sample.id === route.query.entry)) {
    sampleId.value = route.query.entry;
  }
  if (["design", "css"].includes(route.query.settings)) panelLayout.open(route.query.settings);
});

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

  // Edits to a view on its way out would be lost.
  if (switching.value) return;

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
  clearTimeout(switchingTimer);
  window.removeEventListener("keydown", onKeydown);
  // Design edits the user chose to leave behind must not resurface elsewhere.
  if (!isBuilderPath(router.currentRoute.value.path)) site.discardTheme();
});
</script>

<style scoped>
.builder-topbar {
  --builder-toolbar-text: 255 255 255;
  --builder-toolbar-hover: rgb(255 255 255 / 0.15);
  --builder-toolbar-selected: #fff;
  --builder-toolbar-selected-text: rgb(var(--color-theme-800));
  background: rgb(var(--color-theme-700));
  border-color: rgb(0 0 0 / 0.12);
  color: rgb(var(--builder-toolbar-text));
}
.builder-topbar :deep(:is(button, a, select):focus-visible) {
  outline: 2px solid white;
  outline-offset: 2px;
}
.builder-topbar :deep([role="menu"] :is(button, a):focus-visible) {
  outline-color: rgb(var(--color-theme-500));
  outline-offset: -2px;
}
.topbar-muted {
  color: rgb(var(--builder-toolbar-text) / 0.8);
}
.topbar-variants,
.builder-topbar :deep(.topbar-devices) {
  background: rgb(0 0 0 / 0.12);
}
.topbar-variants .topbar-view,
.builder-topbar :deep(.topbar-devices button) {
  background: transparent;
  color: rgb(var(--builder-toolbar-text) / 0.8);
}
.topbar-variants .topbar-view:hover,
.builder-topbar :deep(.topbar-devices button:hover) {
  background: var(--builder-toolbar-hover);
  color: rgb(var(--builder-toolbar-text));
}
.topbar-variants .topbar-view[aria-pressed="true"],
.builder-topbar :deep(.topbar-devices button[aria-pressed="true"]) {
  background: var(--builder-toolbar-selected);
  color: var(--builder-toolbar-selected-text);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.1);
}
.builder-topbar :deep(.topbar-languages [data-tab-value]) {
  background: transparent;
  color: rgb(var(--builder-toolbar-text) / 0.8);
  --tw-ring-color: rgb(var(--builder-toolbar-text) / 0.3);
}
.builder-topbar :deep(.topbar-languages [data-tab-value]:has([aria-selected="true"])) {
  background: var(--builder-toolbar-selected);
  color: var(--builder-toolbar-selected-text);
  --tw-ring-color: transparent;
}
.topbar-save-badge {
  --tw-ring-color: rgb(var(--color-theme-700));
}
.topbar-action {
  position: relative;
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  color: rgb(var(--builder-toolbar-text) / 0.85);
  transition: background-color 150ms, color 150ms;
}
.topbar-action:hover:not(:disabled) {
  background: var(--builder-toolbar-hover);
  color: rgb(var(--builder-toolbar-text));
}
.topbar-action:disabled {
  opacity: 0.35;
}
.topbar-divider {
  height: 1.25rem;
  width: 1px;
  flex-shrink: 0;
  background: rgb(var(--builder-toolbar-text) / 0.25);
}
.topbar-group {
  height: 2rem;
  min-width: 0;
  align-items: center;
  border-radius: 0.5rem;
  background: rgb(0 0 0 / 0.12);
}
.topbar-group > * + * {
  border-left: 1px solid rgb(var(--builder-toolbar-text) / 0.25);
}
.builder-progress::before {
  content: "";
  position: absolute;
  inset-block: 0;
  width: 30%;
  background: rgb(var(--builder-toolbar-text) / 0.85);
  animation: builder-progress 1s ease-in-out infinite;
}

@keyframes builder-progress {
  from { left: -30%; }
  to { left: 100%; }
}

@media (prefers-reduced-motion: reduce) {
  .builder-progress::before {
    width: 100%;
    animation: none;
    opacity: 0.5;
  }
}

.builder-stage {
  background-color: rgb(var(--color-slate-100));
  background-image: radial-gradient(rgb(var(--color-slate-300) / 0.7) 1px, transparent 1px);
  background-size: 16px 16px;
}
.rail-action {
  touch-action: none;
  position: relative;
  display: flex;
  height: 2.5rem;
  width: 2.5rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.625rem;
  color: rgb(var(--color-slate-500));
  transition: background-color 150ms, color 150ms, transform 100ms;
}
/* Brief press feedback in place of a lingering focus ring. */
.rail-action:active {
  transform: scale(0.92);
  background: rgb(var(--color-slate-200));
}
.rail-action:hover {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
.rail-action.is-active {
  background: rgb(var(--color-theme-50, 239 246 255));
  color: rgb(var(--color-theme-text, 29 78 216));
}
/* Marks open panels on the rail's edge. */
.rail-action.is-active::before {
  content: "";
  position: absolute;
  left: -0.375rem;
  top: 0.625rem;
  bottom: 0.625rem;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: rgb(var(--color-theme-600));
}
.rail-tooltip {
  pointer-events: none;
  position: absolute;
  left: calc(100% + 0.625rem);
  top: 50%;
  z-index: 10;
  width: max-content;
  max-width: 14rem;
  transform: translate(-4px, -50%);
  border-radius: 0.5rem;
  background: rgb(15 23 42);
  padding: 0.375rem 0.625rem;
  text-align: left;
  font-size: 0.75rem;
  line-height: 1.1rem;
  color: #fff;
  box-shadow: 0 8px 24px rgb(15 23 42 / 0.2);
  opacity: 0;
  visibility: hidden;
  transition: opacity 120ms, transform 120ms, visibility 120ms;
}
.rail-action:hover .rail-tooltip,
.rail-action:focus-visible .rail-tooltip {
  opacity: 1;
  visibility: visible;
  transform: translate(0, -50%);
  transition-delay: 500ms;
}
.rail-action.is-tooltip-off .rail-tooltip {
  opacity: 0;
  visibility: hidden;
  transition-delay: 0ms;
}
</style>
