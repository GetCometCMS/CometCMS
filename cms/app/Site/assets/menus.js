const documents = new WeakSet();

/** A disclosure whose submenu floats over the page, as opposed to one that opens in place. */
const floats = (details) => {
  const submenu = details.querySelector(':scope > .c-submenu');
  return Boolean(submenu) && details.ownerDocument.defaultView?.getComputedStyle(submenu).position === 'absolute';
};

/** Close what a menu has open: floating submenus and its dropdown, side or full-screen panel. */
export function closeMenu(nav, except = null) {
  for (const details of nav.querySelectorAll('details[open]')) {
    if (!details.contains(except) && floats(details)) details.open = false;
  }
  const toggle = nav.querySelector(':scope > .c-menu-toggle');
  if (toggle && !except) toggle.checked = false;
}

/** Install once per document; menus open and close without it, this closes them on the way out. */
export function initMenus(doc) {
  if (documents.has(doc)) return;
  documents.add(doc);
  doc.addEventListener('click', (event) => {
    const target = event.target instanceof doc.defaultView.Node ? event.target : null;
    for (const nav of doc.querySelectorAll('[data-menu]')) {
      if (!nav.contains(target)) {
        closeMenu(nav);
      } else if (target.closest('a[href]') && !event.defaultPrevented) {
        // An anchor on the same page would otherwise leave the panel covering it.
        closeMenu(nav);
      } else {
        closeMenu(nav, target);
      }
    }
  });
  doc.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const nav = doc.activeElement?.closest?.('[data-menu]')
      ?? [...doc.querySelectorAll('[data-menu]')].find((menu) => menu.querySelector(':scope > .c-menu-toggle:checked, details[open]'));
    if (!nav) return;
    let open = doc.activeElement?.closest?.('details[open]');
    while (open && !floats(open)) open = open.parentElement.closest('details[open]');
    if (open) {
      open.open = false;
      open.querySelector(':scope > summary')?.focus();
    } else if (nav.querySelector(':scope > .c-menu-toggle:checked')) {
      closeMenu(nav);
      nav.querySelector(':scope > .c-menu-toggle')?.focus();
    } else {
      closeMenu(nav);
    }
  });
}
