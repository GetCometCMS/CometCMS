<template>
  <div ref="rootRef" class="relative min-w-0 flex-1" @focusout="onFocusout">
    <button
      ref="triggerRef"
      type="button"
      class="flex w-full items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-left text-xs font-medium text-slate-700 hover:border-slate-400 focus-visible:ring-1 focus-visible:ring-theme-500"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-controls="open ? menuId : undefined"
      :aria-label="`${label}: ${current?.label ?? ''}`"
      @click="open ? close() : openMenu()"
      @keydown.down.prevent="openMenu()"
      @keydown.up.prevent="openMenu(true)"
      @keydown.esc.stop.prevent="close(true)"
    >
      <Icon v-if="current" :icon="current.icon" class="h-4 w-4 shrink-0 text-theme-600" aria-hidden="true" />
      <span class="min-w-0 flex-1 truncate">{{ current?.label }}</span>
      <Icon icon="mdi:chevron-down" class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
    </button>

    <div
      v-if="open"
      :id="menuId"
      ref="menuRef"
      role="menu"
      tabindex="-1"
      :aria-label="label"
      class="absolute left-0 right-0 top-full z-50 mt-1 rounded-lg border border-slate-200 bg-white py-1 text-xs shadow-lg"
      @keydown="onMenuKeydown"
    >
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        role="menuitemradio"
        tabindex="-1"
        :aria-checked="option.value === modelValue"
        class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
        :class="option.value === modelValue ? 'font-semibold text-theme-700' : 'text-slate-700'"
        @click="select(option.value)"
      >
        <Icon :icon="option.icon" class="h-4 w-4 shrink-0" :class="option.value === modelValue ? 'text-theme-600' : 'text-slate-400'" aria-hidden="true" />
        <span class="flex-1">{{ option.label }}</span>
        <Icon v-if="option.value === modelValue" icon="mdi:check" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  /** [{ value, label, icon }] */
  options: { type: Array, default: () => [] },
  label: { type: String, required: true },
});
const emit = defineEmits(["update:modelValue"]);
const rootRef = ref(null);
const triggerRef = ref(null);
const menuRef = ref(null);
const open = ref(false);
const menuId = `link-mode-${useId()}`;
const current = computed(() => props.options.find((option) => option.value === props.modelValue) ?? null);

function items() {
  return [...(menuRef.value?.querySelectorAll('[role="menuitemradio"]') ?? [])];
}

async function openMenu(last = false) {
  open.value = true;
  await nextTick();
  const all = items();
  const selected = all.findIndex((item) => item.getAttribute("aria-checked") === "true");
  (all[selected >= 0 ? selected : last ? all.length - 1 : 0] ?? menuRef.value)?.focus();
}

function close(restoreFocus = false) {
  open.value = false;
  if (restoreFocus) triggerRef.value?.focus();
}

function select(value) {
  close(true);
  emit("update:modelValue", value);
}

function onMenuKeydown(event) {
  if (event.key === "Escape" || event.key === "Tab") {
    if (event.key === "Escape") event.preventDefault();
    event.stopPropagation();
    close(event.key === "Escape");
    return;
  }
  if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return;
  event.preventDefault();
  event.stopPropagation();
  const all = items();
  const index = all.indexOf(document.activeElement);
  const next = event.key === "Home" ? 0
    : event.key === "End" ? all.length - 1
    : (index + (event.key === "ArrowDown" ? 1 : -1) + all.length) % all.length;
  all[next]?.focus();
}

function onOutsidePointerdown(event) {
  if (!rootRef.value?.contains(event.target)) close();
}

function onFocusout(event) {
  if (!rootRef.value?.contains(event.relatedTarget)) close();
}

onMounted(() => document.addEventListener("pointerdown", onOutsidePointerdown));
onBeforeUnmount(() => document.removeEventListener("pointerdown", onOutsidePointerdown));
</script>
