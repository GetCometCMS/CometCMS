<template>
  <div class="space-y-1">
    <div class="flex flex-wrap items-center gap-0.5 rounded-lg border border-slate-200 bg-slate-50 p-1" role="toolbar" :aria-label="t('editor.toolbar')">
      <template v-for="(group, index) in groups" :key="index">
        <button
          v-for="item in group"
          :key="item.name"
          type="button"
          class="rich-text-btn focus-visible:ring-2 focus-visible:ring-theme-500"
          :class="{ 'is-active': item.active?.() }"
          :title="item.shortcut ? `${t(`editor.${item.name}`)} (${item.shortcut})` : t(`editor.${item.name}`)"
          :aria-label="t(`editor.${item.name}`)"
          :aria-pressed="item.active ? !!item.active() : undefined"
          :aria-expanded="item.name === 'link' ? linkOpen : undefined"
          :disabled="!editor || (item.enabled ? !item.enabled() : false)"
          @mousedown.prevent
          @click="item.run()"
        >
          <Icon :icon="item.icon" class="h-4 w-4" />
        </button>
        <span v-if="index < groups.length - 1" class="mx-0.5 h-5 w-px bg-slate-200" aria-hidden="true"></span>
      </template>
    </div>
    <div v-if="linkOpen" class="space-y-1.5 rounded-lg border border-slate-200 bg-white p-2" @keydown.enter.stop @keydown.esc.prevent.stop="closeLink">
      <label class="sr-only" :for="linkId">{{ t('editor.linkUrl') }}</label>
      <input :id="linkId" ref="linkInput" v-model="linkHref" @keydown.enter.prevent="applyLink" type="text" inputmode="url" class="form-input w-full rounded-md border-slate-300 py-1 text-xs" :placeholder="t('editor.linkPlaceholder')" :aria-invalid="linkInvalid" />
      <p v-if="linkInvalid" role="alert" class="text-xs text-red-600">{{ t('editor.linkInvalid') }}</p>
      <div class="flex items-center gap-2">
        <label class="mr-auto flex items-center gap-1.5 text-xs text-slate-600"><input v-model="linkNewTab" type="checkbox" class="rounded border-slate-300 text-theme-600" />{{ t('editor.linkNewTab') }}</label>
        <button v-if="editor?.isActive('link')" type="button" class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeLink">{{ t('editor.linkRemove') }}</button>
        <button type="button" @click="applyLink" class="rounded bg-theme-600 px-2 py-1 text-xs font-medium text-white hover:bg-theme-700">{{ t('editor.linkApply') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, useId } from 'vue'
import { Icon } from '@iconify/vue'
import { useI18n } from '../i18n/index.js'
import { normalizeLinkHref } from '../composables/richTextEditor.js'

const props = defineProps({ editor: { type: Object, default: null } })
const { t } = useI18n()
const linkId = `rich-text-link-${useId()}`
const linkOpen = ref(false)
const linkHref = ref('')
const linkNewTab = ref(false)
const linkInvalid = ref(false)
const linkInput = ref(null)

const chain = () => props.editor.chain().focus()
const active = (name, attrs) => () => !!props.editor?.isActive(name, attrs)
const mod = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl'

const groups = computed(() => [
  [
    { name: 'bold', icon: 'mdi:format-bold', shortcut: `${mod}+B`, active: active('bold'), run: () => chain().toggleBold().run() },
    { name: 'italic', icon: 'mdi:format-italic', shortcut: `${mod}+I`, active: active('italic'), run: () => chain().toggleItalic().run() },
    { name: 'underline', icon: 'mdi:format-underline', shortcut: `${mod}+U`, active: active('underline'), run: () => chain().toggleUnderline().run() },
    { name: 'strike', icon: 'mdi:format-strikethrough-variant', active: active('strike'), run: () => chain().toggleStrike().run() },
    { name: 'highlight', icon: 'mdi:format-color-highlight', active: active('highlight'), run: () => chain().toggleHighlight().run() },
    { name: 'link', icon: 'mdi:link-variant', shortcut: `${mod}+K`, active: active('link'), run: toggleLink },
  ],
  [1, 2, 3].map((level) => ({ name: `h${level}`, icon: `mdi:format-header-${level}`, active: active('heading', { level }), run: () => chain().toggleHeading({ level }).run() })),
  [
    { name: 'code', icon: 'mdi:code-tags', active: active('code'), run: () => chain().toggleCode().run() },
    { name: 'superscript', icon: 'mdi:format-superscript', active: active('superscript'), run: () => chain().unsetSubscript().toggleSuperscript().run() },
    { name: 'subscript', icon: 'mdi:format-subscript', active: active('subscript'), run: () => chain().unsetSuperscript().toggleSubscript().run() },
  ],
  [
    { name: 'bulletList', icon: 'mdi:format-list-bulleted', active: active('bulletList'), run: () => chain().toggleBulletList().run() },
    { name: 'orderedList', icon: 'mdi:format-list-numbered', active: active('orderedList'), run: () => chain().toggleOrderedList().run() },
    { name: 'indent', icon: 'mdi:format-indent-increase', enabled: () => !!props.editor?.can().sinkListItem('listItem'), run: () => chain().sinkListItem('listItem').run() },
    { name: 'outdent', icon: 'mdi:format-indent-decrease', enabled: () => !!props.editor?.can().liftListItem('listItem'), run: () => chain().liftListItem('listItem').run() },
    { name: 'blockquote', icon: 'mdi:format-quote-close', active: active('blockquote'), run: () => chain().toggleBlockquote().run() },
    { name: 'codeBlock', icon: 'mdi:code-braces-box', active: active('codeBlock'), run: () => chain().toggleCodeBlock().run() },
    { name: 'rule', icon: 'mdi:minus', run: () => chain().setHorizontalRule().run() },
  ],
  [
    { name: 'clearFormatting', icon: 'mdi:format-clear', run: () => chain().unsetAllMarks().clearNodes().run() },
    { name: 'undo', icon: 'mdi:undo', shortcut: `${mod}+Z`, enabled: () => !!props.editor?.can().undo(), run: () => chain().undo().run() },
    { name: 'redo', icon: 'mdi:redo', shortcut: `${mod}+Shift+Z`, enabled: () => !!props.editor?.can().redo(), run: () => chain().redo().run() },
  ],
])

function toggleLink() {
  if (linkOpen.value) return closeLink()
  const attrs = props.editor.getAttributes('link')
  linkHref.value = attrs.href ?? ''
  linkNewTab.value = attrs.target === '_blank'
  linkInvalid.value = false
  linkOpen.value = true
  nextTick(() => linkInput.value?.focus())
}

function closeLink() {
  linkOpen.value = false
  props.editor?.commands.focus()
}

function applyLink() {
  if (!linkHref.value.trim()) return removeLink()
  const href = normalizeLinkHref(linkHref.value)
  if (!href) { linkInvalid.value = true; return }
  const attrs = { href, target: linkNewTab.value ? '_blank' : null }
  const { empty } = props.editor.state.selection
  if (empty && !props.editor.isActive('link')) chain().insertContent({ type: 'text', text: linkHref.value.trim(), marks: [{ type: 'link', attrs }] }).run()
  else chain().extendMarkRange('link').setLink(attrs).run()
  linkOpen.value = false
}

function removeLink() {
  chain().extendMarkRange('link').unsetLink().run()
  linkOpen.value = false
}

defineExpose({ openLink: () => { if (!linkOpen.value) toggleLink() } })
</script>

<style scoped>
.rich-text-btn { display: inline-flex; height: 1.75rem; width: 1.75rem; align-items: center; justify-content: center; border-radius: 0.375rem; color: rgb(var(--color-slate-500)); transition: background-color 0.15s, color 0.15s; }
.rich-text-btn:hover:not(:disabled) { background: rgb(var(--color-slate-200)); color: rgb(var(--color-slate-900)); }
.rich-text-btn:disabled { opacity: 0.35; cursor: default; }
.rich-text-btn.is-active { background: rgb(var(--color-surface)); color: rgb(var(--color-slate-900)); box-shadow: 0 0 0 1px rgb(var(--color-slate-300)), 0 1px 2px rgb(0 0 0 / 0.08); }
</style>
