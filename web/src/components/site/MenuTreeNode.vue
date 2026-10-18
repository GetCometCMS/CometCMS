<template>
  <li :data-menu-row="item.id" role="treeitem" :aria-level="depth" :aria-selected="selectedId === item.id"
      :aria-label="item.label || t('site.menus.untitled')"
      :aria-expanded="item.children.length ? expanded : undefined" :tabindex="selectedId === item.id ? 0 : -1"
      class="rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
      @focus.self="$emit('select', item.id)" @keydown="onKey">
    <div
      class="group relative flex min-w-0 cursor-pointer items-center gap-1 rounded-lg border px-2 py-2 outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
      :class="selectedId === item.id ? 'border-theme-200 bg-theme-50' : 'border-transparent hover:bg-slate-50'"
      :style="{ marginLeft: `${Math.min(depth - 1, 4) * 14}px` }"
      @click="$emit('select', item.id)"
      @dragover="dragOver" @dragleave="dropPosition = ''" @drop.prevent.stop="drop">
      <span v-if="dropPosition === 'before' || dropPosition === 'after'" class="pointer-events-none absolute inset-x-0 h-0.5 bg-theme-500" :class="dropPosition === 'before' ? '-top-px' : '-bottom-px'"></span>
      <span v-if="dropPosition === 'inside'" class="pointer-events-none absolute inset-0 rounded-lg ring-2 ring-theme-500"></span>
      <button type="button" draggable="true" class="shrink-0 cursor-grab rounded p-1 text-slate-400 hover:bg-slate-100 active:cursor-grabbing"
        :aria-label="t('site.menus.dragItem', { name: item.label || t('site.menus.untitled') })"
        @click.stop @dragstart.stop="startDrag" @dragend="$emit('drag', '')">
        <Icon icon="mdi:drag-vertical" class="h-4 w-4" />
      </button>
      <button v-if="item.children.length" type="button" class="shrink-0 cursor-pointer rounded p-1 text-slate-500 hover:bg-slate-100"
        :aria-expanded="expanded" :aria-label="t(expanded ? 'site.builder.collapse' : 'site.builder.expand')" @click.stop="$emit('toggle', item.id)">
        <Icon icon="mdi:chevron-right" class="h-4 w-4 transition-transform" :class="{ 'rotate-90': expanded }" />
      </button>
      <span v-else class="w-6 shrink-0"></span>
      <Icon :icon="item.link.type === 'none' ? 'mdi:folder-outline' : 'mdi:link'" class="h-4 w-4 shrink-0 text-slate-400" />
      <div class="min-w-0 flex-1 px-1">
        <div class="flex items-center gap-2">
          <span class="truncate text-sm font-medium text-slate-800">{{ item.label || t('site.menus.untitled') }}</span>
          <span v-if="item.children.length" class="shrink-0 rounded bg-slate-100 px-1.5 text-xs text-slate-500" :title="t('site.menus.descendants')">{{ itemCount(item.children) }}</span>
          <Icon v-if="linkIssue(item.link, item.label)" icon="mdi:alert-circle-outline" class="h-4 w-4 shrink-0 text-amber-600" :title="t(`site.link.${linkIssue(item.link, item.label)}`)" />
        </div>
        <MenuLinkSummary :link="item.link" :types="types" :settings="settings" :context="item.id" />
      </div>
    </div>
    <ul v-if="item.children.length && expanded" role="group" class="space-y-1 pt-1">
      <MenuTreeNode v-for="child in item.children" :key="child.id" :item="child" :depth="depth + 1"
        :selected-id="selectedId" :expanded-ids="expandedIds" :types="types" :settings="settings" :items="items" :limits="limits" :drag-id="dragId"
        @select="$emit('select', $event)" @toggle="$emit('toggle', $event)" @navigate="(id, key) => $emit('navigate', id, key)"
        @drag="$emit('drag', $event)" @move="(id, target, position) => $emit('move', id, target, position)" />
    </ul>
  </li>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import MenuLinkSummary from './MenuLinkSummary.vue'
import { itemCount, menuDrop } from '../../composables/menuTree.js'
import { linkIssue } from '../../composables/siteLinks.js'
import { useI18n } from '../../i18n/index.js'
const props = defineProps({
  item: { type: Object, required: true }, items: { type: Array, required: true }, depth: { type: Number, default: 1 },
  selectedId: { type: String, default: '' }, expandedIds: { type: Set, required: true },
  types: { type: Array, required: true }, settings: { type: Object, required: true }, limits: { type: Object, required: true }, dragId: { type: String, default: '' },
})
const emit = defineEmits(['select', 'toggle', 'navigate', 'drag', 'move'])
const { t } = useI18n()
const expanded = computed(() => props.expandedIds.has(props.item.id))
const dropPosition = ref('')
watch(() => props.dragId, () => { dropPosition.value = '' })
function startDrag(event) {
  emit('drag', props.item.id)
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', props.item.id)
}
function dragOver(event) {
  if (!props.dragId) return
  const rect = event.currentTarget.getBoundingClientRect()
  const ratio = (event.clientY - rect.top) / rect.height
  const position = ratio < 0.25 ? 'before' : ratio > 0.75 ? 'after' : 'inside'
  const valid = menuDrop(props.items, props.dragId, props.item.id, position, props.limits)
  dropPosition.value = valid ? position : ''
  event.dataTransfer.dropEffect = valid ? 'move' : 'none'
  if (valid) event.preventDefault()
}
function drop() {
  if (dropPosition.value) emit('move', props.dragId, props.item.id, dropPosition.value)
  dropPosition.value = ''
  emit('drag', '')
}
function onKey(event) {
  if (event.target !== event.currentTarget || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  emit('navigate', props.item.id, event.key)
}
</script>
