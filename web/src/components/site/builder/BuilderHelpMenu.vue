<template>
  <div ref="rootRef" class="relative shrink-0" @focusout="onFocusout">
    <button
      ref="triggerRef"
      type="button"
      class="topbar-action"
      :class="{ 'is-open': open }"
      :title="t('site.builder.help')"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-controls="open ? menuId : undefined"
      @click="open ? close() : openMenu()"
      @keydown.down.prevent="openMenu()"
      @keydown.up.prevent="openMenu(true)"
      @keydown.esc.stop.prevent="close(true)"
    >
      <Icon icon="mdi:help-circle-outline" class="h-[18px] w-[18px]" />
      <span class="sr-only">{{ t("site.builder.help") }}</span>
    </button>

    <div
      v-if="open"
      :id="menuId"
      ref="menuRef"
      role="menu"
      tabindex="-1"
      :aria-label="t('site.builder.help')"
      class="absolute right-0 top-full z-50 mt-1 min-w-[13rem] rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg"
      @keydown="onMenuKeydown"
    >
      <button
        v-for="item in items"
        :key="item.key"
        type="button"
        role="menuitem"
        tabindex="-1"
        class="flex w-full items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
        @click="select(item.key)"
      >
        <Icon :icon="item.icon" class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
        <span class="flex-1">{{ item.label }}</span>
        <kbd v-if="item.shortcut" class="kbd">{{ item.shortcut }}</kbd>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from "vue";
import { Icon } from "@iconify/vue";
import { useI18n } from "../../../i18n/index.js";

const emit = defineEmits(["guide", "reset-layout"]);
const { t } = useI18n();
const rootRef = ref(null);
const triggerRef = ref(null);
const menuRef = ref(null);
const open = ref(false);
const menuId = `builder-help-${useId()}`;
const items = computed(() => [
  { key: "guide", label: t("site.builder.guide.title"), icon: "mdi:lightbulb-on-outline", shortcut: "?" },
  { key: "reset-layout", label: t("site.builder.resetLayout"), icon: "mdi:view-dashboard-outline" },
]);

function menuItems() {
  return [...(menuRef.value?.querySelectorAll('[role="menuitem"]') ?? [])];
}

async function openMenu(last = false) {
  open.value = true;
  await nextTick();
  const all = menuItems();
  (all[last ? all.length - 1 : 0] ?? menuRef.value)?.focus();
}

function close(restoreFocus = false) {
  open.value = false;
  if (restoreFocus) triggerRef.value?.focus();
}

function select(key) {
  close(true);
  emit(key);
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
  const all = menuItems();
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

<style scoped>
.topbar-action {
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  color: rgb(var(--color-slate-600));
  transition: background-color 150ms, color 150ms;
}
.topbar-action:hover,
.topbar-action.is-open {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
</style>
