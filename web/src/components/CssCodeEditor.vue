<template>
  <div class="css-code-editor relative min-h-0 overflow-hidden rounded-lg border border-slate-300 bg-white" :class="{ 'is-disabled': disabled }">
    <div ref="hostRef" class="h-full"></div>
    <div v-if="!ready" class="absolute inset-0 flex items-center justify-center"><LoadingSpinner /></div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import LoadingSpinner from "./LoadingSpinner.vue";

/**
 * CSS source editor with syntax highlighting, completion and bracket matching.
 * CodeMirror is only loaded once an editor is shown.
 */
const props = defineProps({
  modelValue: { type: String, default: "" },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: "" },
  label: { type: String, default: "" },
});
const emit = defineEmits(["update:modelValue"]);

const hostRef = ref(null);
const ready = ref(false);
let view = null;
let editable = null;
let editableExtension = null;

onMounted(async () => {
  const [{ basicSetup }, { css }, { Compartment, EditorState }, { EditorView, keymap, placeholder }, { indentWithTab }] = await Promise.all([
    import("codemirror"),
    import("@codemirror/lang-css"),
    import("@codemirror/state"),
    import("@codemirror/view"),
    import("@codemirror/commands"),
  ]);
  if (!hostRef.value) return;

  editable = new Compartment();
  editableExtension = (disabled) => [EditorView.editable.of(!disabled), EditorState.readOnly.of(disabled)];
  view = new EditorView({
    parent: hostRef.value,
    state: EditorState.create({
      doc: props.modelValue ?? "",
      extensions: [
        basicSetup,
        css(),
        keymap.of([indentWithTab]),
        placeholder(props.placeholder),
        editable.of(editableExtension(props.disabled)),
        EditorView.lineWrapping,
        EditorView.contentAttributes.of({ "aria-label": props.label, spellcheck: "false" }),
        EditorView.updateListener.of((update) => {
          if (update.docChanged) emit("update:modelValue", update.state.doc.toString());
        }),
      ],
    }),
  });
  ready.value = true;
});

// Outside changes, such as the builder's undo, replace the editor's text.
watch(() => props.modelValue, (value) => {
  const next = value ?? "";
  if (!view || view.state.doc.toString() === next) return;
  view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: next } });
});

watch(() => props.disabled, (disabled) => {
  view?.dispatch({ effects: editable.reconfigure(editableExtension(disabled)) });
});

onBeforeUnmount(() => {
  view?.destroy();
  view = null;
});
</script>

<style scoped>
.css-code-editor:focus-within {
  border-color: rgb(var(--color-theme-500, 99 102 241));
  box-shadow: 0 0 0 1px rgb(var(--color-theme-500, 99 102 241));
}
.css-code-editor.is-disabled {
  background: rgb(var(--color-slate-50));
}
.css-code-editor :deep(.cm-editor) {
  height: 100%;
  font-size: 12px;
}
.css-code-editor :deep(.cm-editor.cm-focused) {
  outline: none;
}
.css-code-editor :deep(.cm-scroller) {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  line-height: 1.6;
}
.css-code-editor :deep(.cm-gutters) {
  border-right: 1px solid rgb(var(--color-slate-200));
  background: rgb(var(--color-slate-50));
  color: rgb(var(--color-slate-400));
}
.css-code-editor :deep(.cm-activeLine),
.css-code-editor :deep(.cm-activeLineGutter) {
  background: rgb(var(--color-slate-100) / 0.7);
}
.css-code-editor :deep(.cm-placeholder) {
  color: rgb(var(--color-slate-400));
}
.css-code-editor :deep(.cm-cursor) {
  border-left-color: currentColor;
}
</style>
