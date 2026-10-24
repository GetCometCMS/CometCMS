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

  function create() {
    dialog = doc.createElement('dialog');
    dialog.className = 'c-link-modal';
    dialog.innerHTML = '<div class="c-link-modal-bar"><a class="c-link-modal-title"></a>'
      + '<button type="button" class="c-link-modal-close" autofocus><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M6 6l12 12M6 18L18 6"/></svg></button></div>'
      + '<iframe class="c-link-modal-frame"></iframe>';
    frame = dialog.querySelector('iframe');
    title = dialog.querySelector('a');
    close = dialog.querySelector('button');
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
    doc.body.append(dialog);
  }

  doc.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('a[data-link-modal]');
    if (!trigger || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (typeof doc.defaultView?.HTMLDialogElement?.prototype.showModal !== 'function') return;
    const url = new URL(trigger.href, doc.baseURI);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== doc.location.origin) return;
    event.preventDefault();
    if (!dialog?.isConnected) create();
    opener = trigger;
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
