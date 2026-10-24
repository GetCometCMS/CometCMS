<template>
  <div class="flex flex-col" :class="{ 'min-h-0': layout === 'modal' }">
    <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 pb-3">
      <TabNavigation variant="pills" :items="tabItems" :model-value="tab" :aria-label="t('site.fonts.manage')" @update:model-value="changeTab" />
      <div v-if="tab !== 'upload'" class="ml-auto flex min-w-0 flex-1 items-center gap-2 sm:max-w-sm">
        <label class="relative min-w-0 flex-1">
          <span class="sr-only">{{ t('site.fonts.previewText') }}</span>
          <Icon icon="mdi:format-text" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input v-model="sampleInput" type="text" maxlength="120" :placeholder="t('site.fonts.previewText')" class="form-input w-full rounded-lg border-slate-300 py-1.5 pl-8 text-sm" />
        </label>
        <label class="flex shrink-0 items-center gap-1.5 text-xs text-slate-500">
          <span class="sr-only">{{ t('site.fonts.previewSize') }}</span>
          <input v-model.number="sampleSize" type="range" min="14" max="56" step="2" class="w-20 accent-theme-600" :aria-label="t('site.fonts.previewSize')" />
          <span class="w-9 tabular-nums">{{ sampleSize }}px</span>
        </label>
      </div>
    </div>

    <div class="space-y-4 pt-4" :class="{ 'min-h-0 flex-1 overflow-y-auto pr-1': layout === 'modal' }">
      <p v-if="error" role="alert" class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700"><Icon icon="mdi:alert-circle-outline" class="mt-0.5 h-4 w-4 shrink-0" />{{ error }}</p>
      <p v-if="notice" role="status" class="flex items-start gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800"><Icon icon="mdi:check-circle-outline" class="mt-0.5 h-4 w-4 shrink-0" />{{ notice }}</p>

      <section v-if="jobs.length" class="space-y-3 rounded-xl border border-theme-200 bg-theme-50/40 p-4">
        <h3 class="text-sm font-semibold text-slate-900">{{ t('site.fonts.installations') }}</h3>
        <div v-for="job in jobs" :key="job.id" class="space-y-2">
          <div class="flex items-center justify-between gap-2 text-sm"><span class="font-medium">{{ job.name }}</span><span class="tabular-nums text-slate-500">{{ job.cursor }} / {{ job.total }}</span></div>
          <div class="h-1.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-label="job.name" :aria-valuenow="job.cursor" aria-valuemin="0" :aria-valuemax="job.total">
            <div class="h-full rounded-full bg-theme-600 transition-[width]" :style="{ width: `${job.total ? (job.cursor / job.total) * 100 : 0}%` }"></div>
          </div>
          <div v-if="runningJob !== job.id" class="flex gap-2">
            <button type="button" class="btn-primary btn-sm" :disabled="!!busy" @click="runJob(job)">{{ t('site.fonts.resume') }}</button>
            <button type="button" class="btn-secondary btn-sm" :disabled="!!busy" @click="cancelJob(job)">{{ t('common.cancel') }}</button>
          </div>
          <button v-else type="button" class="btn-secondary btn-sm" @click="cancelJob(job)">{{ t('common.cancel') }}</button>
        </div>
      </section>

      <!-- Installed -->
      <template v-if="tab === 'installed'">
        <section v-for="group in installedGroups" :key="group.key" class="space-y-2">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t(`site.fonts.${group.key}`) }} <span class="font-normal">· {{ group.fonts.length }}</span></h3>
          <div class="grid gap-3" :class="gridClass">
            <article v-for="font in group.fonts" :key="font.id" v-font-visible="font.id" :data-font-id="font.id" class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-slate-300">
              <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                  <h4 class="truncate text-sm font-semibold text-slate-900">{{ font.name }}</h4>
                  <p class="truncate text-xs text-slate-500">{{ categoryLabel(font.category) }}<template v-if="font.version"> · {{ font.version }}</template><template v-if="font.subsets?.length"> · {{ font.subsets.join(', ') }}</template></p>
                </div>
                <span v-if="font.variable" class="badge shrink-0 bg-slate-100 text-slate-600 ring-slate-500/15">{{ t('site.fonts.variable') }}</span>
              </div>
              <p v-if="font.available === false" class="mt-3 flex items-start gap-1.5 rounded-lg bg-amber-50 p-2 text-xs text-amber-800"><Icon icon="mdi:alert-outline" class="mt-px h-3.5 w-3.5 shrink-0" />{{ t('site.fonts.missingHint') }}</p>
              <p v-else class="mt-3 min-h-[1.4em] break-words leading-tight text-slate-900" :style="specimenStyle(visible.has(font.id) ? site.data?.font_stacks?.[font.id] : null)">{{ sampleText }}</p>
              <div v-if="font.source !== 'system'" class="mt-auto flex flex-wrap items-center gap-2 pt-3">
                <span v-if="used(font)" class="badge badge-dot bg-theme-50 text-theme-700 ring-theme-600/20" :title="usageTitle(font)">{{ t('site.fonts.inUse') }}</span>
                <span class="flex-1"></span>
                <button v-if="font.source === 'fontsource'" type="button" class="btn-ghost btn-sm" :disabled="!!busy" :title="t('site.fonts.update')" @click="startInstall(font.provider_id, font.subsets, true)"><Icon icon="mdi:refresh" class="h-4 w-4" /><span class="sr-only sm:not-sr-only">{{ t('site.fonts.update') }}</span></button>
                <button v-if="font.source === 'custom'" type="button" data-action="add-faces" class="btn-ghost btn-sm" :disabled="!!busy" @click="addFaces(font)"><Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.fonts.addFaces') }}</button>
                <button type="button" data-action="remove" class="btn-ghost btn-sm text-red-600 hover:bg-red-50 hover:text-red-700" :disabled="!!busy || used(font)" :title="used(font) ? t('site.fonts.removeBlocked') : t('common.delete')" @click="removing = font"><Icon icon="mdi:trash-can-outline" class="h-4 w-4" /><span class="sr-only">{{ t('common.delete') }}</span></button>
              </div>
            </article>
          </div>
        </section>
        <div v-if="!families.length" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">{{ t('site.fonts.empty') }}</div>
      </template>

      <!-- Catalogue -->
      <template v-else-if="tab === 'catalogue'">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <form class="relative min-w-0 flex-1" role="search" @submit.prevent="search(0)">
              <Icon icon="mdi:magnify" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input v-model="query" type="search" :aria-label="t('site.fonts.search')" :placeholder="t('site.fonts.search')" class="form-input w-full rounded-lg border-slate-300 pl-9 text-sm" />
            </form>
            <select v-model="sortOrder" class="form-input w-auto text-sm" :aria-label="t('site.fonts.sort')" :disabled="!!busy" @change="search(0)">
              <option value="popular">{{ t('site.fonts.popular') }}</option>
              <option value="alphabetical">{{ t('site.fonts.alphabetical') }}</option>
            </select>
          </div>
          <div class="flex flex-wrap items-center gap-1.5" role="group" :aria-label="t('site.fonts.allCategories')">
            <button v-for="category in ['', ...catalogueCategories]" :key="category || 'all'" type="button" class="rounded-full border px-3 py-1 text-xs font-medium transition-colors" :class="categoryFilter === category ? 'border-theme-600 bg-theme-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900'" :aria-pressed="categoryFilter === category" @click="setCategory(category)">{{ category ? categoryLabel(category) : t('site.fonts.allCategories') }}</button>
            <span class="ml-auto text-xs tabular-nums text-slate-500">{{ t('site.fonts.results', { count: total }) }}</span>
          </div>
          <p v-if="stale" class="text-xs text-amber-700">{{ t('site.fonts.stale') }}</p>
        </div>

        <div v-if="busy === 'search' && !catalogue.length" class="grid gap-3" :class="gridClass">
          <div v-for="n in 6" :key="n" class="h-32 animate-pulse rounded-xl bg-slate-100"></div>
        </div>
        <p v-else-if="!catalogue.length" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">{{ t('site.fonts.noResults') }}</p>

        <div class="grid gap-3" :class="gridClass">
          <article v-for="font in catalogue" :key="font.id" v-font-visible="`catalogue:${font.id}`" :data-font-id="font.id" class="flex flex-col rounded-xl border bg-white transition-colors" :class="selected?.id === font.id ? 'col-span-full border-theme-400 ring-1 ring-theme-400' : 'border-slate-200 hover:border-slate-300'">
            <button type="button" class="flex flex-1 flex-col p-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-theme-500" :aria-expanded="selected?.id === font.id" @click="selectFont(font)">
              <span class="flex w-full items-start gap-2">
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-semibold text-slate-900">{{ font.family }}</span>
                  <span class="block truncate text-xs text-slate-500">{{ categoryLabel(font.category) }} · {{ t('site.fonts.subsetCount', { count: font.subsets.length }) }}</span>
                </span>
                <span v-if="installed(font.id)" class="badge shrink-0 bg-emerald-50 text-emerald-700 ring-emerald-600/20"><Icon icon="mdi:check" class="h-3 w-3" />{{ t('site.fonts.installed') }}</span>
                <span v-else-if="font.variable" class="badge shrink-0 bg-slate-100 text-slate-600 ring-slate-500/15">{{ t('site.fonts.variable') }}</span>
              </span>
              <span v-if="previews[font.id] === 'ready'" class="mt-3 block break-words leading-tight text-slate-900" :style="specimenStyle(`'comet-preview-${font.id}'`, selected?.id === font.id ? 1.5 : 1)">{{ sampleText }}</span>
              <span v-else-if="previews[font.id] === 'error'" class="mt-3 block text-xs text-slate-400">{{ t('site.fonts.previewFailed') }}</span>
              <span v-else class="mt-3 block space-y-2" aria-hidden="true"><span class="block h-5 w-4/5 animate-pulse rounded bg-slate-100"></span><span class="block h-5 w-3/5 animate-pulse rounded bg-slate-100"></span></span>
            </button>
            <div v-if="selected?.id === font.id" class="space-y-3 border-t border-slate-100 p-4">
              <fieldset>
                <legend class="mb-2 text-xs font-medium text-slate-700">{{ t('site.fonts.subsets') }}</legend>
                <div class="flex flex-wrap gap-1.5">
                  <label v-for="subset in font.subsets" :key="subset" class="flex cursor-pointer items-center gap-1.5 rounded-md border px-2 py-1 text-xs transition-colors" :class="selectedSubsets.includes(subset) ? 'border-theme-300 bg-theme-50 text-theme-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'"><input v-model="selectedSubsets" type="checkbox" :value="subset" class="h-3.5 w-3.5 rounded border-slate-300 text-theme-600" />{{ subset }}</label>
                </div>
              </fieldset>
              <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary btn-sm" :disabled="!!busy || !selectedSubsets.length || installed(font.id)" @click="startInstall(font.id, selectedSubsets)"><Icon icon="mdi:download" class="h-4 w-4" />{{ installed(font.id) ? t('site.fonts.installed') : t('site.fonts.install') }}</button>
                <button type="button" class="btn-ghost btn-sm" @click="selected = null">{{ t('common.close') }}</button>
                <span class="text-xs text-slate-500">{{ font.license }}</span>
              </div>
            </div>
          </article>
        </div>
        <div v-if="catalogue.length < total" class="flex justify-center">
          <button type="button" class="btn-secondary btn-sm" :disabled="!!busy" @click="search(catalogue.length, true)">{{ busy === 'search' ? t('common.loading') : t('site.fonts.loadMore') }}</button>
        </div>
      </template>

      <!-- Upload -->
      <form v-else class="space-y-4" @submit.prevent="upload">
        <p v-if="uploadFamily" class="flex items-center gap-2 rounded-lg bg-theme-50 p-3 text-sm text-theme-900"><Icon icon="mdi:plus-circle-outline" class="h-4 w-4 shrink-0" />{{ t('site.fonts.addFaces') }}: <strong>{{ uploadName }}</strong></p>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block"><span class="form-label">{{ t('site.fonts.familyName') }}</span><input v-model="uploadName" required maxlength="160" :readonly="!!uploadFamily" class="form-input w-full rounded-lg border-slate-300 text-sm" /></label>
          <label class="block"><span class="form-label">{{ t('site.fonts.category') }}</span><select v-model="uploadCategory" :disabled="!!uploadFamily" class="form-select w-full rounded-lg border-slate-300 text-sm"><option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option></select></label>
        </div>
        <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors" :class="dragging ? 'border-theme-400 bg-theme-50' : 'border-slate-300 hover:border-slate-400 hover:bg-slate-50'" @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="dropFiles">
          <Icon icon="mdi:tray-arrow-up" class="h-6 w-6 text-slate-400" />
          <span class="text-sm font-medium text-slate-700">{{ t('site.fonts.dropFiles') }}</span>
          <span class="text-xs text-slate-500">{{ t('site.fonts.uploadLimits') }}</span>
          <input :key="fileKey" type="file" accept=".woff2" multiple :required="!uploads.length" class="sr-only" :aria-label="t('site.fonts.files')" @change="chooseFiles($event.target.files)" />
        </label>
        <div v-if="uploads.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
          <div v-for="(face, index) in uploads" :key="face.alias" class="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
            <div class="min-w-0">
              <p class="truncate text-xs text-slate-500">{{ face.file.name }}</p>
              <p class="truncate text-xl leading-tight text-slate-900" :style="{ fontFamily: face.ready ? `'${face.alias}'` : 'inherit', fontStyle: face.style }">{{ sampleText }}</p>
            </div>
            <div class="flex items-end gap-2">
              <label class="text-xs text-slate-600">{{ t('site.fonts.style') }}<select v-model="face.style" class="form-select mt-1 block w-24 rounded-lg border-slate-300 py-1 text-xs"><option value="normal">{{ t('site.fonts.normal') }}</option><option value="italic">{{ t('site.fonts.italic') }}</option></select></label>
              <label class="text-xs text-slate-600">{{ t('site.fonts.weightFrom') }}<input v-model.number="face.min" required type="number" min="1" max="1000" class="form-input mt-1 block w-20 rounded-lg border-slate-300 py-1 text-xs" /></label>
              <label class="text-xs text-slate-600">{{ t('site.fonts.weightTo') }}<input v-model.number="face.max" required type="number" :min="face.min" max="1000" class="form-input mt-1 block w-20 rounded-lg border-slate-300 py-1 text-xs" /></label>
              <button type="button" class="icon-btn h-8 w-8" :aria-label="t('common.delete')" @click="removeUpload(index)"><Icon icon="mdi:close" class="h-4 w-4" /></button>
            </div>
          </div>
        </div>
        <p class="text-xs text-slate-500">{{ t('site.fonts.uploadHint') }}</p>
        <label class="flex items-start gap-2 text-sm text-slate-700"><input v-model="licensed" required type="checkbox" class="mt-0.5 rounded border-slate-300 text-theme-600" />{{ t('site.fonts.license') }}</label>
        <div class="flex justify-end gap-2">
          <button v-if="uploadFamily" type="button" class="btn-secondary" @click="changeTab('upload')">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary" :disabled="!!busy || !uploads.length || !licensed">{{ busy === 'upload' ? t('common.saving') : t('site.fonts.upload') }}</button>
        </div>
      </form>

      <p class="flex items-start gap-1.5 text-xs text-slate-400"><Icon icon="mdi:shield-lock-outline" class="mt-px h-3.5 w-3.5 shrink-0" />{{ t('site.fonts.localHint') }}</p>
    </div>

    <ConfirmModal :model-value="!!removing" :title="t('site.fonts.removeTitle')" :message="t('site.fonts.removeMessage', { name: removing?.name ?? '' })" :confirm-label="t('common.delete')" @update:model-value="removing = null" @confirm="removeFont(removing)" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import TabNavigation from '../TabNavigation.vue'
import ConfirmModal from '../ConfirmModal.vue'
import { api, getActiveWorkspace } from '../../api/index.js'
import { useSiteStore } from '../../stores/site.js'
import { useI18n } from '../../i18n/index.js'
import { defaultFontSubsets, guessFontFace, referencesFont } from '../../composables/fontLibrary.js'

const props = defineProps({
  draftNodes: { type: Array, default: () => [] },
  /** Wider grids on the settings page, two columns inside the modal. */
  layout: { type: String, default: 'modal' },
})
const emit = defineEmits(['busy'])
const { t } = useI18n()
const site = useSiteStore()
const tab = ref('installed'), error = ref(''), notice = ref(''), busy = ref('')
const families = computed(() => site.data?.font_families ?? [])
const jobs = ref([]), runningJob = ref('')
const query = ref(''), categoryFilter = ref(''), catalogue = ref([]), total = ref(0), stale = ref(false)
const sortOrder = ref('popular')
const selected = ref(null), selectedSubsets = ref([]), removing = ref(null)
const sampleInput = ref(''), sampleSize = ref(24)
const sampleText = computed(() => sampleInput.value.trim() || t('site.fonts.sample'))
const uploadFamily = ref(''), uploadName = ref(''), uploadCategory = ref('sans-serif'), uploads = ref([]), licensed = ref(false), fileKey = ref(0), dragging = ref(false)
const categories = ['sans-serif', 'serif', 'monospace', 'cursive', 'fantasy']
const catalogueCategories = ['sans-serif', 'serif', 'display', 'handwriting', 'monospace']
const gridClass = computed(() => props.layout === 'page' ? 'sm:grid-cols-2 xl:grid-cols-3' : 'sm:grid-cols-2')
const tabItems = computed(() => [
  { value: 'installed', label: t('site.fonts.installed'), icon: 'mdi:format-font', suffix: families.value.length ? `(${families.value.length})` : '', disabled: !!busy.value },
  { value: 'catalogue', label: t('site.fonts.catalogue'), icon: 'mdi:book-open-variant-outline', disabled: !!busy.value },
  { value: 'upload', label: t('site.fonts.upload'), icon: 'mdi:tray-arrow-up', disabled: !!busy.value },
])
const installedGroups = computed(() => [
  { key: 'installed', fonts: families.value },
  { key: 'system', fonts: (site.data?.fonts ?? []).filter((id) => !families.value.some((family) => family.id === id)).map((id) => ({ id, name: t(`site.font.${id}`), source: 'system' })) },
].filter((group) => group.fonts.length))

let generation = 0
let searchTimer = null
const cancelled = new Set()
const installed = (id) => families.value.some((font) => font.id === `fontsource-${id}`)
const draftUses = (id) => referencesFont(site.draft?.theme, id) || referencesFont(props.draftNodes, id)
const used = (font) => !!font.usages?.length || draftUses(font.id)
const usageTitle = (font) => [...(font.usages ?? []), ...(draftUses(font.id) ? [t('site.fonts.draft')] : [])].join('\n')
const current = (token, workspace) => generation === token && getActiveWorkspace() === workspace
const categoryLabel = (category) => category ? t(`site.fonts.cat.${category}`) : ''
const specimenStyle = (family, scale = 1) => ({ fontFamily: family || 'inherit', fontSize: `${sampleSize.value * scale}px`, visibility: family ? 'visible' : 'hidden' })

watch(busy, (value) => emit('busy', !!value))

// Specimens and catalogue previews only load once a card scrolls into view.
const visible = reactive(new Set())
// Created during setup so the cards' directives can register as they mount.
const observer = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver((entries) => {
  for (const entry of entries) {
    if (!entry.isIntersecting) continue
    observer.unobserve(entry.target)
    reveal(entry.target.dataset.visibleKey)
  }
}, { rootMargin: '200px' })
const vFontVisible = {
  mounted(el, { value }) {
    el.dataset.visibleKey = value
    if (observer) observer.observe(el)
    else reveal(value)
  },
  updated(el, { value }) {
    el.dataset.visibleKey = value
  },
  beforeUnmount(el) {
    observer?.unobserve(el)
  },
}
function reveal(key) {
  if (key.startsWith('catalogue:')) requestPreview(key.slice(10))
  else visible.add(key)
}

// Catalogue previews: one small WOFF2 per family, fetched through the server a few at a time.
const previews = reactive({})
const previewFaces = new Map()
const previewQueue = []
let previewsRunning = 0
function requestPreview(id) {
  if (previews[id]) return
  previews[id] = 'queued'
  previewQueue.push(id)
  pumpPreviews()
}
function pumpPreviews() {
  while (previewsRunning < 3 && previewQueue.length) {
    const id = previewQueue.shift()
    previewsRunning++
    loadPreview(id).finally(() => { previewsRunning--; pumpPreviews() })
  }
}
async function loadPreview(id) {
  const token = generation, workspace = getActiveWorkspace()
  try {
    const response = await api.fonts.preview(id)
    if (!current(token, workspace)) return
    const face = new FontFace(`comet-preview-${id}`, `url(${JSON.stringify(response.data.url)})`)
    await face.load()
    if (!current(token, workspace)) return
    document.fonts.add(face)
    previewFaces.set(id, face)
    previews[id] = 'ready'
  } catch {
    if (current(token, workspace)) previews[id] = 'error'
  }
}
function clearPreviewQueue() {
  for (const id of previewQueue.splice(0)) delete previews[id]
}

async function perform(kind, action) {
  if (busy.value) return
  const token = generation, workspace = getActiveWorkspace()
  busy.value = kind
  error.value = ''
  notice.value = ''
  try {
    await action(() => current(token, workspace))
  } catch (err) {
    if (current(token, workspace)) error.value = err.message
  } finally {
    if (current(token, workspace)) busy.value = ''
  }
}

async function refreshJobs() {
  const token = generation, workspace = getActiveWorkspace()
  try {
    const response = await api.fonts.jobs()
    if (current(token, workspace)) jobs.value = response.data
  } catch (err) {
    if (current(token, workspace)) error.value = err.message
  }
}

function resetUpload() {
  for (const face of uploads.value) if (face.face) document.fonts.delete(face.face)
  uploadFamily.value = ''
  uploadName.value = ''
  uploadCategory.value = 'sans-serif'
  uploads.value = []
  licensed.value = false
  fileKey.value++
}

async function changeTab(view) {
  tab.value = view
  error.value = ''
  notice.value = ''
  if (view === 'catalogue' && !catalogue.value.length) await search(0)
  if (view === 'upload') resetUpload()
}

async function search(nextOffset, append = false) {
  clearTimeout(searchTimer)
  if (!append) clearPreviewQueue()
  await perform('search', async (active) => {
    const response = await api.fonts.catalogue({ q: query.value, category: categoryFilter.value, sort: sortOrder.value, offset: nextOffset })
    if (!active()) return
    catalogue.value = append ? [...catalogue.value, ...response.data.families] : response.data.families
    total.value = response.data.total
    stale.value = response.data.stale
    if (!append) selected.value = null
  })
}
watch(query, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => search(0), 300)
})
function setCategory(category) {
  categoryFilter.value = category
  search(0)
}

function selectFont(font) {
  if (selected.value?.id === font.id) {
    selected.value = null
    return
  }
  selected.value = font
  selectedSubsets.value = defaultFontSubsets(font)
}

async function advance(job, active) {
  runningJob.value = job.id
  try {
    while (active() && job.status === 'pending' && !cancelled.has(job.id)) {
      job = (await api.fonts.step(job.id, job.cursor)).data
      if (!active()) return
      jobs.value = jobs.value.map((existing) => existing.id === job.id ? job : existing)
    }
    if (active()) {
      await site.refreshFonts()
      if (!active()) return
      await refreshJobs()
      if (job.status === 'complete') notice.value = t('site.fonts.complete')
    }
  } finally {
    if (active()) runningJob.value = ''
  }
}

async function startInstall(id, subsets, update = false) {
  await perform('install', async (active) => {
    const job = (await api.fonts.install({ id, subsets: [...subsets], update })).data
    if (!active()) return
    jobs.value.push(job)
    await advance(job, active)
  })
}

async function runJob(job) {
  cancelled.delete(job.id)
  await perform('install', (active) => advance(job, active))
}

async function cancelJob(job) {
  const token = generation, workspace = getActiveWorkspace()
  cancelled.add(job.id)
  try {
    await api.fonts.cancel(job.id)
    if (current(token, workspace)) {
      await site.refreshFonts()
      await refreshJobs()
    }
  } catch (err) {
    if (current(token, workspace)) error.value = err.message
  }
}

async function removeFont(font) {
  removing.value = null
  if (!font || used(font)) return
  await perform('remove', async (active) => {
    const response = await api.fonts.remove(font.id)
    if (active()) site.applyFonts(response.data)
  })
}

function addFaces(font) {
  resetUpload()
  tab.value = 'upload'
  uploadFamily.value = font.id
  uploadName.value = font.name
  uploadCategory.value = font.category
}

function chooseFiles(files) {
  const token = generation
  const added = [...files].filter((file) => file.name.toLowerCase().endsWith('.woff2')).map((file, index) => ({ file, ...guessFontFace(file.name), alias: `comet-upload-${token}-${fileKey.value}-${uploads.value.length + index}`, ready: false, face: null }))
  uploads.value = [...uploads.value, ...added]
  if (!uploadName.value && added.length) uploadName.value = guessFontFace(added[0].file.name).family
  // Local specimens only; nothing is sent until the form is submitted.
  for (const entry of added) {
    entry.file.arrayBuffer().then((bytes) => new FontFace(entry.alias, bytes).load()).then((face) => {
      if (token !== generation || !uploads.value.some((item) => item.alias === entry.alias)) return
      document.fonts.add(face)
      const item = uploads.value.find((existing) => existing.alias === entry.alias)
      item.face = face
      item.ready = true
    }).catch(() => {})
  }
}

function dropFiles(event) {
  dragging.value = false
  chooseFiles(event.dataTransfer?.files ?? [])
}

function removeUpload(index) {
  const [face] = uploads.value.splice(index, 1)
  if (face?.face) document.fonts.delete(face.face)
  if (!uploads.value.length) fileKey.value++
}

async function upload() {
  await perform('upload', async (active) => {
    const body = new FormData()
    body.append('name', uploadName.value)
    body.append('category', uploadCategory.value)
    body.append('family_id', uploadFamily.value)
    body.append('licensed', String(licensed.value))
    body.append('faces', JSON.stringify(uploads.value.map((face) => ({ style: face.style, weight: [face.min, face.max] }))))
    for (const face of uploads.value) body.append('fonts[]', face.file)
    const response = await api.fonts.upload(body)
    if (!active()) return
    site.applyFonts(response.data)
    resetUpload()
    tab.value = 'installed'
    notice.value = t('site.fonts.complete')
  })
}

function reset() {
  generation++
  clearTimeout(searchTimer)
  busy.value = ''
  error.value = ''
  notice.value = ''
  jobs.value = []
  catalogue.value = []
  selected.value = null
  removing.value = null
  runningJob.value = ''
  cancelled.clear()
  resetUpload()
  previewQueue.splice(0)
  for (const key of Object.keys(previews)) delete previews[key]
  for (const face of previewFaces.values()) document.fonts.delete(face)
  previewFaces.clear()
  visible.clear()
}

function load() {
  tab.value = 'installed'
  refreshJobs()
  const token = generation, workspace = getActiveWorkspace()
  site.refreshFonts().catch((err) => { if (current(token, workspace)) error.value = err.message })
}

onMounted(load)
watch(() => site.data?.workspace, (workspace, previous) => {
  if (previous === undefined) return
  reset()
  load()
})
onBeforeUnmount(() => {
  reset()
  observer?.disconnect()
})
</script>
