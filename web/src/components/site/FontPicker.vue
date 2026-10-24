<template>
  <div>
    <button
      :id="inputId" ref="trigger" v-font-visible="'trigger'" type="button" role="combobox"
      aria-haspopup="listbox" :aria-expanded="open" :aria-controls="listId"
      class="form-input flex w-full items-center justify-between gap-2 rounded-lg border-slate-300 text-left text-sm"
      @click="open ? close() : show()" @keydown.down.prevent="show()" @keydown.up.prevent="show()"
      @keydown.esc.stop.prevent="close()" @focusout="onFocusOut"
    >
      <span class="truncate" :style="fontStyle(modelValue, 'trigger')">{{ selectedLabel }}</span>
      <Icon icon="mdi:chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
    </button>
    <p v-if="unavailable(modelValue)" class="mt-1 text-xs text-amber-700" role="status">{{ t('site.fonts.missingHint') }}</p>

    <Teleport to="body">
      <div
        v-if="open" ref="panel" :style="panelStyle"
        class="fixed z-[60] flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
        @keydown.esc.stop.prevent="close()" @keydown.tab="close()" @focusout="onFocusOut"
      >
        <div class="shrink-0 border-b border-slate-100 p-2">
          <input
            ref="search" v-model="query" type="search" role="combobox" aria-autocomplete="list"
            :aria-expanded="true" :aria-controls="listId" :aria-activedescendant="activeId"
            :placeholder="t('select.search')" :aria-label="t('select.searchOptions')"
            class="form-input w-full rounded-lg border-slate-300 text-sm"
            @keydown.down.prevent="step(1)" @keydown.up.prevent="step(-1)"
            @keydown.home.prevent="activate(0)" @keydown.end.prevent="activate(options.length - 1)"
            @keydown.enter.prevent="choose(options[active])"
          />
        </div>
        <div :id="listId" role="listbox" :aria-label="t('site.fonts.familyName')" class="min-h-0 overflow-y-auto p-1">
          <div v-for="group in groups" :key="group.key" role="group" :aria-label="group.label || undefined">
            <div v-if="group.label" class="px-2 pb-1 pt-2 text-xs font-semibold text-slate-500">{{ group.label }}</div>
            <div
              v-for="option in group.options" :id="optionId(option)" :key="option.value"
              v-font-visible="`option:${option.value}`" role="option" :data-value="option.value"
              :aria-selected="modelValue === option.value"
              class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-2 text-sm"
              :class="optionId(option) === activeId ? 'bg-theme-50 text-theme-800' : 'text-slate-700 hover:bg-slate-50'"
              @mousedown.prevent @click="choose(option)" @mousemove="active = options.indexOf(option)"
            >
              <span class="truncate" :style="fontStyle(option.value, `option:${option.value}`)">{{ option.label }}</span>
              <Icon v-if="modelValue === option.value" icon="mdi:check" class="h-4 w-4 shrink-0 text-theme-600" />
            </div>
          </div>
          <div v-if="!options.length" class="px-2 py-3 text-sm text-slate-500">{{ t('select.noResults') }}</div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useId, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useSiteStore } from '../../stores/site.js'
import { useI18n } from '../../i18n/index.js'

const props = defineProps({
  id: { type: String, default: undefined },
  modelValue: { type: String, default: '' },
  allowEmpty: Boolean,
  emptyLabel: { type: String, default: '' },
  roles: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const site = useSiteStore()
const { t } = useI18n()
const uid = useId()
const inputId = computed(() => props.id || `font-${uid}`)
const listId = `font-${uid}-options`
const families = computed(() => site.data?.font_families ?? [])
const resolveFont = (value) => ['body', 'heading'].includes(value) ? site.draft?.theme?.fonts?.[value] : value
const missing = computed(() => props.modelValue && !['body', 'heading'].includes(props.modelValue) && !(site.data?.fonts ?? []).includes(props.modelValue))
function unavailable(value) {
  const id = resolveFont(value)
  return !!id && (!(site.data?.fonts ?? []).includes(id) || families.value.find((font) => font.id === id)?.available === false)
}
const fontOption = (font) => ({ value: font.id, label: `${font.name}${font.available === false ? ` · ${t('site.fonts.unavailable')}` : ''}` })
const allGroups = computed(() => [
  { key: 'choices', options: [
    ...(props.allowEmpty ? [{ value: '', label: props.emptyLabel || t('site.option.default') }] : []),
    ...(props.roles ? ['body', 'heading'].map((value) => ({ value, label: t(`site.theme.${value}Font`) })) : []),
    ...(missing.value ? [{ value: props.modelValue, label: `${props.modelValue} · ${t('site.fonts.unavailable')}` }] : []),
  ] },
  { key: 'system', label: t('site.fonts.system'), options: (site.data?.fonts ?? []).filter((id) => !families.value.some((family) => family.id === id)).map((value) => ({ value, label: t(`site.font.${value}`) })) },
  { key: 'installed', label: t('site.fonts.installed'), options: families.value.map(fontOption) },
])
const selectedLabel = computed(() => allGroups.value.flatMap((group) => group.options).find((option) => option.value === props.modelValue)?.label || props.emptyLabel || t('site.option.default'))
const query = ref('')
const groups = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase()
  return allGroups.value.map((group) => ({ ...group, options: group.options.filter((option) => `${option.label} ${option.value}`.toLocaleLowerCase().includes(needle)) })).filter((group) => group.options.length)
})
const options = computed(() => groups.value.flatMap((group) => group.options))
const open = ref(false), trigger = ref(null), panel = ref(null), search = ref(null)
const active = ref(-1), panelStyle = ref({})
const optionId = (option) => `${listId}-${options.value.indexOf(option)}`
const activeId = computed(() => active.value >= 0 && options.value[active.value] ? optionId(options.value[active.value]) : undefined)

// Only apply a face to the selected label or menu rows that enter view. The
// browser shares these @font-face files with the library and published website.
const visible = reactive(new Set())
const observer = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver((entries) => {
  for (const entry of entries) {
    if (!entry.isIntersecting) continue
    visible.add(entry.target.dataset.fontVisible)
    observer.unobserve(entry.target)
  }
})
const vFontVisible = {
  mounted(el, { value }) {
    el.dataset.fontVisible = value
    if (observer) observer.observe(el)
    else visible.add(value)
  },
  beforeUnmount(el) { observer?.unobserve(el) },
}
function fontStyle(value, key) {
  return { fontFamily: visible.has(key) && !unavailable(value) ? site.data?.font_stacks?.[resolveFont(value)] : undefined }
}

function position() {
  if (!open.value || !trigger.value) return
  const rect = trigger.value.getBoundingClientRect()
  const below = window.innerHeight - rect.bottom - 8
  const above = rect.top - 8
  const upwards = below < 240 && above > below
  const width = Math.min(Math.max(rect.width, 260), window.innerWidth - 16)
  panelStyle.value = {
    left: `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`,
    width: `${width}px`,
    maxHeight: `${Math.max(80, Math.min(360, upwards ? above : below))}px`,
    ...(upwards ? { bottom: `${window.innerHeight - rect.top + 4}px` } : { top: `${rect.bottom + 4}px` }),
  }
}
async function show() {
  if (open.value) return
  query.value = ''
  open.value = true
  position()
  await nextTick()
  active.value = Math.max(0, options.value.findIndex((option) => option.value === props.modelValue))
  await nextTick()
  search.value?.focus()
  scrollActive()
}
function close(restore = true) {
  open.value = false
  if (restore) trigger.value?.focus()
}
function scrollActive() {
  if (activeId.value) document.getElementById(activeId.value)?.scrollIntoView({ block: 'nearest' })
}
async function activate(index) {
  active.value = Math.max(0, Math.min(options.value.length - 1, index))
  await nextTick()
  scrollActive()
}
function step(delta) { activate(active.value + delta) }
function choose(option) {
  if (!option) return
  emit('update:modelValue', option.value)
  close()
}
const contains = (target) => trigger.value?.contains(target) || panel.value?.contains(target)
function onFocusOut(event) { if (!contains(event.relatedTarget)) close(false) }
function outside(event) { if (!contains(event.target)) close(false) }
function onScroll(event) { if (!panel.value?.contains(event.target)) position() }
watch(query, () => activate(0))
watch(() => site.data?.workspace, () => close(false))
onMounted(() => {
  document.addEventListener('pointerdown', outside)
  window.addEventListener('resize', position)
  window.addEventListener('scroll', onScroll, true)
})
onBeforeUnmount(() => {
  observer?.disconnect()
  document.removeEventListener('pointerdown', outside)
  window.removeEventListener('resize', position)
  window.removeEventListener('scroll', onScroll, true)
})
</script>
