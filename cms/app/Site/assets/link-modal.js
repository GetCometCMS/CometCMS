const initialized = new WeakSet();

/** Keep the destination's stylesheet and bundled interactions inside its own document. */
export function initLinkModal(doc) {
  if (initialized.has(doc)) return;
  initialized.add(doc);
  let dialog, frame, title, close, opener;

  function escape(event) {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    event.preventDefault();
    event.stopPropagation();
    dialog.close();
  }

  function prepare() {
    dialog = doc.querySelector('[data-link-modal-dialog]');
    frame = dialog?.querySelector('iframe[data-modal-content]');
    if (!frame) { dialog = null; return false; }
    title = dialog.querySelector('.c-link-modal-title');
    close = dialog.querySelector('.c-link-modal-close');
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', escape);
    dialog.addEventListener('click', (event) => {
      if (event.target !== dialog) return;
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    frame.addEventListener('load', () => {
      // A visitor can navigate further within the frame, including off-site.
      try {
        frame.contentDocument?.addEventListener('keydown', escape);
      } catch {
        // Cross-origin documents handle their own keyboard events.
      }
    });
    dialog.addEventListener('close', () => {
      frame.src = 'about:blank';
      if (opener?.isConnected) opener.focus({ preventScroll: true });
    });
    return true;
  }

  doc.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('a[data-link-modal]');
    if (!trigger || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (typeof doc.defaultView?.HTMLDialogElement?.prototype.showModal !== 'function') return;
    const url = new URL(trigger.href, doc.baseURI);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== doc.location.origin) return;
    if (!dialog?.isConnected && !prepare()) return;
    event.preventDefault();
    if (!dialog.open) opener = trigger;
    const label = trigger.getAttribute('aria-label') || trigger.textContent.trim() || trigger.querySelector('img')?.alt || url.pathname;
    dialog.setAttribute('aria-label', label);
    close.setAttribute('aria-label', trigger.dataset.modalClose);
    title.textContent = label;
    title.href = url.href;
    frame.title = label;
    frame.src = url.href;
    dialog.showModal();
  });
}
