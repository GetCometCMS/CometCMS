<template>
  <div class="relative h-56 rounded-[10px] border border-slate-200 bg-slate-100/75" role="group" :aria-label="t('site.styleGroup.spacing')">
    <span class="pointer-events-none absolute left-[9px] top-[3px] text-[10px] leading-[14px] text-slate-500">{{ t('site.spacing.margin') }}</span>
    <div class="absolute inset-x-[34px] inset-y-[42px] rounded-md border border-dashed border-slate-300 bg-[rgb(var(--color-surface))]">
      <span class="pointer-events-none absolute left-[9px] top-[7px] text-[10px] leading-[14px] text-slate-500">{{ t('site.spacing.padding') }}</span>
      <div class="absolute inset-x-[35px] inset-y-[47px] min-w-3 rounded border border-slate-200 bg-slate-50" aria-hidden="true"></div>
    </div>
    <button
      v-for="control in controls"
      :id="`builder-${node.id}-style-${control.key}`"
      :key="control.key"
      type="button"
      role="combobox"
      aria-haspopup="listbox"
      :aria-label="`${control.label}: ${optionLabel(control.value)}`"
      :aria-expanded="activeKey === control.key"
      :aria-controls="activeKey === control.key ? listId : undefined"
      :aria-activedescendant="activeKey === control.key ? `${listId}-${activeIndex}` : undefined"
      class="absolute flex h-7 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-[5px] text-[11px] font-semibold hover:bg-theme-50 hover:text-theme-700 hover:ring-1 hover:ring-theme-300 focus-visible:bg-theme-50 focus-visible:text-theme-700 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-theme-300 aria-expanded:bg-theme-50 aria-expanded:text-theme-700 aria-expanded:outline-none aria-expanded:ring-1 aria-expanded:ring-theme-300"
      :class="[positionClasses[control.key], control.own ? 'text-theme-700 underline decoration-dotted underline-offset-2' : control.value !== '' ? 'text-slate-700' : 'text-slate-400']"
      :title="`${control.label}: ${optionLabel(control.value)}${control.own ? ` (${t(`site.device.${builder?.device.value}`)})` : ''}`"
      @click="toggle(control, $event.currentTarget)"
      @keydown="onKeydown($event, control)"
      @blur="close()"
    >{{ shortLabel(control.value) }}</button>
    <Teleport to="body">
      <div v-if="activeControl" ref="popup" class="fixed z-[1000] overflow-y-auto overscroll-contain rounded-[10px] border border-slate-200 bg-[rgb(var(--color-surface))] p-[5px] text-slate-700 shadow-[0_12px_28px_-6px_rgb(0_0_0_/_0.18),0_3px_8px_-3px_rgb(0_0_0_/_0.1)]" :style="popupStyle">
        <p :id="`${listId}-label`" class="px-[7px] pb-2 pt-[5px] text-[10px] font-medium leading-4 text-slate-500">{{ activeControl.label }}</p>
        <div :id="listId" role="listbox" :aria-labelledby="`${listId}-label`">
          <button
            v-for="(value, index) in activeControl.options"
            :id="`${listId}-${index}`"
            :key="value"
            type="button"
            role="option"
            tabindex="-1"
            :aria-selected="activeControl.value === value"
            class="flex h-[30px] w-full items-center gap-2 rounded-[5px] px-[7px] text-left text-[11px] leading-4 aria-selected:bg-theme-50 aria-selected:text-theme-700"
            :class="{ 'bg-slate-100': activeIndex === index, 'mb-[5px]': value === '' }"
            @pointermove="activeIndex = index"
            @pointerdown.prevent
            @click="select(value)"
          >
            <span v-if="value === ''" class="flex-1">{{ t('site.option.default') }}</span>
            <template v-else>
              <span class="flex-1 font-semibold">{{ shortLabel(value) }}</span>
              <span v-if="pixelValue(value) !== undefined" class="flex gap-0.5 tabular-nums text-slate-500">{{ pixelValue(value) }}<span class="text-[10px] text-slate-400">px</span></span>
            </template>
            <svg v-if="activeControl.value === value" viewBox="0 0 16 16" fill="none" class="h-3 w-3 shrink-0 text-theme-600" aria-hidden="true"><path d="m3 8 3 3 7-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span v-else class="h-3 w-3 shrink-0 text-theme-600" aria-hidden="true"></span>
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from "vue";
import { useSiteStore } from "../../../stores/site.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  node: { type: Object, required: true },
  spec: { type: Object, required: true },
  keys: { type: Array, required: true },
  /** The value shown for a key, e.g. on the previewed device; defaults to the node's own. */
  valueFor: { type: Function, default: null },
  /** Whether a key has its own value on the previewed device. */
  overridden: { type: Function, default: () => false },
});
const emit = defineEmits(["update"]);
const { t } = useI18n();
const site = useSiteStore();
const builder = useSiteBuilder();
const theme = computed(() => site.draft?.theme ?? site.data?.theme);
const labels = { '': '–', none: '0', xs: 'XS', sm: 'S', md: 'M', lg: 'L', xl: 'XL', '2xl': '2XL' };
const shortLabel = (value) => labels[value] ?? value;
const pixelValue = (value) => value === 'none' ? 0 : theme.value?.space?.[value];
const listId = `spacing-options-${useId()}`;
const activeKey = ref(null);
const activeIndex = ref(0);
const popup = ref(null);
const popupStyle = ref({});
let trigger = null;

const positionClasses = {
  margin_top: 'left-1/2 top-[30px]',
  margin_right: 'left-[calc(100%-18px)] top-1/2',
  margin_bottom: 'left-1/2 top-[calc(100%-22px)]',
  margin_left: 'left-[18px] top-1/2',
  padding_top: 'left-1/2 top-20',
  padding_right: 'left-[calc(100%-53px)] top-1/2',
  padding_bottom: 'left-1/2 top-[calc(100%-67px)]',
  padding_left: 'left-[53px] top-1/2',
};

const controls = computed(() => ["margin", "padding"].flatMap((group) =>
  ["top", "right", "bottom", "left"].flatMap((side) => {
    const key = `${group}_${side}`;
    if (!props.keys.includes(key) || !props.spec[key]) return [];
    const pair = group === "padding" ? `padding_${['top', 'bottom'].includes(side) ? 'y' : 'x'}` : null;
    // Older pages keep their paired padding; a side overrides only that edge.
    const inherited = pair ? props.node.style?.[pair] : '';
    const value = (props.valueFor ? props.valueFor(key) : props.node.style?.[key]) || inherited || '';
    return [{ key, group, side, value, own: props.overridden(key), options: props.spec[key].options ?? [], label: t(`site.style.${key}`) }];
  }),
));

const activeControl = computed(() => controls.value.find((control) => control.key === activeKey.value));

function close(restoreFocus = false) {
  activeKey.value = null;
  if (restoreFocus) trigger?.focus({ preventScroll: true });
}

async function open(control, element) {
  trigger = element;
  activeIndex.value = Math.max(0, control.options.indexOf(control.value));
  popupStyle.value = { visibility: 'hidden', width: `${Math.min(150, window.innerWidth - 16)}px`, maxHeight: `${window.innerHeight - 16}px` };
  activeKey.value = control.key;
  await nextTick();
  if (!popup.value || activeKey.value !== control.key) return;
  const anchor = element.getBoundingClientRect();
  const panel = popup.value.getBoundingClientRect();
  const below = window.innerHeight - anchor.bottom - 14;
  const above = anchor.top - 14;
  const placeBelow = below >= panel.height || below >= above;
  const height = Math.min(panel.height, Math.max(0, placeBelow ? below : above));
  popupStyle.value = {
    width: `${panel.width}px`,
    left: `${Math.max(8, Math.min(anchor.left + anchor.width / 2 - panel.width / 2, window.innerWidth - panel.width - 8))}px`,
    top: `${placeBelow ? anchor.bottom + 6 : anchor.top - height - 6}px`,
    maxHeight: `${height}px`,
  };
  await nextTick();
  if (activeKey.value === control.key) revealActive();
}

function toggle(control, element) {
  if (activeKey.value === control.key) close();
  else open(control, element);
}

function select(value) {
  if (!activeControl.value) return;
  emit('update', activeControl.value.key, value);
  close(true);
}

function revealActive() {
  popup.value?.querySelector(`[id="${listId}-${activeIndex.value}"]`)?.scrollIntoView({ block: 'nearest' });
}

async function onKeydown(event, control) {
  // Keep the builder's save shortcut; navigation and deletion stay in the picker.
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') return;
  event.stopPropagation();
  if (event.altKey || event.ctrlKey || event.metaKey) return;
  const isOpen = activeKey.value === control.key;
  if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
    event.preventDefault();
    if (!isOpen) await open(control, event.currentTarget);
    else if (event.key === 'ArrowDown') activeIndex.value = (activeIndex.value + 1) % control.options.length;
    else if (event.key === 'ArrowUp') activeIndex.value = (activeIndex.value - 1 + control.options.length) % control.options.length;
    if (event.key === 'Home') activeIndex.value = 0;
    if (event.key === 'End') activeIndex.value = control.options.length - 1;
    await nextTick();
    revealActive();
  } else if (isOpen && ['Enter', ' '].includes(event.key)) {
    event.preventDefault();
    select(control.options[activeIndex.value]);
  } else if (isOpen && event.key === 'Escape') {
    event.preventDefault();
    close(true);
  } else if (event.key === 'Tab') close();
}

function onPointerDown(event) {
  if (!popup.value?.contains(event.target) && !trigger?.contains(event.target)) close();
}

function onScroll(event) {
  if (!popup.value?.contains(event.target)) close();
}

function onResize() { close(); }
watch(() => props.node.id, () => close());
onMounted(() => {
  document.addEventListener('pointerdown', onPointerDown);
  document.addEventListener('scroll', onScroll, true);
  window.addEventListener('resize', onResize);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onPointerDown);
  document.removeEventListener('scroll', onScroll, true);
  window.removeEventListener('resize', onResize);
});

function optionLabel(value) {
  if (value === '') return t('site.option.default');
  if (value === 'none') return '0';
  const pixels = theme.value?.space?.[value];
  return pixels === undefined ? shortLabel(value) : `${shortLabel(value)} · ${pixels}px`;
}
</script>
