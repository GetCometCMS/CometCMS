/** Preset geometry matches the public stylesheet; custom effects use theme colors. */
export function shadowSettings(value, text = false) {
  const base = { color: 'primary', x: text ? 3 : 6, y: text ? 3 : 6, blur: 0, opacity: 100, ...(!text ? { spread: 0, inset: false } : {}) };
  if (value && typeof value === 'object') return { ...base, ...value };
  const soft = text
    ? { sm: [0, 1, 2, 0, 25], md: [0, 2, 4, 0, 35], lg: [0, 4, 8, 0, 45], inset: [0, 1, 2, 0, 35] }
    : { sm: [0, 1, 3, 0, 8], md: [0, 6, 18, -6, 15], lg: [0, 20, 45, -15, 25], inset: [0, 2, 8, 0, 25] };
  if (soft[value]) {
    const [x, y, blur, spread, opacity] = soft[value];
    return { ...base, color: 'text', x, y, blur, opacity, ...(!text ? { spread, inset: value === 'inset' } : {}) };
  }
  if (value === 'glow') return { ...base, x: 0, y: 0, blur: text ? 16 : 24, opacity: text ? 100 : 55, ...(!text ? { spread: 4 } : {}) };
  return base;
}

export function shadowPreview(value, text, options) {
  const shadow = shadowSettings(value, text);
  const color = options.find(option => option.value === shadow.color)?.swatch ?? '#6366f1';
  const tint = `color-mix(in srgb, ${color} ${shadow.opacity}%, transparent)`;
  let css = `${!text && shadow.inset ? 'inset ' : ''}${shadow.x}px ${shadow.y}px ${shadow.blur}px ${!text ? `${shadow.spread}px ` : ''}${tint}`;
  // Legacy soft presets are black in published layouts.
  if (typeof value === 'string' && ['sm', 'md', 'lg', 'inset'].includes(value)) {
    css = css.replace(tint, `rgb(0 0 0 / ${shadow.opacity / 100})`);
    if (!text && value === 'sm') css += ', 0 1px 2px rgb(0 0 0 / 0.04)';
  }
  return { [text ? 'textShadow' : 'boxShadow']: css };
}
