<template>
  <div ref="rootRef" class="relative min-w-0" @focusout="onFocusout">
    <button
      ref="triggerRef"
      type="button"
      class="page-switcher-trigger -mx-1 flex max-w-full items-center gap-1 rounded-md px-1 text-left transition-colors"
      :title="t('site.builder.switchPage')"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-controls="open ? menuId : undefined"
      @click="open ? close() : openMenu()"
      @keydown.down.prevent="openMenu()"
      @keydown.esc.stop.prevent="close(true)"
    >
      <span class="truncate text-sm font-semibold leading-tight">{{ heading }}</span>
      <Icon icon="mdi:chevron-down" class="h-4 w-4 shrink-0 opacity-70 transition-transform" :class="{ 'rotate-180': open }" aria-hidden="true" />
    </button>

    <div
      v-if="open"
      :id="menuId"
      tabindex="-1"
      class="absolute left-0 top-full z-50 mt-1 w-80 overflow-hidden rounded-lg focus:outline-none border border-slate-200 bg-white text-sm shadow-lg"
      @keydown="onMenuKeydown"
    >
      <div v-if="showFilter" class="border-b border-slate-100 p-2">
        <input
          ref="filterRef"
          v-model="filter"
          type="search"
          :placeholder="t('site.builder.filterPages')"
          :aria-label="t('site.builder.filterPages')"
          class="form-input w-full rounded-lg border-slate-300 py-1 text-xs"
        />
      </div>
      <div ref="menuRef" role="menu" tabindex="-1" :aria-label="t('site.builder.switchPage')" class="max-h-[60vh] overflow-y-auto py-1">
        <div v-for="group in groups" :key="group.key" role="group" :aria-labelledby="`${menuId}-${group.key}`">
          <p :id="`${menuId}-${group.key}`" class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ group.label }}</p>
          <button
            v-for="item in group.items"
            :key="item.key"
            type="button"
            role="menuitem"
            tabindex="-1"
            :aria-current="item.current ? 'page' : undefined"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
            :class="[item.current ? 'font-medium text-theme-700' : 'text-slate-700', { 'py-1 pl-8 text-[13px]': item.depth === 1 }]"
            :title="item.title"
            @click="go(item)"
          >
            <Icon :icon="item.icon" class="shrink-0" :class="[item.current ? 'text-theme-600' : 'text-slate-400', item.depth === 1 ? 'h-3.5 w-3.5' : 'h-4 w-4']" aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span v-if="item.detail" class="min-w-0 max-w-[55%] shrink truncate text-xs font-normal text-slate-400">{{ item.detail }}</span>
            <span v-if="item.isHomepage" class="shrink-0 rounded bg-slate-100 px-1.5 py-px text-[10px] font-medium text-slate-500">{{ t("site.overview.homepage") }}</span>
            <Icon v-if="item.current" icon="mdi:check" class="h-4 w-4 shrink-0 text-theme-600" aria-hidden="true" />
          </button>
        </div>
        <p v-if="!groups.length" role="status" class="px-3 py-2 text-xs text-slate-500">{{ t("site.builder.noPages") }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Icon } from "@iconify/vue";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { DEFAULT_AREA, FRAME_DEFAULT, areaLabel, frameLabel, templateList, templateName, usesTemplates } from "../../../composables/templates.js";
import { hasListPage } from "../../../composables/siteLinks.js";
import { useSiteStore } from "../../../stores/site.js";
import { useI18n } from "../../../i18n/index.js";

/** The builder title, opening a menu of every page, region and component to edit instead. */
const props = defineProps({
  heading: { type: String, required: true },
  target: { type: String, required: true },
  name: { type: String, required: true },
});

const REGION_ICONS = { header: "mdi:page-layout-header", footer: "mdi:page-layout-footer" };
const SPECIAL_PAGE_ICONS = { not_found: "mdi:file-question-outline", modal: "mdi:dock-window" };

const route = useRoute();
const router = useRouter();
const builder = useSiteBuilder();
const site = useSiteStore();
const { t } = useI18n();
const rootRef = ref(null);
const triggerRef = ref(null);
const menuRef = ref(null);
const filterRef = ref(null);
const open = ref(false);
const filter = ref("");
const menuId = `page-switcher-${useId()}`;

/**
 * A content type's pages: each page kind and, once it has more than the
 * default one, its templates in order. A template opens its content area; its
 * header and footer are edited under "Site-wide", in Components or from the
 * canvas, so the row only names the parts that differ from the site's.
 */
function typeItems(type) {
  const editing = ["layout", "page"].includes(props.target) && props.name === type.name;
  const variants = hasListPage(builder.settings.value, type.name) ? ["detail", "index"] : ["detail"];
  return variants.flatMap((variant) => {
    const path = `/page-builder/builder/layout/${type.name}/${variant}`;
    const areas = site.data?.content_areas?.[type.name]?.[variant] ?? [];
    const components = builder.components.value;
    const templates = site.draft?.templates ?? site.data?.templates;
    const shown = usesTemplates(templates, areas, type.name, variant) ? templateList(templates, type.name, variant) : [];
    const editingVariant = editing && (props.target === "page" ? variant === "detail" : route.params.variant === variant);
    const editingTemplate = editingVariant && shown.some((template) => template.id === route.query.template);
    return [
      {
        key: `layout:${type.name}:${variant}`,
        label: t(`site.variant.${variant}`),
        icon: variant === "index" ? "mdi:view-grid-outline" : type.icon || "mdi:file-multiple-outline",
        path,
        current: editingVariant && !editingTemplate,
        isHomepage: builder.settings.value?.homepage?.type === type.name && (variant === "index") === !builder.settings.value?.homepage?.entry,
      },
      ...shown.map((template) => {
        const parts = [
          frameLabel(t, template.header, "header", components),
          areaLabel(t, template.content, areas),
          frameLabel(t, template.footer, "footer", components),
        ];
        const distinct = [template.header !== FRAME_DEFAULT && parts[0], template.content !== DEFAULT_AREA && parts[1], template.footer !== FRAME_DEFAULT && parts[2]].filter(Boolean);
        return {
          key: `template:${type.name}:${variant}:${template.id}`,
          label: templateName(t, template),
          detail: distinct.join(" · "),
          title: parts.join(" · "),
          icon: "mdi:page-layout-header-footer",
          depth: 1,
          path,
          query: { area: template.content, template: template.id },
          current: editingVariant && route.query.template === template.id,
        };
      }),
    ];
  });
}

const allGroups = computed(() => [
  ...builder.pageTypes.value.map((type) => ({
    key: `type:${type.name}`,
    label: type.label ?? type.name,
    items: typeItems(type),
  })),
  {
    key: "site",
    label: t("site.builder.siteWide"),
    items: [
      ...Object.keys(site.data?.regions ?? REGION_ICONS).map((region) => [region, REGION_ICONS[region] ?? "mdi:page-layout-body"]),
      ...Object.keys(site.data?.special_pages ?? SPECIAL_PAGE_ICONS).map((page) => [page, SPECIAL_PAGE_ICONS[page] ?? "mdi:file-outline"]),
    ].map(([region, icon]) => ({
      key: `region:${region}`,
      label: t(`site.region.${region}`),
      icon,
      path: `/page-builder/builder/region/${region}`,
      current: props.target === "region" && props.name === region,
    })),
  },
  {
    key: "components",
    label: t("site.builder.components"),
    items: [...builder.components.value]
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((component) => ({
        key: `component:${component.id}`,
        label: component.name,
        icon: "mdi:puzzle-outline",
        path: `/page-builder/builder/component/${component.id}`,
        current: props.target === "component" && props.name === component.id,
      })),
  },
]);

const showFilter = computed(() => allGroups.value.reduce((count, group) => count + group.items.length, 0) > 8);

const groups = computed(() => {
  const terms = filter.value.trim().toLowerCase();
  return allGroups.value
    // Rows no longer repeat their type's name, so a match on the heading keeps the whole group.
    .map((group) => ({ ...group, items: !terms || group.label.toLowerCase().includes(terms) ? group.items : group.items.filter((item) => `${item.label} ${item.detail ?? ""}`.toLowerCase().includes(terms)) }))
    .filter((group) => group.items.length);
});

function menuItems() {
  return [...(menuRef.value?.querySelectorAll('[role="menuitem"]') ?? [])];
}

async function openMenu() {
  filter.value = "";
  open.value = true;
  await nextTick();
  if (filterRef.value) {
    filterRef.value.focus();
    return;
  }
  const items = menuItems();
  (items.find((item) => item.getAttribute("aria-current")) ?? items[0] ?? menuRef.value)?.focus();
}

function close(restoreFocus = false) {
  open.value = false;
  if (restoreFocus) triggerRef.value?.focus();
}

function go(item) {
  close();
  if (item.current) return;
  // Keep where the builder was opened from, so "back" still leads there, and the previewed entry.
  const back = typeof route.query.back === "string" ? route.query.back : "";
  const entry = item.path.startsWith("/page-builder/builder/layout/") && typeof route.query.entry === "string" ? route.query.entry : "";
  router.push({ path: item.path, query: { ...(item.query ?? {}), ...(entry ? { entry } : {}), ...(back ? { back } : {}) } });
}

function onMenuKeydown(event) {
  if (event.key === "Escape") {
    event.preventDefault();
    event.stopPropagation();
    close(true);
    return;
  }
  if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return;
  const inFilter = event.target === filterRef.value;
  if (inFilter && ["Home", "End"].includes(event.key)) return;
  event.preventDefault();
  event.stopPropagation();
  const items = menuItems();
  if (!items.length) return;
  const current = items.indexOf(document.activeElement);
  if (inFilter || current === -1) {
    items[event.key === "ArrowUp" || event.key === "End" ? items.length - 1 : 0].focus();
    return;
  }
  if (event.key === "ArrowUp" && current === 0 && filterRef.value) {
    filterRef.value.focus();
    return;
  }
  const index = event.key === "Home" ? 0
    : event.key === "End" ? items.length - 1
    : (current + (event.key === "ArrowDown" ? 1 : -1) + items.length) % items.length;
  items[index].focus();
}

function onOutsidePointerdown(event) {
  if (!rootRef.value?.contains(event.target)) close();
}

// Also catches clicks into the canvas, which the outside-pointerdown listener cannot see.
function onFocusout(event) {
  if (!rootRef.value?.contains(event.relatedTarget)) close();
}

onMounted(() => document.addEventListener("pointerdown", onOutsidePointerdown));
onBeforeUnmount(() => document.removeEventListener("pointerdown", onOutsidePointerdown));
</script>

<style scoped>
.page-switcher-trigger {
  color: rgb(var(--builder-toolbar-text, var(--color-slate-900)));
}
.page-switcher-trigger:hover,
.page-switcher-trigger[aria-expanded="true"] {
  background: var(--builder-toolbar-hover, rgb(var(--color-slate-100)));
}
</style>
