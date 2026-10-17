<template>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <!-- Menu list -->
    <aside class="card h-fit p-3">
      <ul class="space-y-1">
        <li v-for="menu in menus" :key="menu.name">
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors"
            :class="menu.name === selectedName ? 'bg-theme-50 font-medium text-theme-700' : 'text-slate-700 hover:bg-slate-50'"
            @click="selectedName = menu.name"
          >
            <Icon icon="mdi:menu" class="h-4 w-4 shrink-0 opacity-60" />
            <span class="min-w-0 flex-1 truncate">{{ menu.label }}</span>
            <span class="text-xs text-slate-400">{{ menu.items.length }}</span>
          </button>
        </li>
      </ul>
      <form class="mt-3 flex gap-2 border-t border-slate-100 pt-3" @submit.prevent="addMenu">
        <input
          v-model="newMenuLabel"
          type="text"
          class="form-input min-w-0 flex-1 rounded-lg border-slate-300 text-sm"
          :placeholder="t('site.menus.newMenu')"
          :aria-label="t('site.menus.newMenu')"
        />
        <button type="submit" class="btn-secondary btn-sm" :disabled="!newMenuLabel.trim()">
          <Icon icon="mdi:plus" class="h-4 w-4" />
          <span class="sr-only">{{ t("site.menus.addMenu") }}</span>
        </button>
      </form>
    </aside>

    <!-- Menu editor -->
    <section v-if="selected" class="card p-5">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
          <label class="form-label" for="menu-label">{{ t("site.menus.label") }}</label>
          <input id="menu-label" v-model="selected.label" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div class="text-xs text-slate-500">
          {{ t("site.menus.name") }} <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono">{{ selected.name }}</code>
        </div>
        <button type="button" class="btn-danger-subtle btn-sm" @click="deleteOpen = true">
          <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
          {{ t("site.menus.deleteMenu") }}
        </button>
      </div>

      <p class="mt-4 text-sm text-slate-500">{{ t("site.menus.hint") }}</p>

      <ol class="mt-4 space-y-3">
        <li v-for="(item, index) in selected.items" :key="item.id" class="rounded-xl border border-slate-200 bg-white">
          <MenuItemEditor
            :item="item"
            :types="site.data.types"
            :locales="extraLocales"
            :first="index === 0"
            :last="index === selected.items.length - 1"
            @move="(delta) => move(selected.items, index, delta)"
            @remove="selected.items.splice(index, 1)"
          />
          <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-3 pl-8">
            <ol v-if="item.children.length" class="mb-2 space-y-2">
              <li v-for="(child, childIndex) in item.children" :key="child.id" class="rounded-lg border border-slate-200 bg-white">
                <MenuItemEditor
                  :item="child"
                  :types="site.data.types"
                  :locales="extraLocales"
                  :first="childIndex === 0"
                  :last="childIndex === item.children.length - 1"
                  compact
                  @move="(delta) => move(item.children, childIndex, delta)"
                  @remove="item.children.splice(childIndex, 1)"
                />
              </li>
            </ol>
            <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-theme-700 hover:text-theme-800" @click="item.children.push(newItem())">
              <Icon icon="mdi:subdirectory-arrow-right" class="h-4 w-4" />
              {{ t("site.menus.addChild") }}
            </button>
          </div>
        </li>
      </ol>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <button type="button" class="btn-secondary btn-sm" @click="selected.items.push(newItem())">
          <Icon icon="mdi:plus" class="h-4 w-4" />
          {{ t("site.menus.addItem") }}
        </button>
        <select
          v-if="quickLinks.length"
          class="form-select rounded-lg border-slate-300 py-1.5 text-xs"
          :aria-label="t('site.menus.quickAdd')"
          @change="quickAdd($event)"
        >
          <option value="">{{ t("site.menus.quickAdd") }}</option>
          <option v-for="(option, index) in quickLinks" :key="index" :value="index">{{ option.label }}</option>
        </select>
      </div>
    </section>

    <section v-else class="card p-6 text-sm text-slate-500">{{ t("site.menus.empty") }}</section>

    <ConfirmModal
      v-model="deleteOpen"
      :title="t('site.menus.deleteTitle')"
      :message="t('site.menus.deleteMessage', { name: selected?.label ?? '' })"
      :confirm-label="t('site.menus.deleteMenu')"
      @confirm="deleteMenu"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../ConfirmModal.vue";
import MenuItemEditor from "./MenuItemEditor.vue";
import { newId } from "../../composables/layoutTree.js";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

const site = useSiteStore();
const { t } = useI18n();
const menus = computed(() => site.draft.menus);
const selectedName = ref(menus.value[0]?.name ?? "");
const newMenuLabel = ref("");
const deleteOpen = ref(false);

const selected = computed(() => menus.value.find((menu) => menu.name === selectedName.value) ?? null);
const extraLocales = computed(() =>
  site.draft.settings.locales.filter((locale) => locale !== site.draft.settings.default_locale),
);

const quickLinks = computed(() => {
  const options = [{ label: t("site.link.home"), link: { type: "home" } }];
  for (const type of site.data.types) {
    const route = site.draft.settings.routes[type.name];
    if (!route?.enabled || type.visibility === "private") continue;
    if (type.singleton) {
      options.push({ label: type.label, link: { type: "entry", collection: type.name, entry: type.name } });
    } else if (route.index_path) {
      options.push({ label: type.label, link: { type: "index", collection: type.name } });
    }
  }
  return options;
});

watch(menus, (list) => {
  if (!list.some((menu) => menu.name === selectedName.value)) selectedName.value = list[0]?.name ?? "";
});

function newItem(label = "", link = { type: "url", url: "", new_tab: false }) {
  return { id: newId(), label, labels: {}, link: { new_tab: false, ...link }, children: [] };
}

function slugify(value) {
  return String(value).toLowerCase().trim().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "") || "menu";
}

function addMenu() {
  const label = newMenuLabel.value.trim();
  if (!label) return;
  let name = slugify(label);
  let suffix = 2;
  while (menus.value.some((menu) => menu.name === name)) name = `${slugify(label)}-${suffix++}`;
  menus.value.push({ name, label, items: [] });
  selectedName.value = name;
  newMenuLabel.value = "";
}

function deleteMenu() {
  const index = menus.value.findIndex((menu) => menu.name === selectedName.value);
  if (index >= 0) menus.value.splice(index, 1);
  deleteOpen.value = false;
}

function move(list, index, delta) {
  const next = index + delta;
  if (next < 0 || next >= list.length) return;
  const [item] = list.splice(index, 1);
  list.splice(next, 0, item);
}

function quickAdd(event) {
  const option = quickLinks.value[Number(event.target.value)];
  event.target.value = "";
  if (option && selected.value) selected.value.items.push(newItem(option.label, option.link));
}
</script>
