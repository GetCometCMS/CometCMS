/** Built-in editable box shadow sizes; theme presets follow them. */
export const FIXED_SHADOWS = ['sm', 'md', 'lg'];
export const TEXT_SHADOWS = ['sm', 'md', 'lg', 'hard', 'glow'];
export const MAX_SHADOWS = 32;
const BLACK = { value: 'black', swatch: '#000000' };

/** Shadow colors: plain black, then the theme palette. */
export function shadowColorOptions(options, label) {
  return [{ ...BLACK, label, custom: false }, ...options];
}

/** A preset's own name, or the shipped preset's translated one. */
export function shadowLabel(key, preset, t) {
  return preset?.label || (['hard', 'glow', 'inset'].includes(key) ? t(`site.shadow.${key}`) : key);
}

/** Preset geometry matches the public stylesheet; custom effects use theme colors. */
export function shadowSettings(value, text = false, presets = {}, scale = {}) {
  const base = { color: 'primary', x: text ? 3 : 6, y: text ? 3 : 6, blur: 0, opacity: 100, ...(!text ? { spread: 0, inset: false } : {}) };
  const trim = (shadow) => {
    const { label: _label, ...settings } = { ...base, ...shadow };
    if (text) { delete settings.spread; delete settings.inset; }
    return settings;
  };
  if (value && typeof value === 'object') return trim(value);
  if (!text && scale?.[value]) return trim(scale[value]);
  const soft = text
    ? { sm: [0, 1, 2, 0, 25], md: [0, 2, 4, 0, 35], lg: [0, 4, 8, 0, 45] }
    : { sm: [0, 1, 3, 0, 8], md: [0, 6, 18, -6, 15], lg: [0, 20, 45, -15, 25] };
  if (soft[value]) {
    const [x, y, blur, spread, opacity] = soft[value];
    return trim({ color: 'black', x, y, blur, spread, opacity });
  }
  if (text && value === 'glow') return { ...base, x: 0, y: 0, blur: 16 };
  if (!text && presets?.[value]) return trim(presets[value]);
  return base;
}

export function shadowPreview(value, text, options, presets = {}, scale = {}) {
  if (!value || value === 'none') return { [text ? 'textShadow' : 'boxShadow']: 'none' };
  const shadow = shadowSettings(value, text, presets, scale);
  const color = [BLACK, ...options].find(option => option.value === shadow.color)?.swatch ?? '#6366f1';
  let css = `${!text && shadow.inset ? 'inset ' : ''}${shadow.x}px ${shadow.y}px ${shadow.blur}px ${!text ? `${shadow.spread}px ` : ''}color-mix(in srgb, ${color} ${shadow.opacity}%, transparent)`;
  if (!text && value === 'sm' && Object.entries(shadowSettings('sm')).every(([key, setting]) => shadow[key] === setting)) css += ', 0 1px 2px rgb(0 0 0 / 0.04)';
  return { [text ? 'textShadow' : 'boxShadow']: css };
}
