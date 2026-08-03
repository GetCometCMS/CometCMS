import { nextTick, onBeforeUnmount, watch } from "vue";

const FOCUSABLE_SELECTOR = [
  "a[href]",
  "button:not([disabled])",
  "input:not([disabled]):not([type='hidden'])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  "[tabindex]:not([tabindex='-1'])",
].join(",");
const dialogStack = [];

/**
 * Keeps keyboard focus inside a modal surface and restores it to the trigger.
 * The caller controls whether Escape may close the surface (for example while
 * a destructive operation is in progress).
 */
export function useDialogFocus({ open, container, close, closeBlocked = () => false }) {
  let previouslyFocused = null;

  function focusableElements() {
    return [...(container.value?.querySelectorAll(FOCUSABLE_SELECTOR) ?? [])]
      .filter((element) => !element.hidden && element.getClientRects().length > 0);
  }

  function handleKeydown(event) {
    if (!open.value || dialogStack.at(-1) !== handleKeydown) return;

    if (event.key === "Escape" && !closeBlocked()) {
      event.preventDefault();
      close();
      return;
    }

    if (event.key !== "Tab") return;
    const focusable = focusableElements();
    if (focusable.length === 0) {
      event.preventDefault();
      container.value?.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function stopListening({ restore = true } = {}) {
    document.removeEventListener("keydown", handleKeydown);
    const stackIndex = dialogStack.lastIndexOf(handleKeydown);
    if (stackIndex !== -1) dialogStack.splice(stackIndex, 1);
    if (restore && previouslyFocused?.isConnected) previouslyFocused.focus();
    previouslyFocused = null;
  }

  watch(open, async (isOpen) => {
    if (!isOpen) {
      stopListening();
      return;
    }

    previouslyFocused = document.activeElement;
    const existingIndex = dialogStack.lastIndexOf(handleKeydown);
    if (existingIndex !== -1) dialogStack.splice(existingIndex, 1);
    dialogStack.push(handleKeydown);
    document.addEventListener("keydown", handleKeydown);
    await nextTick();
    const preferred = container.value?.querySelector("[data-dialog-initial-focus]");
    (preferred ?? focusableElements()[0] ?? container.value)?.focus();
  }, { immediate: true });

  onBeforeUnmount(() => stopListening());
}
