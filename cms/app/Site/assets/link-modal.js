const initialized = new WeakSet();

/**
 * Opens marked same-site links in the modal template. The page shows embedded
 * (?embed=1: its main content only) in a fresh frame per page, so the
 * visitor's history stays untouched, sized to its content.
 */
export function initLinkModal(doc) {
  if (initialized.has(doc)) return;
  initialized.add(doc);
  const view = doc.defaultView;
  let dialog, slot, opener, frame;

  const modified = (event) => event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey;

  function prepare() {
    dialog = doc.querySelector('dialog[data-link-modal-dialog]');
    slot = dialog?.querySelector('.c-el-site-content');
    if (!slot) {
      dialog = null;
      return false;
    }
    for (const button of dialog.querySelectorAll('[data-modal-close]')) button.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target !== dialog || !dialog.hasAttribute('data-backdrop-close')) return;
      // Clicks on the panel's own padding also target the dialog; only the backdrop lies outside it.
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
      frame?.remove();
      frame = null;
      if (opener?.isConnected) opener.focus({ preventScroll: true });
    });
    return true;
  }

  function show(url, label) {
    dialog.setAttribute('aria-label', label);
    for (const title of dialog.querySelectorAll('[data-modal-title]')) title.textContent = label;
    frame?.remove();
    frame = doc.createElement('iframe');
    frame.className = 'c-modal-frame';
    frame.title = label;
    const src = new URL(url);
    src.searchParams.set('embed', '1');
    frame.src = src.href;
    frame.addEventListener('load', () => embedded(frame, src.hash));
    slot.append(frame);
    if (!dialog.open) dialog.showModal();
  }

  function embedded(current, hash) {
    const inner = current.contentDocument;
    if (current !== frame || !inner?.body) return;
    if (doc.documentElement.dataset.theme) inner.documentElement.dataset.theme = doc.documentElement.dataset.theme;
    const fit = () => { current.style.height = `${Math.ceil(inner.body.getBoundingClientRect().height)}px`; };
    fit();
    new current.contentWindow.ResizeObserver(fit).observe(inner.body);
    const target = hash && inner.getElementById(decodeURIComponent(hash.slice(1)));
    if (target) target.scrollIntoView();
    inner.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !event.defaultPrevented) dialog.close();
    });
    // Modal links swap the page in place; other links leave the modal for the whole window.
    inner.addEventListener('click', (event) => {
      const link = event.target.closest?.('a[href]');
      if (!link || event.defaultPrevented || modified(event) || link.target || link.hasAttribute('download')) return;
      const url = new URL(link.href, inner.baseURI);
      if (url.hash && url.href.split('#')[0] === inner.location.href.split('#')[0]) return;
      event.preventDefault();
      if (link.hasAttribute('data-link-modal') && url.origin === doc.location.origin) show(url.href, label(link, url));
      else view.location.assign(url.href);
    });
  }

  function label(link, url) {
    return link.getAttribute('aria-label') || link.textContent.trim() || link.querySelector('img')?.alt || url.pathname;
  }

  doc.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('a[data-link-modal]');
    if (!trigger || event.defaultPrevented || modified(event)) return;
    if (typeof view?.HTMLDialogElement?.prototype.showModal !== 'function') return;
    const url = new URL(trigger.href, doc.baseURI);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== doc.location.origin) return;
    if (!dialog?.isConnected && !prepare()) return;
    event.preventDefault();
    if (!dialog.open) opener = trigger;
    show(url.href, label(trigger, url));
  });
}
