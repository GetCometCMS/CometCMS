/** Include editor styles before the document paints, avoiding late outline transitions. */
export function withPreviewStyles(html, css) {
  if (!html || !css) return html;
  return html.replace(/<\/head\s*>/i, () => `<style data-preview-styles>${css}</style></head>`);
}
