<template>
  <div ref="container" class="relative" @focusout="onFocusOut">
    <div class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 py-1.5 focus-within:border-theme-500 focus-within:ring-1 focus-within:ring-theme-500" @click="input?.focus()">
      <span v-for="name in selected" :key="name" class="inline-flex max-w-full items-center gap-1 rounded bg-theme-100 px-1.5 py-0.5 font-mono text-xs text-theme-700">
        <span class="break-all">{{ name }}</span>
        <button type="button" class="shrink-0 text-theme-500 hover:text-theme-800" :aria-label="t('select.remove', { name })" @click.stop="remove(name)">×</button>
      </span>
      <input
        :id="id"
        ref="input"
        v-model="query"
        type="text"
        role="combobox"
        autocomplete="off"
        spellcheck="false"
        aria-autocomplete="list"
        :aria-expanded="open"
        :aria-controls="`${id}-options`"
        :aria-activedescendant="open && active >= 0 && filtered[active] ? `${id}-option-${active}` : undefined"
        :aria-describedby="`${id}-hint`"
        :placeholder="t('site.builder.classPlaceholder')"
        class="min-w-20 flex-1 border-0 bg-transparent p-0 font-mono text-xs focus:ring-0"
        @focus="open = true"
        @input="open = true; active = -1"
        @keydown="onKeydown"
        @paste="onPaste"
      />
    </div>
    <p :id="`${id}-hint`" class="mt-1 text-[10px] text-slate-400">
      {{ t(selected.length >= MAX_CSS_CLASSES ? 'site.builder.classLimit' : 'site.builder.classInputHint', { count: MAX_CSS_CLASSES }) }}
    </p>
    <ul v-if="open" :id="`${id}-options`" role="listbox" :aria-label="t('site.style.class')" class="absolute bottom-full left-0 z-50 mb-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
      <li
        v-for="(name, index) in filtered"
        :id="`${id}-option-${index}`"
        :key="name"
        role="option"
        :aria-selected="index === active"
        :aria-disabled="selected.length >= MAX_CSS_CLASSES"
        class="cursor-pointer break-all px-2 py-1.5 font-mono text-xs text-slate-700"
        :class="[index === active ? 'bg-theme-50 text-theme-700' : 'hover:bg-slate-50', { 'cursor-not-allowed opacity-50': selected.length >= MAX_CSS_CLASSES }]"
        @mousedown.prevent
        @click="add(name)"
      >
        {{ name }}
      </li>
      <li v-if="!filtered.length" role="presentation" class="px-2 py-1.5 text-xs text-slate-400">{{ t('select.noResults') }}</li>
    </ul>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { MAX_CSS_CLASSES, normalizeCssClasses } from "../../../composables/cssClasses.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: String, default: "" },
  options: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const container = ref(null);
const input = ref(null);
const query = ref("");
const open = ref(false);
const active = ref(-1);
const selected = computed(() => normalizeCssClasses(props.modelValue));
const filtered = computed(() => props.options.filter((name) => !selected.value.includes(name) && name.toLowerCase().includes(query.value.trim().toLowerCase())));
watch(filtered, () => { active.value = -1; });

function add(value) {
  const incoming = normalizeCssClasses(value);
  if (!incoming.length) return;
  const names = normalizeCssClasses([...selected.value, ...incoming]);
  const changed = names.join(" ") !== selected.value.join(" ");
  if (!changed && incoming.some((name) => !selected.value.includes(name))) return;
  if (changed) emit("update:modelValue", names.join(" "));
  query.value = "";
  active.value = -1;
}

function remove(name) {
  input.value?.focus();
  emit("update:modelValue", selected.value.filter((value) => value !== name).join(" "));
}

function onFocusOut(event) {
  if (container.value?.contains(event.relatedTarget)) return;
  add(query.value);
  open.value = false;
}

function onPaste(event) {
  const value = event.clipboardData?.getData("text");
  if (!value || !/\s/.test(value.trim())) return;
  event.preventDefault();
  const start = event.target.selectionStart ?? query.value.length;
  const end = event.target.selectionEnd ?? start;
  add(query.value.slice(0, start) + value + query.value.slice(end));
}

async function onKeydown(event) {
  if (event.isComposing) return;
  if (event.key === "ArrowDown" || event.key === "ArrowUp") {
    event.preventDefault();
    open.value = true;
    const count = filtered.value.length;
    if (!count) return;
    if (active.value < 0) active.value = event.key === "ArrowDown" ? 0 : count - 1;
    else active.value = (active.value + (event.key === "ArrowDown" ? 1 : -1) + count) % count;
    await nextTick();
    container.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: "nearest" });
  } else if (event.key === "Enter" || event.key === " " || event.key === "Tab") {
    if (event.key !== "Tab") event.preventDefault();
    add(open.value && active.value >= 0 ? filtered.value[active.value] : query.value);
    if (event.key === "Tab") open.value = false;
  } else if (event.key === "Escape") {
    event.preventDefault();
    event.stopPropagation();
    open.value = false;
  }
}
</script>
