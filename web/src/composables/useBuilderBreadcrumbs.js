import { computed } from 'vue'
import { ancestors } from './layoutTree.js'

/** Keep the selected branch available while navigating between its breadcrumbs. */
export function useBuilderBreadcrumbs(builder) {
  const branch = computed((previous = []) => {
    const selected = builder.selected.value
    if (!selected) return []
    const path = [...ancestors(builder.nodes.value, selected.id), selected]

    // Resolve remembered IDs against the live tree, stopping at removed or moved nodes.
    const retained = []
    let siblings = builder.nodes.value
    for (const item of previous) {
      const current = siblings.find((node) => node.id === item.id)
      if (!current) break
      retained.push(current)
      siblings = current.children ?? []
    }

    return path.every((node, index) => node.id === retained[index]?.id) ? retained : path
  })

  return computed(() => {
    // A collection's card is the local root of its repeated content.
    const selectedIndex = branch.value.findIndex((node) => node.id === builder.selected.value?.id)
    const cardIndex = branch.value.findLastIndex((node, index) => index <= selectedIndex && builder.isCard(node))
    return cardIndex >= 0 ? branch.value.slice(cardIndex) : branch.value
  })
}
