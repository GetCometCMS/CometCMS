<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
      <label for="menu-selector" class="text-sm font-medium text-slate-700">{{ t('site.menus.menu') }}</label>
      <select id="menu-selector" :value="`menu:${selectedName}`" class="form-select min-w-0 max-w-full rounded-lg border-slate-300 text-sm" @change="chooseMenu($event.target)">
        <option v-if="!menus.length" value="" disabled>{{ t('site.menus.empty') }}</option>
        <option v-for="menu in menus" :key="menu.name" :value="`menu:${menu.name}`">{{ menu.label }} ({{ itemCount(menu.items) }})</option>
        <option value="__create__" :disabled="menus.length >= limits.max_menus">{{ t('site.menus.createMenu') }}</option>
      </select>
      <button v-if="selected" type="button" class="btn-secondary btn-sm" :aria-expanded="settingsOpen" @click="settingsOpen = !settingsOpen">
        <Icon icon="mdi:cog-outline" class="h-4 w-4" />{{ t('site.menus.settings') }}
      </button>
    </div>
    <div v-if="selected && settingsOpen" class="card space-y-3 p-4">
      <label class="form-label" for="menu-label">{{ t('site.menus.label') }}</label>
      <input id="menu-label" v-model="selected.label" type="text" maxlength="80" class="form-input w-full rounded-lg border-slate-300 text-sm" />
      <p class="text-xs text-slate-500">{{ t('site.menus.name') }} <code class="rounded bg-slate-100 px-1">{{ selected.name }}</code></p>
      <button type="button" class="btn-danger-subtle btn-sm" @click="deleteMenuOpen = true">{{ t('site.menus.deleteMenu') }}</button>
    </div>
    <section v-if="selected" class="card min-w-0">
      <div class="grid min-w-0 grid-cols-1 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="card-title">{{ t('site.menus.structure') }}</h2>
            <button type="button" class="btn-primary btn-sm" :disabled="selected.items.length >= limits.max_siblings" @click="startAdd()">
              <Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.menus.addItem') }}
            </button>
          </div>
          <p class="mb-3 text-xs text-slate-500">{{ t('site.menus.treeHint', { count: limits.max_depth }) }}</p>
          <div v-if="!selected.items.length" class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center">
            <Icon icon="mdi:format-list-bulleted" class="mx-auto mb-2 h-8 w-8 text-slate-300" />
            <p class="text-sm text-slate-600">{{ t('site.menus.emptyItems') }}</p>
          </div>
          <ul v-else ref="tree" role="tree" :aria-label="t('site.menus.structure')" class="space-y-1">
            <MenuTreeNode v-for="item in selected.items" :key="item.id" :item="item" :items="selected.items"
              :selected-id="selectedId" :expanded-ids="expandedIds" :types="site.data.types" :settings="site.draft.settings" :limits="limits" :drag-id="dragId"
              @action="rowAction" @select="selectItem" @toggle="toggle" @navigate="navigate" @drag="dragId = $event" @move="move"
              @default-label="(id, value) => defaultLabels[id] = value" />
          </ul>
          <div v-if="dragId" class="mt-3 rounded-lg border-2 border-dashed p-3 text-center text-xs"
            :class="rootOver ? 'border-theme-500 bg-theme-50 text-theme-700' : 'border-slate-300 text-slate-500'"
            @dragover="rootDragOver" @dragleave="rootOver = false" @drop.prevent="dropRoot">{{ t('site.menus.dropRoot') }}</div>
          <p v-if="selected.items.length >= limits.max_siblings" class="mt-2 text-xs text-slate-500">{{ t('site.menus.limitSiblings', { count: limits.max_siblings }) }}</p>
        </div>

        <aside class="min-w-0 border-t border-slate-200 bg-slate-50/50 p-4 xl:border-l xl:border-t-0">
          <template v-if="selectedItem">
            <h2 class="card-title mb-4 break-words">{{ itemLabel(selectedItem) }}</h2>
            <MenuItemEditor :key="selectedItem.id" ref="inspector" :item="selectedItem" :types="site.data.types" :settings="site.draft.settings" />
          </template>
          <p v-else class="py-8 text-center text-sm text-slate-500">{{ t('site.menus.selectItem') }}</p>
        </aside>
      </div>
      <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">{{ t('site.menus.hint') }}</p>
      <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>
    </section>
    <section v-else class="card p-6 text-sm text-slate-500">{{ t('site.menus.empty') }}</section>

    <ComponentNameModal v-model="createMenuOpen" :maxlength="80" :title="t('site.menus.createMenu')" :label="t('site.menus.label')" :confirm-label="t('site.menus.addMenu')" icon="mdi:menu" @confirm="addMenu" />
    <MenuDestinationDialog v-model="addOpen" adding :parent-label="addParent ? itemLabel(addParent) : ''" :types="site.data.types" :settings="site.draft.settings" @confirm="addItem" />
    <ConfirmModal v-model="deleteMenuOpen" :title="t('site.menus.deleteTitle')"
      :message="t('site.menus.deleteMessage', { name: selected?.label ?? '' })" :confirm-label="t('site.menus.deleteMenu')" @confirm="deleteMenu" />
    <ConfirmModal v-model="deleteItemOpen" :title="t('site.menus.deleteItemTitle')"
      :message="t('site.menus.deleteItemMessage', { name: selectedItem ? itemLabel(selectedItem) : '', count: selectedItem ? itemCount([selectedItem]) : 0 })"
      @confirm="removeItem" />
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import ConfirmModal from '../ConfirmModal.vue'
import ComponentNameModal from './ComponentNameModal.vue'
import MenuDestinationDialog from './MenuDestinationDialog.vue'
import { resolveLocalized } from '../../composables/localizedValues.js'
import MenuItemEditor from './MenuItemEditor.vue'
import MenuTreeNode from './MenuTreeNode.vue'
import { DEFAULT_MENU_LIMITS, createMenuItem, itemCount, findNode, locate, ancestors, menuDrop, menuMoveTarget, moveMenuItem } from '../../composables/menuTree.js'
import { useSiteStore } from '../../stores/site.js'
import { useI18n } from '../../i18n/index.js'
const site = useSiteStore()
const { t } = useI18n()
const menus = computed(() => site.draft.menus)
const limits = computed(() => ({ ...DEFAULT_MENU_LIMITS, ...site.data.menu_limits }))
const selectedName = ref(menus.value[0]?.name ?? '')
const createMenuOpen = ref(false)
const settingsOpen = ref(false)
const addOpen = ref(false)
const addParent = ref(null)
const selectedId = ref('')
const expandedIds = ref(new Set())
const deleteMenuOpen = ref(false)
const deleteItemOpen = ref(false)
const dragId = ref('')
const rootOver = ref(false)
const tree = ref(null)
const inspector = ref(null)
const announcement = ref('')
const defaultLabels = ref({})
const selected = computed(() => menus.value.find((menu) => menu.name === selectedName.value) ?? null)
const selectedItem = computed(() => findNode(selected.value?.items ?? [], selectedId.value))
watch(() => menus.value.map((menu) => menu.name), (names) => {
  if (!names.includes(selectedName.value)) selectedName.value = names[0] ?? ''
})
watch(() => `${site.data.workspace}:${selected.value?.name}`, () => {
  defaultLabels.value = {}
  const ids = new Set()
  const visit = (items) => items.forEach((item) => { if (item.children.length) ids.add(item.id); visit(item.children) })
  visit(selected.value?.items ?? [])
  expandedIds.value = ids
  selectedId.value = selected.value?.items[0]?.id ?? ''
  dragId.value = ''
  settingsOpen.value = false
  addOpen.value = false
}, { immediate: true })
watch(dragId, () => { rootOver.value = false })
function itemLabel(item) { return resolveLocalized(item.label, site.draft.settings.default_locale, site.draft.settings.default_locale) || defaultLabels.value[item.id] || t('site.menus.untitled') }
function chooseMenu(element) {
  if (element.value === '__create__') { createMenuOpen.value = true; element.value = `menu:${selectedName.value}` }
  else selectedName.value = element.value.slice(5)
}
async function selectItem(id, edit = false) {
  selectedId.value = id
  await nextTick()
  if (edit && window.matchMedia('(max-width: 1279px)').matches) inspector.value?.focusLabel()
}
function startAdd(parent = null) { addParent.value = parent; addOpen.value = true }
function rowAction(id, action) {
  selectedId.value = id
  if (action === 'add') startAdd(selectedItem.value)
  else if (action === 'delete') requestRemove()
  else shift(action)
}
function toggle(id) {
  if (expandedIds.value.has(id)) {
    expandedIds.value.delete(id)
    if (ancestors(selected.value.items, selectedId.value).some((item) => item.id === id)) selectedId.value = id
  } else expandedIds.value.add(id)
}
async function focusRow(id) {
  selectedId.value = id
  for (const parent of ancestors(selected.value.items, id)) expandedIds.value.add(parent.id)
  await nextTick()
  const element = [...(tree.value?.querySelectorAll('[data-menu-row]') ?? [])].find((row) => row.dataset.menuRow === id)
  element?.focus()
  element?.scrollIntoView?.({ block: 'nearest' })
}
function navigate(id, key) {
  const visible = []
  const visit = (items) => items.forEach((item) => { visible.push(item); if (expandedIds.value.has(item.id)) visit(item.children) })
  visit(selected.value.items)
  const index = visible.findIndex((item) => item.id === id)
  const item = visible[index]
  let next = id
  if (key === 'ArrowUp') next = visible[Math.max(0, index - 1)]?.id
  if (key === 'ArrowDown') next = visible[Math.min(visible.length - 1, index + 1)]?.id
  if (key === 'Home') next = visible[0]?.id
  if (key === 'End') next = visible.at(-1)?.id
  if (key === 'ArrowRight' && item.children.length) {
    if (!expandedIds.value.has(id)) expandedIds.value.add(id)
    else next = item.children[0].id
  }
  if (key === 'ArrowLeft') {
    if (expandedIds.value.has(id) && item.children.length) expandedIds.value.delete(id)
    else next = locate(selected.value.items, id)?.parent?.id ?? id
  }
  if (next) focusRow(next)
}
async function addItem({ link, label }) {
  const parent = addParent.value
  if (!selected.value) return
  const items = parent ? parent.children : selected.value.items
  if (items.length >= limits.value.max_siblings || (parent && ancestors(selected.value.items, parent.id).length + 2 > limits.value.max_depth)) return
  const item = { ...createMenuItem(), link, label }
  items.push(item)
  if (parent) expandedIds.value.add(parent.id)
  selectedId.value = item.id
  await nextTick()
  inspector.value?.focusLabel()
}
function shift(action) {
  const target = menuMoveTarget(selected.value.items, selectedId.value, action)
  if (target) move(selectedId.value, ...target)
}
function move(id, target, position) {
  if (moveMenuItem(selected.value.items, id, target, position, limits.value)) {
    if (position === 'inside' && target) expandedIds.value.add(target)
    focusRow(id)
    announcement.value = t('site.menus.moved', { name: itemLabel(findNode(selected.value.items, id)) })
  }
}
function rootDragOver(event) {
  rootOver.value = !!menuDrop(selected.value.items, dragId.value, null, 'inside', limits.value)
  if (rootOver.value) event.preventDefault()
}
function dropRoot() {
  if (rootOver.value) move(dragId.value, null, 'inside')
  rootOver.value = false
  dragId.value = ''
}
function requestRemove() {
  if (selectedItem.value.children.length) deleteItemOpen.value = true
  else removeItem()
}
function removeItem() {
  const found = locate(selected.value.items, selectedId.value)
  if (!found) return
  const next = found.list[found.index + 1]?.id ?? found.list[found.index - 1]?.id ?? found.parent?.id ?? ''
  found.list.splice(found.index, 1)
  selectedId.value = next
  deleteItemOpen.value = false
  if (next) focusRow(next)
}
function slugify(value) { return value.toLowerCase().trim().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70) || 'menu' }
function addMenu(value) {
  const label = value.trim()
  if (!label || menus.value.length >= limits.value.max_menus) return
  const base = slugify(label)
  let name = base
  let suffix = 2
  while (menus.value.some((menu) => menu.name === name)) name = `${base}-${suffix++}`
  menus.value.push({ name, label, items: [] })
  selectedName.value = name
  createMenuOpen.value = false
}
function deleteMenu() {
  const index = menus.value.findIndex((menu) => menu.name === selectedName.value)
  if (index >= 0) menus.value.splice(index, 1)
  deleteMenuOpen.value = false
}
</script>
