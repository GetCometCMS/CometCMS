import { ref, watch } from 'vue'

/** Route changes reveal the current section; browsing a submenu never navigates. */
export function useNavigationSections(groups, path, isActive) {
  const expanded = ref({})

  watch([groups, path], () => {
    const sections = groups.value.flatMap((group) => group.items).filter((item) => item.children)
    const next = {}
    for (const item of sections) {
      next[item.key] = isActive(item, path.value) || !!expanded.value[item.key]
    }
    expanded.value = next
  }, { immediate: true })

  function isExpanded(item) {
    return !!expanded.value[item.key]
  }

  function toggle(item) {
    expanded.value = { ...expanded.value, [item.key]: !isExpanded(item) }
  }

  function reveal(item) {
    expanded.value = { ...expanded.value, [item.key]: true }
  }

  return { isExpanded, toggle, reveal }
}
