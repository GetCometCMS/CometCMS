/** Icons for layout node kinds in the builder palette and outline. */
export const KIND_ICONS = {
  section: 'mdi:view-agenda-outline',
  row: 'mdi:view-column-outline',
  column: 'mdi:view-split-vertical',
  field: 'mdi:form-textbox',
  heading: 'mdi:format-header-pound',
  text: 'mdi:text',
  image: 'mdi:image-outline',
  gallery: 'mdi:image-multiple-outline',
  button: 'mdi:gesture-tap-button',
  accordion: 'mdi:chevron-down',
  icon: 'mdi:star-outline',
  spacer: 'mdi:arrow-expand-vertical',
  divider: 'mdi:minus',
  collection: 'mdi:view-grid-outline',
  form: 'mdi:form-select',
  menu: 'mdi:menu',
  site_title: 'mdi:home-variant-outline',
  language_switcher: 'mdi:translate',
  component: 'mdi:puzzle-outline',
}

/** Column sets arranged underneath each other read as rows. */
export const STACKED_ICONS = {
  row: 'mdi:view-agenda-outline',
  column: 'mdi:view-split-horizontal',
}

export function nodeIcon(node, stacked = false) {
  return (stacked && STACKED_ICONS[node?.kind]) || KIND_ICONS[node?.kind] || 'mdi:shape-outline'
}
