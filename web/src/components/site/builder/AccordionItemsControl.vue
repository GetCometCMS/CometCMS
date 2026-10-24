<template>
  <div class="space-y-2">
    <ol class="space-y-2">
      <li v-for="(item, index) in items" :key="keys[index]" class="overflow-hidden rounded-lg border border-slate-200">
        <div class="flex items-center gap-1 bg-slate-50 p-2">
          <button type="button" class="flex min-w-0 flex-1 items-center gap-1 text-left text-xs font-semibold text-slate-700" :aria-expanded="expanded === keys[index]" :aria-controls="`${id}-${keys[index]}`" @click="expanded = expanded === keys[index] ? null : keys[index]">
            <Icon :icon="expanded === keys[index] ? 'mdi:chevron-down' : 'mdi:chevron-right'" class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ entryLabel(item, index) }}</span>
          </button>
          <button v-for="action in actions" :key="action.name" type="button" class="rounded p-1 text-slate-500 hover:bg-slate-200 disabled:opacity-30" :disabled="structuralLocked || (action.name === 'up' && index === 0) || (action.name === 'down' && index === items.length - 1)" :aria-label="`${t(action.label)}: ${entryLabel(item, index)}`" @click="change(action.name, index)"><Icon :icon="action.icon" class="h-3.5 w-3.5" /></button>
        </div>
        <div v-if="expanded === keys[index]" :id="`${id}-${keys[index]}`" class="space-y-3 p-3">
          <SmartValueEditor :model-value="localized(item.title)" :node="node" target="text" :label="t('site.prop.title')" @update:model-value="update(index, 'title', $event)" />
          <SmartValueEditor :model-value="localized(item.html)" :node="node" target="html" :label="t('site.prop.html')" @update:model-value="update(index, 'html', $event)" />
          <label class="flex items-center justify-between gap-2 text-xs text-slate-500" :for="`${id}-${keys[index]}-open`">
            {{ t('site.prop.open') }}
            <ToggleSwitch :id="`${id}-${keys[index]}-open`" :model-value="!!item.open" :disabled="structuralLocked" @update:model-value="update(index, 'open', $event)" />
          </label>
        </div>
      </li>
    </ol>
    <button type="button" class="btn-secondary btn-sm w-full justify-center" :disabled="structuralLocked" @click="add"><Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.accordion.add') }}</button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Icon } from '@iconify/vue';
import SmartValueEditor from './SmartValueEditor.vue';
import ToggleSwitch from '../../ToggleSwitch.vue';
import { useSiteBuilder } from '../../../composables/useSiteBuilder.js';
import { useI18n } from '../../../i18n/index.js';
import { isLocalized, resolveLocalized, setLocalized } from '../../../composables/localizedValues.js';
import { smartSummary } from '../../../composables/smartValues.js';

const props = defineProps({ id: { type: String, required: true }, node: { type: Object, required: true }, modelValue: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue']);
const builder = useSiteBuilder();
const { t } = useI18n();
const items = computed(() => props.modelValue ?? []);
const structuralLocked = computed(() => !!builder.translating?.value);
let sequence = 0;
const keys = ref([]);
const expanded = ref(null);
watch(() => items.value.length, (length) => {
  while (keys.value.length < length) keys.value.push(++sequence);
  keys.value.length = length;
  if (expanded.value === null && length === 1) expanded.value = keys.value[0];
}, { immediate: true, flush: 'sync' });
const actions = [
  { name: 'up', icon: 'mdi:arrow-up', label: 'site.builder.moveUp' },
  { name: 'down', icon: 'mdi:arrow-down', label: 'site.builder.moveDown' },
  { name: 'duplicate', icon: 'mdi:content-copy', label: 'site.builder.duplicate' },
  { name: 'delete', icon: 'mdi:trash-can-outline', label: 'common.delete' },
];
const localized = (value) => resolveLocalized(value, builder.locale.value || builder.defaultLocale.value, builder.defaultLocale.value);
const entryLabel = (item, index) => smartSummary(localized(item.title)) || t('site.accordion.entry', { number: index + 1 });
function update(index, key, value) {
  const next = items.value.map((item) => ({ ...item }));
  const current = next[index][key];
  next[index][key] = isLocalized(current) ? setLocalized(current, builder.locale.value || builder.defaultLocale.value, value, builder.defaultLocale.value) : value;
  if (key === 'open' && value && props.node.props.single_open) next.forEach((item, i) => { if (i !== index) item.open = false; });
  emit('update:modelValue', next);
}
function add() {
  keys.value.push(++sequence);
  expanded.value = sequence;
  emit('update:modelValue', [...items.value, { title: t('site.accordion.entry', { number: items.value.length + 1 }), html: '', open: false }]);
}
function change(action, index) {
  const next = items.value.slice();
  if (action === 'delete') {
    next.splice(index, 1);
    keys.value.splice(index, 1);
  } else if (action === 'duplicate') {
    next.splice(index + 1, 0, { ...JSON.parse(JSON.stringify(next[index])), open: false });
    keys.value.splice(index + 1, 0, ++sequence);
    expanded.value = sequence;
  } else {
    const to = index + (action === 'up' ? -1 : 1);
    [next[index], next[to]] = [next[to], next[index]];
    [keys.value[index], keys.value[to]] = [keys.value[to], keys.value[index]];
  }
  emit('update:modelValue', next);
}
</script>
