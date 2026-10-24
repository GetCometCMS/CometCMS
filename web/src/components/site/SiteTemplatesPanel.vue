<template>
  <section class="card p-6">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0 flex-1">
        <h2 class="card-title">{{ t("site.templates.title") }}</h2>
        <p class="card-description">{{ t("site.templates.hint") }}</p>
      </div>
      <div v-if="types.length" class="flex w-full flex-wrap items-end gap-3 sm:w-auto">
        <div class="w-full sm:w-56">
          <label :for="typeSelectId" class="mb-1 block text-xs font-semibold text-slate-700">{{ t("site.templates.type") }}</label>
          <select :id="typeSelectId" v-model="selectedType" class="form-select w-full rounded-lg border-slate-300 text-sm">
            <option v-for="type in types" :key="type.name" :value="type.name">{{ type.label }}</option>
          </select>
        </div>
        <div v-if="variants.length > 1" class="w-full sm:w-56">
          <span class="mb-1 block text-xs font-semibold text-slate-700">{{ t("site.templates.page") }}</span>
          <SegmentedControl v-model="selectedVariant" :options="variants.map((variant) => ({ value: variant, label: t(`site.variant.${variant}`) }))" />
        </div>
      </div>
    </div>

    <p v-if="!types.length" class="py-6 text-sm text-slate-500">{{ t("site.templates.empty") }}</p>
    <div v-else-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
      <p>{{ error }}</p>
      <button type="button" class="btn-secondary btn-sm mt-3" @click="loadComponents">{{ t("common.retry") }}</button>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
      <!-- The page kind's templates, in the order they are tried. -->
      <div class="min-w-0">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3 class="text-xs font-semibold text-slate-700">{{ t("site.templates.order") }}</h3>
          <button type="button" class="btn-secondary btn-sm" :disabled="list.length >= MAX_TEMPLATES" @click="nameDialog = { mode: 'new' }">
            <Icon icon="mdi:plus" class="h-4 w-4" aria-hidden="true" />{{ t("site.templates.new") }}
          </button>
        </div>
        <p class="mb-3 text-xs text-slate-500">{{ t(selectedVariant === "detail" ? "site.templates.orderHint" : "site.templates.orderHintIndex") }}</p>
        <ol class="space-y-1.5" :aria-label="t('site.templates.order')">
          <li
            v-for="(template, index) in list"
            :key="template.id"
            :draggable="!isDefault(template)"
            class="group flex items-center gap-2 rounded-lg border px-2 py-2 transition"
            :class="[template.id === selectedId ? 'border-theme-400 bg-theme-50/60' : 'border-slate-200 bg-white hover:border-theme-300', dropIndex === index ? 'ring-2 ring-theme-400' : '']"
            @dragstart="dragId = template.id"
            @dragend="dragId = null; dropIndex = null"
            @dragover.prevent="dropIndex = isDefault(template) ? list.length - 1 : index"
            @drop.prevent="drop(index)"
          >
            <Icon :icon="isDefault(template) ? 'mdi:pin-outline' : 'mdi:drag-vertical'" class="h-4 w-4 shrink-0 text-slate-400" :class="{ 'cursor-grab': !isDefault(template) }" aria-hidden="true" />
            <button type="button" class="min-w-0 flex-1 text-left" :aria-current="template.id === selectedId || undefined" @click="selectedId = template.id">
              <span class="block truncate text-sm font-medium text-slate-900">{{ templateName(t, template) }}</span>
              <span class="block truncate text-xs text-slate-500">{{ summary(template) }}</span>
            </button>
            <span v-if="!isDefault(template)" class="flex shrink-0 opacity-60 group-hover:opacity-100 group-focus-within:opacity-100">
              <button type="button" class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 disabled:pointer-events-none disabled:opacity-40" :disabled="index === 0" :title="t('site.templates.moveUp')" @click="move(template, index - 1)">
                <Icon icon="mdi:chevron-up" class="h-4 w-4" aria-hidden="true" /><span class="sr-only">{{ t("site.templates.moveUp") }}: {{ templateName(t, template) }}</span>
              </button>
              <button type="button" class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 disabled:pointer-events-none disabled:opacity-40" :disabled="index >= list.length - 2" :title="t('site.templates.moveDown')" @click="move(template, index + 1)">
                <Icon icon="mdi:chevron-down" class="h-4 w-4" aria-hidden="true" /><span class="sr-only">{{ t("site.templates.moveDown") }}: {{ templateName(t, template) }}</span>
              </button>
            </span>
          </li>
        </ol>
      </div>

      <!-- The selected template: its parts and when it applies. -->
      <div v-if="selected" class="min-w-0 space-y-5">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900">{{ templateName(t, selected) }}</h3>
          <button v-if="!isDefault(selected)" type="button" class="btn-secondary btn-sm" @click="nameDialog = { mode: 'rename' }">
            <Icon icon="mdi:form-textbox" class="h-4 w-4" aria-hidden="true" />{{ t("site.templates.rename") }}
          </button>
          <button type="button" class="btn-secondary btn-sm" :disabled="list.length >= MAX_TEMPLATES" @click="nameDialog = { mode: 'duplicate' }">
            <Icon icon="mdi:content-copy" class="h-4 w-4" aria-hidden="true" />{{ t("site.templates.duplicate") }}
          </button>
          <button v-if="!isDefault(selected)" type="button" class="btn-danger-subtle btn-sm" @click="deleteOpen = true">
            <Icon icon="mdi:trash-can-outline" class="h-4 w-4" aria-hidden="true" />{{ t("common.delete") }}
          </button>
        </div>

        <div class="template-preview" @keydown.esc="openPicker = null">
          <div v-for="part in PARTS" :key="part" class="relative" :data-template-part="part">
            <button
              type="button"
              class="template-preview__part"
              :class="[`template-preview__part--${part}`, { 'is-open': openPicker === part, 'is-empty': selected[part] === FRAME_NONE }]"
              :aria-expanded="openPicker === part"
              :aria-controls="`${pickerId}-${part}`"
              @click="openPicker = openPicker === part ? null : part"
            >
              <!-- Decorative sample layout; the assigned part remains the source of truth. -->
              <span v-if="selected[part] !== FRAME_NONE" class="template-preview__sample" aria-hidden="true">
                <span v-if="part === 'header'" class="preview-header">
                  <span class="preview-logo"></span>
                  <span class="preview-nav"><span></span><span></span><span></span></span>
                  <span class="preview-action"></span>
                </span>
                <span v-else-if="part === 'content'" class="preview-content">
                  <span class="preview-hero">
                    <svg viewBox="0 0 160 90" fill="currentColor" focusable="false">
                      <circle cx="111" cy="22" r="11" />
                      <path d="M24 66 57 32a7 7 0 0 1 10 0l24 25 11-11a7 7 0 0 1 10 0l22 24H27a3 3 0 0 1-3-4Z" />
                    </svg>
                  </span>
                  <span class="preview-copy"><span></span><span></span><span></span></span>
                  <span class="preview-cards">
                    <span v-for="card in 3" :key="card" class="preview-card">
                      <span class="preview-card__image"><span></span></span>
                      <span class="preview-card__line"></span>
                      <span class="preview-card__line preview-card__line--short"></span>
                    </span>
                  </span>
                </span>
                <span v-else class="preview-footer">
                  <span class="preview-social"><span></span><span></span><span></span></span>
                  <span class="preview-nav"><span></span><span></span><span></span></span>
                  <span class="preview-social"><span></span><span></span><span></span></span>
                </span>
              </span>
              <span class="template-preview__badge">
                <span class="template-preview__kind">{{ t(`site.templates.part.${part}`) }}</span>
                <span class="template-preview__name">{{ partLabel(part) }}</span>
              </span>
              <span class="template-preview__assignment">
                <span class="template-preview__change">{{ t("site.templates.change") }}</span>
                <Icon icon="mdi:chevron-down" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              </span>
            </button>

            <div
              v-if="openPicker === part"
              :id="`${pickerId}-${part}`"
              class="absolute inset-x-0 z-20 mt-1 rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
              :class="part === 'footer' ? 'bottom-full mb-1' : 'top-full'"
            >
              <ul role="listbox" :aria-label="t(`site.templates.part.${part}`)">
                <li v-for="option in options(part)" :key="option.value" class="flex items-center">
                  <button
                    type="button"
                    role="option"
                    :aria-selected="selected[part] === option.value"
                    class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-theme-50"
                    :class="selected[part] === option.value ? 'font-semibold text-theme-700' : 'text-slate-700'"
                    @click="assign(part, option.value)"
                  >
                    <Icon :icon="option.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                    <Icon v-if="selected[part] === option.value" icon="mdi:check" class="h-4 w-4 shrink-0" aria-hidden="true" />
                  </button>
                  <button
                    v-if="option.deletable"
                    type="button"
                    class="mr-1 rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 disabled:pointer-events-none disabled:opacity-40"
                    :title="t('site.templates.deleteArea', { name: option.label })"
                    @click="areaToDelete = option"
                  >
                    <Icon icon="mdi:trash-can-outline" class="h-4 w-4" aria-hidden="true" /><span class="sr-only">{{ t("site.templates.deleteArea", { name: option.label }) }}</span>
                  </button>
                </li>
              </ul>
              <p v-if="part !== 'content' && loading" class="px-3 py-2 text-xs text-slate-500">{{ t("common.loading") }}</p>
              <p v-else-if="part !== 'content' && !frameComponents(components, selectedType).length" class="px-3 py-2 text-xs text-slate-500">{{ t("site.frame.noComponents") }}</p>
              <div class="mt-1 border-t border-slate-100 pt-1">
                <button v-if="part === 'content'" type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" @click="nameDialog = { mode: 'area' }">
                  <Icon icon="mdi:file-document-plus-outline" class="h-4 w-4" aria-hidden="true" />{{ t("site.templates.newAreaFrom", { name: partLabel("content") }) }}
                </button>
                <router-link v-if="selected[part] !== FRAME_NONE" :to="editPath(part)" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                  <Icon icon="mdi:pencil-outline" class="h-4 w-4" aria-hidden="true" />{{ t("site.frame.editComponent", { name: partLabel(part) }) }}
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- When the template applies. -->
        <div class="rounded-xl border border-slate-200 p-4">
          <h4 class="text-xs font-semibold text-slate-700">{{ t("site.templates.conditions") }}</h4>
          <p v-if="isDefault(selected)" class="mt-1 text-xs text-slate-500">{{ t("site.templates.defaultHint") }}</p>
          <p v-else-if="selectedVariant !== 'detail'" class="mt-1 text-xs text-slate-500">{{ t("site.templates.indexConditionsHint") }}</p>
          <template v-else>
            <p class="mt-1 text-xs text-slate-500">{{ selected.conditions.length ? t("site.templates.conditionsHint") : t("site.templates.noConditionsHint") }}</p>
            <ul class="mt-3 space-y-2">
              <li v-for="(condition, index) in selected.conditions" :key="index" class="flex flex-wrap items-center gap-2">
                <select
                  class="form-select min-w-0 flex-1 rounded-lg border-slate-300 py-1.5 text-sm sm:max-w-48"
                  :value="condition.field"
                  :aria-label="t('site.templates.conditionField')"
                  @change="changeCondition(index, { field: $event.target.value })"
                >
                  <option v-for="field in fields" :key="field.key" :value="field.key">{{ field.label }}</option>
                </select>
                <select
                  class="form-select min-w-0 flex-1 rounded-lg border-slate-300 py-1.5 text-sm sm:max-w-44"
                  :value="condition.op"
                  :aria-label="t('site.templates.conditionOp')"
                  @change="changeCondition(index, { op: $event.target.value })"
                >
                  <option v-for="op in conditionOps(fieldType(condition.field))" :key="op" :value="op">{{ t(`site.templates.opName.${op}`) }}</option>
                </select>
                <template v-if="needsValue(condition.op)">
                  <select
                    v-if="valueOptions(condition.field)"
                    class="form-select min-w-0 flex-1 rounded-lg border-slate-300 py-1.5 text-sm"
                    :value="String(condition.value ?? '')"
                    :aria-label="t('site.templates.conditionValue')"
                    @change="changeCondition(index, { value: parseValue(condition.field, $event.target.value) })"
                  >
                    <option v-for="option in valueOptions(condition.field)" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                  <input
                    v-else
                    :type="['number', 'range'].includes(fieldType(condition.field)) ? 'number' : 'text'"
                    class="form-input min-w-0 flex-1 rounded-lg border-slate-300 py-1.5 text-sm"
                    :value="condition.value ?? ''"
                    maxlength="500"
                    :aria-label="t('site.templates.conditionValue')"
                    @change="changeCondition(index, { value: parseValue(condition.field, $event.target.value) })"
                  />
                </template>
                <button type="button" class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 disabled:pointer-events-none disabled:opacity-40" :title="t('site.templates.removeCondition')" @click="removeCondition(index)">
                  <Icon icon="mdi:close" class="h-4 w-4" aria-hidden="true" /><span class="sr-only">{{ t("site.templates.removeCondition") }}</span>
                </button>
              </li>
            </ul>
            <button type="button" class="btn-secondary btn-sm mt-3" :disabled="selected.conditions.length >= MAX_CONDITIONS || !fields.length" @click="addCondition">
              <Icon icon="mdi:plus" class="h-4 w-4" aria-hidden="true" />{{ t("site.templates.addCondition") }}
            </button>
          </template>
        </div>
      </div>
    </div>

    <ComponentNameModal
      :model-value="nameDialog !== null"
      :title="nameDialogText.title"
      :confirm-label="nameDialogText.confirm"
      :label="nameDialogText.label"
      :placeholder="nameDialogText.placeholder"
      :initial="nameDialogText.initial"
      :icon="nameDialog?.mode === 'area' ? 'mdi:file-document-plus-outline' : 'mdi:page-layout-header-footer'"
      :loading="creatingArea"
      @update:model-value="(open) => { if (!open) nameDialog = null }"
      @confirm="confirmName"
    />
    <ConfirmModal
      :model-value="deleteOpen"
      :title="t('site.templates.deleteTitle', { name: selected ? templateName(t, selected) : '' })"
      :message="t('site.templates.deleteMessage')"
      :confirm-label="t('common.delete')"
      @update:model-value="deleteOpen = $event"
      @confirm="removeSelected"
    />
    <ConfirmModal
      :model-value="areaToDelete !== null"
      :title="t('site.templates.deleteArea', { name: areaToDelete?.label ?? '' })"
      :message="t('site.templates.deleteAreaMessage')"
      :confirm-label="t('common.delete')"
      :loading="deletingArea"
      @update:model-value="(open) => { if (!open) areaToDelete = null }"
      @confirm="deleteArea"
    />
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from "vue";
import { useRoute } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../ConfirmModal.vue";
import ComponentNameModal from "./ComponentNameModal.vue";
import SegmentedControl from "./SegmentedControl.vue";
import { api } from "../../api/index.js";
import { selectOptions } from "../../composables/fieldBuilderUtils.js";
import {
  DEFAULT_AREA, DEFAULT_TEMPLATE, FRAME_DEFAULT, FRAME_NONE, MAX_CONDITIONS, MAX_TEMPLATES,
  areaLabel, conditionFields, conditionLabel, conditionOps, frameComponents, frameLabel, needsValue,
  templateList, templateName, withNewTemplate, withTemplate, withTemplateMoved, withoutTemplate,
} from "../../composables/templates.js";
import { useSiteStore } from "../../stores/site.js";
import { useToastStore } from "../../stores/toast.js";
import { useI18n } from "../../i18n/index.js";

const PARTS = ["header", "content", "footer"];
const PART_ICONS = { header: "mdi:page-layout-header", content: "mdi:file-document-outline", footer: "mdi:page-layout-footer" };
const BACK = "/page-builder/templates";

const route = useRoute();
const site = useSiteStore();
const toast = useToastStore();
const { t } = useI18n();
const typeSelectId = useId();
const pickerId = useId();

const components = ref([]);
const loading = ref(false);
const error = ref("");
const openPicker = ref(null);
let active = true;

/** Types with website pages: the ones that have layouts. */
const types = computed(() => (site.data?.types ?? []).filter((type) => site.data?.layouts?.[type.name]));
const queryType = typeof route.query.type === "string" ? route.query.type : "";
const homepageType = site.data?.settings?.homepage?.type;
const selectedType = ref([queryType, homepageType].find((name) => types.value.some((type) => type.name === name)) ?? types.value[0]?.name ?? "");
const variants = computed(() => ["detail", "index"].filter((variant) => variant in (site.data?.layouts?.[selectedType.value] ?? {})));
const selectedVariant = ref(route.query.variant === "index" ? "index" : "detail");
watch(variants, (available) => {
  if (!available.includes(selectedVariant.value)) selectedVariant.value = available[0] ?? "detail";
}, { immediate: true });

const schema = computed(() => types.value.find((type) => type.name === selectedType.value) ?? null);
const list = computed(() => templateList(site.draft?.templates, selectedType.value, selectedVariant.value));
const areas = computed(() => site.data?.content_areas?.[selectedType.value]?.[selectedVariant.value] ?? [{ id: DEFAULT_AREA, name: "Default" }]);
const selectedId = ref(typeof route.query.template === "string" ? route.query.template : DEFAULT_TEMPLATE);
const selected = computed(() => list.value.find((template) => template.id === selectedId.value) ?? list.value[list.value.length - 1]);
watch([selectedType, selectedVariant], () => {
  openPicker.value = null;
  if (!list.value.some((template) => template.id === selectedId.value)) selectedId.value = DEFAULT_TEMPLATE;
});

const isDefault = (template) => template.id === DEFAULT_TEMPLATE;

/** Change the draft templates of the selected page kind. */
function update(templates) {
  if (site.draft) site.draft.templates = templates;
}
const change = (changes) => update(withTemplate(site.draft?.templates, selectedType.value, selectedVariant.value, selected.value.id, changes));

function summary(template) {
  if (isDefault(template)) return t("site.templates.defaultSummary");
  if (!template.conditions.length) return t("site.templates.always");
  return template.conditions.map((condition) => conditionLabel(t, condition, schema.value)).join(", ");
}

// ─── Order ───────────────────────────────────────────────────────────────────

const dragId = ref(null);
const dropIndex = ref(null);
function move(template, index) {
  update(withTemplateMoved(site.draft?.templates, selectedType.value, selectedVariant.value, template.id, index));
}
function drop(index) {
  if (dragId.value) move({ id: dragId.value }, Math.min(index, list.value.length - 2));
  dragId.value = null;
  dropIndex.value = null;
}

// ─── Parts ───────────────────────────────────────────────────────────────────

function partLabel(part) {
  const value = selected.value[part];
  return part === "content" ? areaLabel(t, value, areas.value) : frameLabel(t, value, part, components.value);
}

/** Content areas some template of the draft still shows cannot be deleted. */
const usedAreas = computed(() => new Set(list.value.map((template) => template.content)));

function options(part) {
  if (part === "content") {
    return areas.value.map((area) => ({
      value: area.id,
      label: areaLabel(t, area.id, areas.value),
      icon: "mdi:file-document-outline",
      deletable: area.id !== DEFAULT_AREA && !usedAreas.value.has(area.id),
    }));
  }
  return [
    { value: FRAME_DEFAULT, label: t(`site.frame.default.${part}`), icon: PART_ICONS[part] },
    { value: FRAME_NONE, label: t(`site.frame.none.${part}`), icon: "mdi:cancel" },
    ...frameComponents(components.value, selectedType.value).map((component) => ({ value: component.id, label: component.name, icon: "mdi:puzzle-outline" })),
  ];
}

function assign(part, value) {
  change({ [part]: value });
  openPicker.value = null;
}

function editPath(part) {
  const value = selected.value[part];
  if (part === "content") {
    return { path: `/page-builder/builder/layout/${encodeURIComponent(selectedType.value)}/${selectedVariant.value}`, query: { area: value, template: selected.value.id, back: BACK } };
  }
  const path = value === FRAME_DEFAULT ? `/page-builder/builder/region/${part}` : `/page-builder/builder/component/${encodeURIComponent(value)}`;
  return { path, query: { back: BACK } };
}

const areaToDelete = ref(null);
const deletingArea = ref(false);
async function deleteArea() {
  const area = areaToDelete.value;
  if (!area || deletingArea.value) return;
  deletingArea.value = true;
  try {
    await api.site.deleteContentArea(selectedType.value, selectedVariant.value, area.value);
    site.setContentAreas(selectedType.value, selectedVariant.value, areas.value.filter((item) => item.id !== area.value));
    areaToDelete.value = null;
  } catch (err) {
    toast.error(err.message);
  } finally {
    deletingArea.value = false;
  }
}

// ─── Conditions ──────────────────────────────────────────────────────────────

const fields = computed(() => conditionFields(schema.value));
const fieldType = (key) => fields.value.find((field) => field.key === key)?.type ?? "";

/** Fixed choices for a condition's value: a select field's options, or yes/no. */
function valueOptions(key) {
  const type = fieldType(key);
  if (type === "boolean") return [{ value: "true", label: t("site.templates.true") }, { value: "false", label: t("site.templates.false") }];
  if (type === "select") return selectOptions(schema.value?.fields?.[key]?.options);
  return null;
}

function parseValue(key, value) {
  const type = fieldType(key);
  if (type === "boolean") return value === "true";
  if (["number", "range"].includes(type) && value !== "" && Number.isFinite(Number(value))) return Number(value);
  return value;
}

function initialValue(key) {
  return valueOptions(key)?.[0] ? parseValue(key, valueOptions(key)[0].value) : "";
}

function addCondition() {
  const field = fields.value[0];
  if (!field) return;
  const op = conditionOps(field.type)[0];
  change({ conditions: [...selected.value.conditions, { field: field.key, op, value: needsValue(op) ? initialValue(field.key) : null }] });
}

function changeCondition(index, changes) {
  const conditions = selected.value.conditions.map((condition, position) => {
    if (position !== index) return condition;
    const next = { ...condition, ...changes };
    // A new field may not allow the old comparison or value.
    if (changes.field) {
      const ops = conditionOps(fieldType(next.field));
      if (!ops.includes(next.op)) next.op = ops[0];
      next.value = initialValue(next.field);
    }
    if (!needsValue(next.op)) next.value = null;
    else if (next.value === null) next.value = initialValue(next.field);
    return { field: next.field, op: next.op, value: next.value };
  });
  change({ conditions });
}

function removeCondition(index) {
  change({ conditions: selected.value.conditions.filter((_, position) => position !== index) });
}

// ─── Names ───────────────────────────────────────────────────────────────────

/** The name dialog: a new or duplicated template, a rename, or a new content area. */
const nameDialog = ref(null);
const nameDialogText = computed(() => {
  const mode = nameDialog.value?.mode;
  if (mode === "area") {
    return { title: t("site.templates.newAreaTitle"), confirm: t("site.templates.createArea"), label: t("site.templates.areaName"), placeholder: t("site.templates.areaNamePlaceholder"), initial: "" };
  }
  return {
    title: t(mode === "rename" ? "site.templates.renameTitle" : mode === "duplicate" ? "site.templates.duplicateTitle" : "site.templates.newTitle"),
    confirm: t(mode === "rename" ? "site.templates.rename" : "site.templates.create"),
    label: t("site.templates.name"),
    placeholder: t("site.templates.namePlaceholder"),
    initial: mode === "rename" ? selected.value?.name ?? "" : mode === "duplicate" ? t("site.templates.copyOf", { name: templateName(t, selected.value) }) : "",
  };
});

const creatingArea = ref(false);
async function confirmName(name) {
  const mode = nameDialog.value?.mode;
  if (mode === "area") {
    if (creatingArea.value) return;
    creatingArea.value = true;
    try {
      const created = (await api.site.createContentArea(selectedType.value, selectedVariant.value, name, selected.value.content)).data;
      site.setContentAreas(selectedType.value, selectedVariant.value, [...areas.value, created]);
      change({ content: created.id });
      nameDialog.value = null;
      openPicker.value = null;
    } catch (err) {
      toast.error(err.message);
    } finally {
      creatingArea.value = false;
    }
    return;
  }
  if (mode === "rename") {
    change({ name });
  } else {
    const result = withNewTemplate(site.draft?.templates, selectedType.value, selectedVariant.value, name, mode === "duplicate" ? selected.value : null);
    update(result.templates);
    selectedId.value = result.id;
  }
  nameDialog.value = null;
}

const deleteOpen = ref(false);
function removeSelected() {
  update(withoutTemplate(site.draft?.templates, selectedType.value, selectedVariant.value, selected.value.id));
  selectedId.value = DEFAULT_TEMPLATE;
  deleteOpen.value = false;
}

// ─── Loading ─────────────────────────────────────────────────────────────────

async function loadComponents() {
  loading.value = true;
  error.value = "";
  try {
    const response = await api.site.components();
    if (active) components.value = response.data;
  } catch (err) {
    if (active) error.value = err.message;
  } finally {
    if (active) loading.value = false;
  }
}

/** A click outside the open picker's part closes it. */
function onPointerDown(event) {
  if (openPicker.value && !event.target.closest?.(`[data-template-part="${openPicker.value}"]`)) openPicker.value = null;
}

onMounted(() => {
  loadComponents();
  document.addEventListener("pointerdown", onPointerDown);
});
onBeforeUnmount(() => {
  active = false;
  document.removeEventListener("pointerdown", onPointerDown);
});
</script>


<style scoped>
.template-preview {
  width: 100%;
  max-width: 38rem;
  margin-inline: auto;
  padding: 12px;
  display: grid;
  gap: 12px;
  border: 1px solid rgb(var(--color-slate-300) / 0.7);
  border-radius: 18px;
  background: rgb(var(--color-slate-50));
  box-shadow: inset 0 0 24px rgb(var(--color-slate-200) / 0.25);
}

.template-preview__part {
  --part-color: 14 165 233;
  --part-label: #0284c7;
  position: relative;
  display: block;
  width: 100%;
  min-width: 0;
  height: 88px;
  border: 1.5px dashed rgb(var(--part-color));
  border-radius: 12px;
  background: linear-gradient(135deg, rgb(var(--part-color) / 0.09), rgb(var(--part-color) / 0.025));
  text-align: left;
  transition: background-color 150ms, box-shadow 150ms;
}

.template-preview__part--content {
  --part-color: 167 139 250;
  --part-label: #7c3aed;
  height: auto;
  min-height: 300px;
}

.template-preview__part--footer {
  --part-color: 20 184 166;
  --part-label: #0d9488;
}

.template-preview__part:hover,
.template-preview__part.is-open {
  background-color: rgb(var(--part-color) / 0.08);
  box-shadow: 0 0 0 3px rgb(var(--part-color) / 0.1), inset 0 0 24px rgb(var(--part-color) / 0.06);
}

.template-preview__part:focus-visible {
  outline: 2px solid rgb(var(--part-color));
  outline-offset: 4px;
}

.template-preview__part.is-empty {
  background: transparent;
}

.template-preview__badge {
  position: absolute;
  z-index: 1;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  width: max-content;
  max-width: calc(100% - 24px);
  padding: 5px 18px 6px;
  border: 1px solid rgb(var(--part-color));
  border-radius: 12px;
  text-align: center;
  background: linear-gradient(rgb(255 255 255 / 0.16), transparent), var(--part-label);
  box-shadow: 0 0 0 4px rgb(var(--part-color) / 0.08), 0 3px 10px rgb(var(--part-color) / 0.2);
  color: #fff;
  font-size: 12px;
  font-weight: 500;
  line-height: 18px;
}

.template-preview__kind {
  font-size: 10px;
  font-weight: 500;
  line-height: 14px;
  color: rgb(255 255 255 / 0.8);
}

.template-preview__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14px;
  font-weight: 600;
  line-height: 20px;
}

.template-preview__part--content .template-preview__badge {
  top: 50%;
  transform: translate(-50%, -50%);
}

.template-preview__assignment {
  position: absolute;
  right: 10px;
  bottom: 7px;
  left: 10px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 7px;
  color: rgb(var(--color-slate-600));
  font-size: 11px;
  line-height: 16px;
}

.template-preview__change {
  flex-shrink: 0;
  color: rgb(var(--color-slate-800));
  font-weight: 600;
}

.template-preview__sample {
  display: block;
  pointer-events: none;
  color: rgb(var(--color-slate-400) / 0.3);
}

.preview-header,
.preview-footer {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 28px 20px;
}

.preview-logo {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 50%;
  background: rgb(var(--part-color) / 0.3);
}

.preview-nav {
  display: flex;
  flex: 1;
  gap: 14px;
}

.preview-nav > span,
.preview-copy > span,
.preview-card__line {
  display: block;
  height: 8px;
  border-radius: 5px;
  background: currentColor;
}

.preview-nav > span {
  width: 22%;
  max-width: 48px;
}

.preview-action {
  width: 22%;
  height: 23px;
  border-radius: 5px;
  background: rgb(var(--part-color) / 0.2);
}

.preview-content {
  display: grid;
  gap: 16px;
  padding: 22px 28px 36px;
}

.preview-hero {
  display: flex;
  height: 88px;
  justify-content: center;
  border-radius: 7px;
  background: currentColor;
}

.preview-hero svg {
  width: 140px;
  color: rgb(var(--color-slate-400) / 0.4);
}

.preview-copy {
  display: grid;
  gap: 8px;
}

.preview-copy > span:nth-child(2) { width: 91%; }
.preview-copy > span:nth-child(3) { width: 76%; }

.preview-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.preview-card {
  display: grid;
  gap: 8px;
}

.preview-card__image {
  display: grid;
  height: 50px;
  place-items: center;
  border-radius: 5px;
  background: currentColor;
}

.preview-card__image > span {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  background: currentColor;
  transform: rotate(45deg);
}

.preview-card__line { width: 80%; }
.preview-card__line--short { width: 56%; }

.preview-footer {
  gap: 22px;
  padding-top: 30px;
}

.preview-social {
  display: flex;
  gap: 9px;
}

.preview-social > span {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: currentColor;
}

@media (max-width: 480px) {
  .preview-header, .preview-footer { gap: 12px; padding-inline: 14px; }
  .preview-content { padding-inline: 18px; }
  .preview-cards { gap: 10px; }
  .preview-social { gap: 5px; }
  .preview-social > span { width: 8px; height: 8px; }
  .preview-nav { gap: 8px; }
}
</style>
