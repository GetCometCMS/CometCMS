<template>
  <div class="space-y-3">
    <p class="text-xs text-slate-500">{{ t('site.tabs.editHint') }}</p>
    <ol class="space-y-2">
      <li v-for="(tab, index) in group.children" :key="tab.id" class="rounded-lg border border-slate-200 p-2">
        <label :for="`tab-title-${tab.id}`" class="sr-only">{{ t('site.tabs.tabTitle', { number: index + 1 }) }}</label>
        <input :id="`tab-title-${tab.id}`" :value="tab.props.title" :maxlength="300" class="form-input w-full rounded-lg border-slate-300 py-1.5 text-xs" @input="builder.updateValue(tab.id, 'props', 'title', $event.target.value)" />
        <div class="mt-2 flex items-center gap-1">
          <button type="button" class="btn-secondary btn-sm mr-auto" @click="builder.select(tab.id)">
            <Icon icon="mdi:pencil-outline" class="h-3.5 w-3.5" />{{ t('site.tabs.editContent') }}
          </button>
          <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" :disabled="index === 0" :aria-label="t('site.tabs.moveLeft', { title: tab.props.title })" @click="builder.shift(tab.id, -1)"><Icon icon="mdi:arrow-left" class="h-4 w-4" /></button>
          <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" :disabled="index === group.children.length - 1" :aria-label="t('site.tabs.moveRight', { title: tab.props.title })" @click="builder.shift(tab.id, 1)"><Icon icon="mdi:arrow-right" class="h-4 w-4" /></button>
          <button type="button" class="rounded p-1 text-red-500 hover:bg-red-50" :aria-label="`${t('common.delete')}: ${tab.props.title}`" @click="builder.remove(tab.id)"><Icon icon="mdi:trash-can-outline" class="h-4 w-4" /></button>
        </div>
      </li>
    </ol>
    <button type="button" class="btn-secondary btn-sm w-full justify-center" @click="addTab"><Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.tabs.add') }}</button>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue';
import { useSiteBuilder } from '../../../composables/useSiteBuilder.js';
import { useI18n } from '../../../i18n/index.js';

const props = defineProps({ group: { type: Object, required: true } });
const builder = useSiteBuilder();
const { t } = useI18n();

function addTab() {
  const tab = builder.make('tab', { title: t('site.tabs.tabTitle', { number: props.group.children.length + 1 }) });
  builder.insert(tab, props.group.id);
}
</script>
