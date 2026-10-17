<template>
  <div class="flex gap-3" :class="compact ? 'p-3' : 'p-4'">
    <div class="flex flex-col gap-0.5 pt-6">
      <button type="button" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" :disabled="first" :title="t('site.builder.moveUp')" @click="$emit('move', -1)">
        <Icon icon="mdi:chevron-up" class="h-4 w-4" />
      </button>
      <button type="button" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30" :disabled="last" :title="t('site.builder.moveDown')" @click="$emit('move', 1)">
        <Icon icon="mdi:chevron-down" class="h-4 w-4" />
      </button>
    </div>

    <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 md:grid-cols-2">
      <div>
        <label class="form-label" :for="`menu-item-${item.id}`">{{ t("site.menus.itemLabel") }}</label>
        <input
          :id="`menu-item-${item.id}`"
          v-model="item.label"
          type="text"
          class="form-input w-full rounded-lg border-slate-300 text-sm"
          :placeholder="t('site.menus.itemLabelPlaceholder')"
        />
        <div v-if="locales.length" class="mt-2 space-y-1.5">
          <label v-for="locale in locales" :key="locale" class="flex items-center gap-2">
            <span class="w-8 shrink-0 font-mono text-xs uppercase text-slate-500">{{ locale }}</span>
            <input
              :value="item.labels?.[locale] ?? ''"
              type="text"
              class="form-input min-w-0 flex-1 rounded-lg border-slate-300 py-1 text-xs"
              :placeholder="item.label"
              @input="setLabel(locale, $event.target.value)"
            />
          </label>
        </div>
      </div>
      <div>
        <span class="form-label">{{ t("site.menus.target") }}</span>
        <LinkEditor v-model="item.link" :types="types" :allow="['url', 'home', 'index', 'entry']" />
      </div>
    </div>

    <button type="button" class="self-start rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" :title="t('common.remove')" @click="$emit('remove')">
      <Icon icon="mdi:close" class="h-4 w-4" />
      <span class="sr-only">{{ t("common.remove") }}</span>
    </button>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import LinkEditor from "./LinkEditor.vue";
import { useI18n } from "../../i18n/index.js";

const props = defineProps({
  item: { type: Object, required: true },
  types: { type: Array, default: () => [] },
  locales: { type: Array, default: () => [] },
  first: { type: Boolean, default: false },
  last: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

defineEmits(["move", "remove"]);
const { t } = useI18n();

function setLabel(locale, value) {
  props.item.labels = { ...(props.item.labels ?? {}), [locale]: value };
}
</script>
