import { addIcon } from '@iconify/vue'
import icons from '../../../cms/app/Site/assets/icons.json'

// The picker and public PHP renderer use the same local SVG data.
for (const [name, data] of Object.entries(icons)) addIcon(name, data)

export const SITE_ICONS = Object.keys(icons)
