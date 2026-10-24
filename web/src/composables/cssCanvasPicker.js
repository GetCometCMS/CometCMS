/** A selector for a rendered target, bounded by the CSS panel's current scope. */
export function cssCanvasTarget(target, { root = null, className = null, isEditable }) {
  if (!target?.closest || target.closest('[data-preview-keep], [data-page-area], .c-lightbox')) return null;
  if (root && !root.contains(target)) return null;
  let owner = target.closest('[data-node]');
  while (owner && !isEditable(owner.dataset.node)) owner = owner.parentElement?.closest('[data-node]');
  if (!owner) return null;

  const classes = [...target.classList].filter((name) => !/^c-preview-/.test(name));
  const selector = classes.length ? classes.map((name) => `.${CSS.escape(name)}`).join('') : target.localName;
  if (!selector) return null;
  const relative = root === target ? '&' : selector;
  return {
    element: target,
    selector: relative,
    label: root ? (root === target ? `.${className}` : `.${className} ${selector}`) : selector,
  };
}
