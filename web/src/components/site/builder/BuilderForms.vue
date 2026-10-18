<template>
  <div class="space-y-3 p-3">
    <div v-if="builder.forms.value.length" class="sticky top-0 z-10 -mx-3 bg-white px-3 pb-2">
      <label class="sr-only" :for="searchId">{{ t("site.forms.search") }}</label>
      <input
        :id="searchId"
        v-model="search"
        type="search"
        :placeholder="t('site.forms.search')"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        @keydown.esc.stop.prevent="search = ''"
      />
    </div>
    <ul class="space-y-2">
      <li v-for="form in forms" :key="form.id" class="space-y-1">
        <div class="flex items-center gap-1">
          <PaletteItem
            class="min-w-0 flex-1"
            :icon="KIND_ICONS.form"
            :label="form.name"
            :title="form.name"
            @add="builder.insert(create(form))"
            @drag="start($event, form)"
            @dragend="end"
          />
          <router-link :to="manageLink(form.type)" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-theme-700" :title="t('site.forms.edit')">
            <Icon icon="mdi:pencil-outline" class="h-4 w-4" />
            <span class="sr-only">{{ t("site.forms.edit") }}: {{ form.name }}</span>
          </router-link>
        </div>
        <p class="px-2 text-[11px] text-slate-400">
          {{ typeLabel(form.type) }}
        </p>
      </li>
    </ul>
    <p v-if="!builder.forms.value.length" class="px-1 text-xs text-slate-500">{{ t("site.forms.paletteEmpty") }}</p>
    <p v-else-if="!forms.length" role="status" class="px-1 text-sm text-slate-500">{{ t("site.builder.noSearchResults") }}</p>

    <router-link :to="manageLink()" class="btn-secondary btn-sm w-full justify-center">
      <Icon icon="mdi:cog-outline" class="h-4 w-4" />
      {{ t("site.forms.manage") }}
    </router-link>
  </div>
</template>

<script setup>
import { computed, ref, useId } from "vue";
import { useRoute } from "vue-router";
import { Icon } from "@iconify/vue";
import PaletteItem from "./PaletteItem.vue";
import { KIND_ICONS } from "./kindIcons.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

/** Website forms, ready to place; their settings belong to the content type. */
const builder = useSiteBuilder();
const route = useRoute();
const { t } = useI18n();
const search = ref("");
const searchId = `builder-forms-search-${useId()}`;
const forms = computed(() => {
  const terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
  return builder.forms.value.filter((form) => {
    const text = [form.name, typeLabel(form.type)].join(" ").toLowerCase();
    return terms.every((term) => text.includes(term));
  });
});

function typeLabel(name) {
  return builder.types.value.find((type) => type.name === name)?.label ?? name;
}

/** The forms settings, coming back here afterwards. */
function manageLink(type = "") {
  return { path: type ? `/content-types/${type}/edit` : "/content-types", hash: type ? "#submissions" : "", query: { back: route.fullPath } };
}

function create(form) {
  return builder.make("form", { form: form.id });
}

function start(event, form) {
  builder.drag.value = { source: "palette", create: () => create(form) };
  event.dataTransfer.effectAllowed = "copy";
  event.dataTransfer.setData("text/plain", "cometcms-node");
}

function end() {
  setTimeout(() => { builder.drag.value = null; }, 0);
}
</script>
