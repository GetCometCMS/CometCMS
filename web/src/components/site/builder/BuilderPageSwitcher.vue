<template>
  <div ref="rootRef" class="relative min-w-0" @focusout="onFocusout">
    <button
      ref="triggerRef"
      type="button"
      class="-mx-1 flex max-w-full items-center gap-1 rounded-md px-1 text-left transition-colors hover:bg-slate-100"
      :title="t('site.builder.switchPage')"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-controls="open ? menuId : undefined"
      @click="open ? close() : openMenu()"
      @keydown.down.prevent="openMenu()"
      @keydown.esc.stop.prevent="close(true)"
    >
      <span class="truncate text-sm font-semibold leading-tight text-slate-900">{{ heading }}</span>
      <Icon icon="mdi:chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="{ 'rotate-180': open }" aria-hidden="true" />
    </button>

    <div
      v-if="open"
      :id="menuId"
      tabindex="-1"
      class="absolute left-0 top-full z-50 mt-1 w-72 overflow-hidden rounded-lg focus:outline-none border border-slate-200 bg-white text-sm shadow-lg"
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
            :class="item.current ? 'font-medium text-theme-700' : 'text-slate-700'"
            @click="go(item)"
          >
            <Icon :icon="item.icon" class="h-4 w-4 shrink-0" :class="item.current ? 'text-theme-600' : 'text-slate-400'" aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span v-if="item.isHomepage" class="shrink-0 rounded bg-slate-100 px-1.5 py-px text-[10px] font-medium text-slate-500">{{ t("site.overview.homepage") }}</span>
            <span v-if="item.isDefault" class="shrink-0 rounded bg-slate-100 px-1.5 py-px text-[10px] font-medium text-slate-500">{{ t("site.pages.default") }}</span>
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
import { useSiteStore } from "../../../stores/site.js";
import { useI18n } from "../../../i18n/index.js";

/** The builder title, opening a menu of every page, region and component to edit instead. */
const props = defineProps({
  heading: { type: String, required: true },
  target: { type: String, required: true },
  name: { type: String, required: true },
});

const REGION_ICONS = { header: "mdi:page-layout-header", footer: "mdi:page-layout-footer" };

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

const allGroups = computed(() => [
  {
    key: "pages",
    label: t("site.builder.pages"),
    items: builder.types.value.map((type) => ({
      key: `layout:${type.name}`,
      label: type.label ?? type.name,
      icon: type.singleton ? "mdi:file-document-outline" : "mdi:file-multiple-outline",
      path: `/page-builder/builder/layout/${type.name}/detail`,
      current: props.target === "layout" && props.name === type.name,
      isHomepage: site.data?.settings?.homepage?.type === type.name,
      isDefault: site.data?.layouts?.[type.name]?.detail === false,
    })),
  },
  {
    key: "regions",
    label: t("site.builder.regions"),
    items: Object.keys(site.data?.regions ?? REGION_ICONS).map((region) => ({
      key: `region:${region}`,
      label: t(`site.region.${region}`),
      icon: REGION_ICONS[region] ?? "mdi:page-layout-body",
      path: `/page-builder/builder/region/${region}`,
      current: props.target === "region" && props.name === region,
      isDefault: site.data?.regions?.[region] === false,
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
        isDefault: false,
      })),
  },
]);

const showFilter = computed(() => allGroups.value.reduce((count, group) => count + group.items.length, 0) > 8);

const groups = computed(() => {
  const terms = filter.value.trim().toLowerCase();
  return allGroups.value
    .map((group) => ({ ...group, items: terms ? group.items.filter((item) => item.label.toLowerCase().includes(terms)) : group.items }))
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
  // Keep where the builder was opened from, so "back" still leads there.
  const back = typeof route.query.back === "string" ? route.query.back : "";
  router.push({ path: item.path, query: back ? { back } : {} });
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
