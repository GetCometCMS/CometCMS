<template>
  <div class="grid grid-cols-1 gap-5 lg:grid-cols-[12rem_minmax(0,1fr)]">
    <aside class="card h-fit p-3">
      <ul class="space-y-1">
        <li v-for="menu in menus" :key="menu.name">
          <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors"
            :class="menu.name === selectedName ? 'bg-theme-50 font-medium text-theme-700' : 'text-slate-700 hover:bg-slate-50'" @click="selectedName = menu.name">
            <Icon icon="mdi:menu" class="h-4 w-4 shrink-0 opacity-60" />
            <span class="min-w-0 flex-1 truncate">{{ menu.label }}</span>
            <span class="text-xs text-slate-400">{{ itemCount(menu.items) }}</span>
          </button>
        </li>
      </ul>
      <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" @submit.prevent="addMenu">
        <input v-model="newMenuLabel" type="text" maxlength="80" class="form-input w-full rounded-lg border-slate-300 text-sm"
          :placeholder="t('site.menus.newMenu')" :aria-label="t('site.menus.newMenu')" />
        <button type="submit" class="btn-secondary btn-sm w-full justify-center" :disabled="!newMenuLabel.trim() || menus.length >= limits.max_menus">
          <Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.menus.addMenu') }}
        </button>
        <p v-if="menus.length >= limits.max_menus" class="text-xs text-slate-500">{{ t('site.menus.limitMenus', { count: limits.max_menus }) }}</p>
      </form>
    </aside>

    <section v-if="selected" class="card min-w-0">
      <div class="border-b border-slate-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
          <div class="min-w-0 flex-1">
            <label class="form-label" for="menu-label">{{ t('site.menus.label') }}</label>
            <input id="menu-label" v-model="selected.label" type="text" maxlength="80" class="form-input w-full rounded-lg border-slate-300 text-sm" />
          </div>
          <button type="button" class="btn-danger-subtle btn-sm" @click="deleteMenuOpen = true">
            <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />{{ t('site.menus.deleteMenu') }}
          </button>
        </div>
        <p class="mt-2 text-xs text-slate-500">{{ t('site.menus.name') }} <code class="rounded bg-slate-100 px-1">{{ selected.name }}</code></p>
      </div>

      <div class="grid min-w-0 grid-cols-1 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="card-title">{{ t('site.menus.structure') }}</h2>
            <button type="button" class="btn-secondary btn-sm" :disabled="selected.items.length >= limits.max_siblings" @click="addItem()">
              <Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.menus.addItem') }}
            </button>
          </div>
          <p class="mb-3 text-xs text-slate-500">{{ t('site.menus.treeHint', { count: limits.max_depth }) }}</p>
          <div v-if="!selected.items.length" class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center">
            <Icon icon="mdi:format-list-bulleted" class="mx-auto mb-2 h-8 w-8 text-slate-300" />
            <p class="text-sm text-slate-600">{{ t('site.menus.emptyItems') }}</p>
            <button type="button" class="mt-3 text-sm font-medium text-theme-700" @click="addItem()">{{ t('site.menus.addFirst') }}</button>
          </div>
          <ul v-else ref="tree" role="tree" :aria-label="t('site.menus.structure')" class="space-y-1">
            <MenuTreeNode v-for="item in selected.items" :key="item.id" :item="item" :items="selected.items"
              :selected-id="selectedId" :expanded-ids="expandedIds" :types="site.data.types" :settings="site.draft.settings" :limits="limits" :drag-id="dragId"
              @select="selectItem" @toggle="toggle" @navigate="navigate" @drag="dragId = $event" @move="move" />
          </ul>
          <div v-if="dragId" class="mt-3 rounded-lg border-2 border-dashed p-3 text-center text-xs"
            :class="rootOver ? 'border-theme-500 bg-theme-50 text-theme-700' : 'border-slate-300 text-slate-500'"
            @dragover="rootDragOver" @dragleave="rootOver = false" @drop.prevent="dropRoot">{{ t('site.menus.dropRoot') }}</div>
          <p v-if="selected.items.length >= limits.max_siblings" class="mt-2 text-xs text-slate-500">{{ t('site.menus.limitSiblings', { count: limits.max_siblings }) }}</p>
        </div>

        <aside class="min-w-0 border-t border-slate-200 bg-slate-50/50 p-4 xl:border-l xl:border-t-0">
          <template v-if="selectedItem">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="card-title">{{ t('site.menus.editItem') }}</h2>
              <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="t('common.remove')" @click="requestRemove">
                <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
              </button>
            </div>
            <div class="mb-4 flex flex-wrap items-center gap-1 border-b border-slate-200 pb-3">
              <button v-for="action in moveActions" :key="action.value" type="button"
                class="rounded border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-100 disabled:opacity-30"
                :disabled="!canMove(action.value)" :title="action.label" :aria-label="action.label" @click="shift(action.value)">
                <Icon :icon="action.icon" class="h-4 w-4" />
              </button>
              <span class="ml-auto text-xs text-slate-400">{{ t('site.menus.level', { level: selectedDepth, max: limits.max_depth }) }}</span>
            </div>
            <MenuItemEditor :key="selectedItem.id" ref="inspector" :item="selectedItem" :types="site.data.types" :settings="site.draft.settings" :locales="extraLocales" />
            <button type="button" class="btn-secondary btn-sm mt-5 w-full justify-center" :disabled="!canAddChild" @click="addItem(selectedItem)">
              <Icon icon="mdi:subdirectory-arrow-right" class="h-4 w-4" />{{ t('site.menus.addChild') }}
            </button>
          </template>
          <p v-else class="py-8 text-center text-sm text-slate-500">{{ t('site.menus.selectItem') }}</p>
        </aside>
      </div>
      <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">{{ t('site.menus.hint') }}</p>
      <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>
    </section>
    <section v-else class="card p-6 text-sm text-slate-500">{{ t('site.menus.empty') }}</section>

    <ConfirmModal v-model="deleteMenuOpen" :title="t('site.menus.deleteTitle')"
      :message="t('site.menus.deleteMessage', { name: selected?.label ?? '' })" :confirm-label="t('site.menus.deleteMenu')" @confirm="deleteMenu" />
    <ConfirmModal v-model="deleteItemOpen" :title="t('site.menus.deleteItemTitle')"
      :message="t('site.menus.deleteItemMessage', { name: selectedItem?.label || t('site.menus.untitled'), count: selectedItem ? itemCount([selectedItem]) : 0 })"
      @confirm="removeItem" />
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import ConfirmModal from '../ConfirmModal.vue'
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
const newMenuLabel = ref('')
const selectedId = ref('')
const expandedIds = ref(new Set())
const deleteMenuOpen = ref(false)
const deleteItemOpen = ref(false)
const dragId = ref('')
const rootOver = ref(false)
const tree = ref(null)
const inspector = ref(null)
const announcement = ref('')
const selected = computed(() => menus.value.find((menu) => menu.name === selectedName.value) ?? null)
const selectedItem = computed(() => findNode(selected.value?.items ?? [], selectedId.value))
const selectedDepth = computed(() => ancestors(selected.value?.items ?? [], selectedId.value).length + 1)
const canAddChild = computed(() => selectedItem.value && selectedDepth.value < limits.value.max_depth && selectedItem.value.children.length < limits.value.max_siblings)
const extraLocales = computed(() => site.draft.settings.locales.filter((locale) => locale !== site.draft.settings.default_locale))
const moveActions = computed(() => [
  { value: 'up', icon: 'mdi:arrow-up', label: t('site.builder.moveUp') },
  { value: 'down', icon: 'mdi:arrow-down', label: t('site.builder.moveDown') },
  { value: 'indent', icon: 'mdi:format-indent-increase', label: t('site.menus.indent') },
  { value: 'outdent', icon: 'mdi:format-indent-decrease', label: t('site.menus.outdent') },
])
watch(() => menus.value.map((menu) => menu.name), (names) => {
  if (!names.includes(selectedName.value)) selectedName.value = names[0] ?? ''
})
watch(() => `${site.data.workspace}:${selected.value?.name}`, () => {
  const ids = new Set()
  const visit = (items) => items.forEach((item) => { if (item.children.length) ids.add(item.id); visit(item.children) })
  visit(selected.value?.items ?? [])
  expandedIds.value = ids
  selectedId.value = selected.value?.items[0]?.id ?? ''
  dragId.value = ''
}, { immediate: true })
watch(dragId, () => { rootOver.value = false })
function selectItem(id) { selectedId.value = id }
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
async function addItem(parent = null) {
  if (!selected.value) return
  const items = parent ? parent.children : selected.value.items
  if (items.length >= limits.value.max_siblings || (parent && ancestors(selected.value.items, parent.id).length + 2 > limits.value.max_depth)) return
  const item = createMenuItem()
  items.push(item)
  if (parent) expandedIds.value.add(parent.id)
  selectedId.value = item.id
  await nextTick()
  inspector.value?.focusLabel()
}
function canMove(action) {
  if (!selected.value || !selectedItem.value) return false
  const target = menuMoveTarget(selected.value.items, selectedId.value, action)
  return !!target && !!menuDrop(selected.value.items, selectedId.value, ...target, limits.value)
}
function shift(action) {
  const target = menuMoveTarget(selected.value.items, selectedId.value, action)
  if (target) move(selectedId.value, ...target)
}
function move(id, target, position) {
  if (moveMenuItem(selected.value.items, id, target, position, limits.value)) {
    if (position === 'inside' && target) expandedIds.value.add(target)
    focusRow(id)
    announcement.value = t('site.menus.moved', { name: findNode(selected.value.items, id)?.label || t('site.menus.untitled') })
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
function addMenu() {
  const label = newMenuLabel.value.trim()
  if (!label || menus.value.length >= limits.value.max_menus) return
  const base = slugify(label)
  let name = base
  let suffix = 2
  while (menus.value.some((menu) => menu.name === name)) name = `${base}-${suffix++}`
  menus.value.push({ name, label, items: [] })
  selectedName.value = name
  newMenuLabel.value = ''
}
function deleteMenu() {
  const index = menus.value.findIndex((menu) => menu.name === selectedName.value)
  if (index >= 0) menus.value.splice(index, 1)
  deleteMenuOpen.value = false
}
</script>
