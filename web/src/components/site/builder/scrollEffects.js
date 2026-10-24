/** Scroll controls follow the effective device value, independently of entrance animations. */
export function scrollStyleVisible(node, key, valueFor) {
  const section = node?.kind === 'section';
  if (!section && node?.kind !== 'image') return false;
  if (section && (!valueFor('background_image') || valueFor('background_gradient')?.type)) return false;
  if (key === 'scroll_background') return section;
  if (key === 'scroll_element') return !section;
  if (key === 'scroll_direction' && section) return false;
  return valueFor(section ? 'scroll_background' : 'scroll_element') === 'parallax';
}
