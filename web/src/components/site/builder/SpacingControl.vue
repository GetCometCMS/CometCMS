<template>
  <div class="spacing-box" role="group" :aria-label="t('site.styleGroup.spacing')">
    <span class="spacing-label spacing-margin-label">{{ t('site.spacing.margin') }}</span>
    <div class="spacing-padding">
      <span class="spacing-label">{{ t('site.spacing.padding') }}</span>
      <div class="spacing-content" aria-hidden="true"></div>
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
      class="spacing-value"
      :class="[`spacing-${control.group}-${control.side}`, { 'spacing-value-set': control.value !== '', 'spacing-value-open': activeKey === control.key }]"
      :title="`${control.label}: ${optionLabel(control.value)}`"
      @click="toggle(control, $event.currentTarget)"
      @keydown="onKeydown($event, control)"
      @blur="close()"
    >{{ shortLabel(control.value) }}</button>
    <Teleport to="body">
      <div v-if="activeControl" ref="popup" class="spacing-picker" :style="popupStyle">
        <p :id="`${listId}-label`" class="spacing-picker-label">{{ activeControl.label }}</p>
        <div :id="listId" role="listbox" :aria-labelledby="`${listId}-label`">
          <button
            v-for="(value, index) in activeControl.options"
            :id="`${listId}-${index}`"
            :key="value"
            type="button"
            role="option"
            tabindex="-1"
            :aria-selected="activeControl.value === value"
            class="spacing-option"
            :class="{ 'spacing-option-active': activeIndex === index, 'spacing-option-default': value === '' }"
            @pointermove="activeIndex = index"
            @pointerdown.prevent
            @click="select(value)"
          >
            <span v-if="value === ''" class="spacing-option-default-label">{{ t('site.option.default') }}</span>
            <template v-else>
              <span class="spacing-token">{{ shortLabel(value) }}</span>
              <span v-if="pixelValue(value) !== undefined" class="spacing-pixels">{{ pixelValue(value) }}<span>px</span></span>
            </template>
            <svg v-if="activeControl.value === value" viewBox="0 0 16 16" fill="none" class="spacing-check" aria-hidden="true"><path d="m3 8 3 3 7-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span v-else class="spacing-check" aria-hidden="true"></span>
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from "vue";
import { useSiteStore } from "../../../stores/site.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  node: { type: Object, required: true },
  spec: { type: Object, required: true },
  keys: { type: Array, required: true },
});
const emit = defineEmits(["update"]);
const { t } = useI18n();
const site = useSiteStore();
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

const controls = computed(() => ["margin", "padding"].flatMap((group) =>
  ["top", "right", "bottom", "left"].flatMap((side) => {
    const key = `${group}_${side}`;
    if (!props.keys.includes(key) || !props.spec[key]) return [];
    const pair = group === "padding" ? `padding_${['top', 'bottom'].includes(side) ? 'y' : 'x'}` : null;
    // Older pages keep their paired padding; a side overrides only that edge.
    const inherited = pair ? props.node.style?.[pair] : '';
    const value = props.node.style?.[key] || inherited || '';
    return [{ key, group, side, value, options: props.spec[key].options ?? [], label: t(`site.style.${key}`) }];
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

<style scoped>
.spacing-box {
  position: relative;
  height: 224px;
  border: 1px solid rgb(var(--color-slate-200));
  border-radius: 10px;
  background: rgb(var(--color-slate-100) / 0.75);
}
.spacing-label {
  position: absolute;
  top: 7px;
  left: 9px;
  color: rgb(var(--color-slate-500));
  font-size: 10px;
  line-height: 14px;
  pointer-events: none;
}
.spacing-padding {
  position: absolute;
  inset: 42px 34px;
  border: 1px dashed rgb(var(--color-slate-300));
  border-radius: 6px;
  background: rgb(var(--color-surface));
}
.spacing-content {
  position: absolute;
  inset: 47px 35px;
  min-width: 12px;
  border: 1px solid rgb(var(--color-slate-200));
  border-radius: 4px;
  background: rgb(var(--color-slate-50));
}
.spacing-value {
  position: absolute;
  display: flex;
  width: 36px;
  height: 28px;
  align-items: center;
  justify-content: center;
  border-radius: 5px;
  color: rgb(var(--color-slate-400));
  font-size: 11px;
  font-weight: 600;
  transform: translate(-50%, -50%);
}
.spacing-value-set { color: rgb(var(--color-slate-700)); }
.spacing-value:hover, .spacing-value:focus-visible, .spacing-value-open {
  outline: none;
  background: rgb(var(--color-theme-50));
  color: rgb(var(--color-theme-700));
  box-shadow: 0 0 0 1px rgb(var(--color-theme-300));
}
.spacing-picker {
  position: fixed;
  z-index: 1000;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid rgb(var(--color-slate-200));
  border-radius: 10px;
  padding: 5px;
  background: rgb(var(--color-surface));
  color: rgb(var(--color-slate-700));
  box-shadow: 0 12px 28px -6px rgb(0 0 0 / 0.18), 0 3px 8px -3px rgb(0 0 0 / 0.1);
}
.spacing-picker-label {
  padding: 5px 7px 8px;
  color: rgb(var(--color-slate-500));
  font-size: 10px;
  font-weight: 500;
  line-height: 16px;
}
.spacing-option {
  display: flex;
  width: 100%;
  height: 30px;
  align-items: center;
  gap: 8px;
  border-radius: 5px;
  padding: 0 7px;
  text-align: left;
  font-size: 11px;
  line-height: 16px;
}
.spacing-option-active { background: rgb(var(--color-slate-100)); }
.spacing-option[aria-selected="true"] { color: rgb(var(--color-theme-700)); background: rgb(var(--color-theme-50)); }
.spacing-option-default { margin-bottom: 5px; }
.spacing-option-default-label { flex: 1; }
.spacing-token { flex: 1; font-weight: 600; }
.spacing-pixels { display: flex; gap: 2px; color: rgb(var(--color-slate-500)); font-variant-numeric: tabular-nums; }
.spacing-pixels > span { color: rgb(var(--color-slate-400)); font-size: 10px; }
.spacing-check { width: 12px; height: 12px; flex-shrink: 0; color: rgb(var(--color-theme-600)); }
.spacing-margin-label { top: 3px; }
.spacing-margin-top { top: 30px; left: 50%; }
.spacing-margin-right { top: 50%; left: calc(100% - 18px); }
.spacing-margin-bottom { top: calc(100% - 22px); left: 50%; }
.spacing-margin-left { top: 50%; left: 18px; }
.spacing-padding-top { top: 80px; left: 50%; }
.spacing-padding-right { top: 50%; left: calc(100% - 53px); }
.spacing-padding-bottom { top: calc(100% - 67px); left: 50%; }
.spacing-padding-left { top: 50%; left: 53px; }
</style>
