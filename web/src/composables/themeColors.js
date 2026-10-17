export const MAX_CUSTOM_COLORS = 64;

/** Saved palette tokens shared by backgrounds, text and divider colors. */
export function themeColorOptions(theme, values, title) {
  const palette = theme?.mode === "dark" ? theme.dark_colors : theme?.colors;
  const predefined = values.filter(Boolean).map((value) => ({
    value,
    label: title(value),
    swatch: palette?.[value === "inverse" ? "background" : value] ?? "#cccccc",
    custom: false,
  }));
  const custom = Object.entries(theme?.custom_colors ?? {}).map(([value, color]) => ({
    value,
    label: color.label,
    swatch: theme?.mode === "dark" ? color.dark_color : color.color,
    custom: true,
  }));
  return [...predefined, ...custom];
}
