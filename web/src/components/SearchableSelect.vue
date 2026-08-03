<template>
  <div class="relative" ref="containerRef">
    <!-- Free-input mode trigger (combobox / autocomplete) -->
    <div v-if="allowFreeInput && !multiple" class="flex items-center">
      <input
        ref="freeInputRef"
        type="text"
        :value="modelValue ?? ''"
        :placeholder="placeholder"
        :disabled="disabled"
        role="combobox"
        :aria-label="ariaLabel || placeholder"
        :aria-expanded="isOpen"
        :aria-controls="listboxId"
        aria-autocomplete="list"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        :class="disabled ? 'opacity-50 cursor-not-allowed' : ''"
        @input="onFreeInput"
        @focus="openDropdown"
        @keydown.escape="closeDropdown"
        @keydown.enter.prevent="selectFirst"
      />
    </div>

    <!-- Single mode trigger (select-style button) -->
    <button
      v-else-if="!multiple"
      ref="triggerRef"
      type="button"
      role="combobox"
      :aria-label="ariaLabel || placeholder"
      :aria-expanded="isOpen"
      :aria-controls="listboxId"
      aria-haspopup="listbox"
      class="form-input w-full text-left flex items-center justify-between gap-2 rounded-lg border-slate-300 text-sm"
      :class="disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
      :disabled="disabled"
      @click="toggleOpen"
    >
      <span
        class="min-w-0 flex items-center gap-2"
        :class="selectedLabel ? 'text-slate-900' : 'text-slate-400'"
      >
        <Icon
          v-if="selectedIcon"
          :icon="selectedIcon"
          class="h-4 w-4 shrink-0 text-slate-400"
        />
        <span class="truncate">{{ selectedLabel || placeholder }}</span>
      </span>
      <Icon
        icon="mdi:chevron-down"
        class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-150"
        :class="isOpen ? 'rotate-180' : ''"
      />
    </button>

    <!-- Multiple mode trigger (tag list) -->
    <div
      v-else
      ref="triggerRef"
      role="combobox"
      tabindex="0"
      :aria-label="ariaLabel || placeholder"
      :aria-expanded="isOpen"
      :aria-controls="listboxId"
      aria-haspopup="listbox"
      class="form-input w-full rounded-lg border-slate-300 text-sm min-h-[2.25rem] flex flex-wrap gap-1.5 items-center cursor-text"
      :class="disabled ? 'opacity-50 cursor-not-allowed' : ''"
      @click="!disabled && openDropdown()"
      @keydown.enter.prevent="!disabled && openDropdown()"
      @keydown.space.prevent="!disabled && openDropdown()"
      @keydown.down.prevent="!disabled && openDropdown()"
      @keydown.escape="closeDropdown"
    >
      <span
        v-for="val in selectedValues"
        :key="val"
        class="inline-flex items-center gap-1 bg-theme-100 text-theme-700 rounded px-1.5 py-0.5 text-xs font-medium"
      >
        <Icon
          v-if="iconFor(val)"
          :icon="iconFor(val)"
          class="h-3.5 w-3.5 shrink-0"
        />
        {{ labelFor(val) }}
        <button
          type="button"
          :aria-label="`Remove ${labelFor(val)}`"
          class="ml-0.5 text-theme-500 hover:text-theme-800 leading-none"
          @click.stop="removeValue(val)"
        >
          ×
        </button>
      </span>
      <span v-if="selectedValues.length === 0" class="text-slate-400 text-sm">{{
        placeholder
      }}</span>
    </div>

    <!-- Dropdown panel -->
    <div
      v-if="isOpen"
      :id="listboxId"
      role="listbox"
      :aria-multiselectable="multiple || undefined"
      class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden"
    >
      <!-- Search input inside dropdown (hidden in free-input mode since trigger IS the search) -->
      <div
        v-if="!allowFreeInput && searchable"
        class="p-2 border-b border-slate-100"
      >
        <input
          ref="searchInputRef"
          v-model="searchQuery"
          type="text"
          placeholder="Search…"
          aria-label="Search options"
          class="form-input w-full rounded-lg border-slate-300 text-sm"
          @keydown.escape="closeDropdown"
          @keydown.enter.prevent="selectFirst"
        />
      </div>

      <div class="max-h-64 overflow-y-auto">
        <div v-if="loading" class="p-3 text-sm text-slate-400 text-center">
          Loading…
        </div>
        <div
          v-else-if="filteredOptions.length === 0"
          class="p-3 text-sm text-slate-400 text-center"
        >
          No entries found
        </div>
        <button
          v-else
          v-for="option in filteredOptions"
          :key="option.value"
          type="button"
          role="option"
          :aria-selected="isSelected(option.value)"
          class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2"
          :class="
            option.disabled
              ? 'opacity-40 cursor-not-allowed text-slate-500'
              : isSelected(option.value)
                ? 'text-theme-700 bg-theme-50 font-medium'
                : 'text-slate-700 hover:bg-slate-50'
          "
          :disabled="option.disabled"
          @click="!option.disabled && selectOption(option)"
        >
          <span class="min-w-0 flex items-center gap-2">
            <Icon
              v-if="option.icon"
              :icon="option.icon"
              class="h-4 w-4 shrink-0"
              :class="
                isSelected(option.value) ? 'text-theme-600' : 'text-slate-400'
              "
            />
            <span class="truncate">{{ option.label }}</span>
          </span>
          <Icon
            v-if="isSelected(option.value)"
            icon="mdi:check"
            class="w-4 h-4 text-theme-600 shrink-0"
          />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  useId,
  watch,
} from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
  options: { type: Array, default: () => [] }, // [{ value: string, label: string, icon?: string }]
  modelValue: { default: null }, // string | string[] | null
  multiple: { type: Boolean, default: false },
  placeholder: { type: String, default: "Select…" },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  allowFreeInput: { type: Boolean, default: false }, // combobox / autocomplete mode
  clearable: { type: Boolean, default: true },
  searchable: { type: Boolean, default: true },
  ariaLabel: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "search", "open"]);

const containerRef = ref(null);
const searchInputRef = ref(null);
const freeInputRef = ref(null);
const triggerRef = ref(null);
const isOpen = ref(false);
const searchQuery = ref("");
const listboxId = `${useId()}-listbox`;

// ── helpers ──────────────────────────────────────────────────────────────────

function labelFor(value) {
  return props.options.find((o) => o.value === value)?.label ?? value;
}

function iconFor(value) {
  return props.options.find((o) => o.value === value)?.icon ?? "";
}

const selectedLabel = computed(() => {
  if (props.multiple) return "";
  const val = props.modelValue;
  if (val === null || val === undefined || val === "") return "";
  return labelFor(String(val));
});

const selectedIcon = computed(() => {
  if (props.multiple) return "";
  const val = props.modelValue;
  if (val === null || val === undefined || val === "") return "";
  return iconFor(String(val));
});

const selectedValues = computed(() => {
  if (!props.multiple) return [];
  return Array.isArray(props.modelValue) ? props.modelValue.map(String) : [];
});

const filteredOptions = computed(() => {
  // In free-input mode, filter by the typed value; otherwise use the search input inside the dropdown
  const q = (
    props.allowFreeInput ? String(props.modelValue ?? "") : searchQuery.value
  )
    .trim()
    .toLowerCase();
  if (!q) return props.options;
  return props.options.filter(
    (opt) =>
      opt.label.toLowerCase().includes(q) ||
      opt.value.toLowerCase().includes(q),
  );
});

function isSelected(value) {
  if (props.multiple) {
    return Array.isArray(props.modelValue) && props.modelValue.includes(value);
  }
  return String(props.modelValue ?? "") === value;
}

// ── open / close ──────────────────────────────────────────────────────────────

function toggleOpen() {
  if (isOpen.value) {
    closeDropdown();
  } else {
    openDropdown();
  }
}

async function openDropdown() {
  isOpen.value = true;
  emit("open");
  searchQuery.value = "";
  await nextTick();
  // In free-input mode the trigger input keeps focus; otherwise focus the inner search
  if (!props.allowFreeInput) {
    searchInputRef.value?.focus();
  }
}

function closeDropdown(restoreFocus = true) {
  isOpen.value = false;
  searchQuery.value = "";
  if (restoreFocus && !props.allowFreeInput) triggerRef.value?.focus();
}

// ── selection ─────────────────────────────────────────────────────────────────

function selectOption(option) {
  if (props.multiple) {
    const current = Array.isArray(props.modelValue)
      ? [...props.modelValue]
      : [];
    const idx = current.indexOf(option.value);
    emit(
      "update:modelValue",
      idx === -1
        ? [...current, option.value]
        : current.filter((v) => v !== option.value),
    );
    // keep dropdown open for multi-select
  } else {
    // In free-input mode, always set the value (don't toggle); otherwise toggle deselects
    if (props.allowFreeInput) {
      emit("update:modelValue", option.value);
    } else {
      emit(
        "update:modelValue",
        props.clearable && isSelected(option.value) ? null : option.value,
      );
    }
    closeDropdown();
  }
}

// Called when the user types in free-input mode
function onFreeInput(event) {
  emit("update:modelValue", event.target.value);
  emit("search", event.target.value);
  isOpen.value = true;
}

function removeValue(value) {
  if (!Array.isArray(props.modelValue)) return;
  emit(
    "update:modelValue",
    props.modelValue.filter((v) => v !== value),
  );
}

function selectFirst() {
  if (filteredOptions.value.length > 0) {
    selectOption(filteredOptions.value[0]);
  }
}

// ── search event (for server-side filtering support) ──────────────────────────

watch(searchQuery, (q) => emit("search", q));

// ── click outside ─────────────────────────────────────────────────────────────

function handleClickOutside(event) {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    closeDropdown(false);
  }
}

onMounted(() => document.addEventListener("mousedown", handleClickOutside));
onBeforeUnmount(() =>
  document.removeEventListener("mousedown", handleClickOutside),
);
</script>
