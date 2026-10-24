const initialized = new WeakSet();

/** The trusted admin parent also installs this in the builder's script-free iframe. */
export function initGalleryLightbox(doc) {
  if (initialized.has(doc)) return;
  initialized.add(doc);
  let dialog, image, caption, previous, next, counter, close;
  let items = [], index = 0, navigation = false, opener;

  function update() {
    const item = items[index];
    image.src = item.href;
    image.alt = item.querySelector('img')?.alt ?? '';
    caption.textContent = item.dataset.caption ?? '';
    caption.hidden = !caption.textContent;
    counter.textContent = `${index + 1} / ${items.length}`;
    counter.hidden = !navigation;
    previous.hidden = next.hidden = !navigation;
  }

  function move(step) {
    if (!navigation) return;
    index = (index + step + items.length) % items.length;
    update();
  }

  function create() {
    dialog = doc.createElement('dialog');
    dialog.className = 'c-lightbox';
    const icon = (path) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + path + '"/></svg>';
    dialog.innerHTML = '<button type="button" class="c-lightbox-close" autofocus>' + icon('M6 6l12 12M6 18L18 6') + '</button>'
      + '<figure class="c-lightbox-figure"><img class="c-lightbox-image" decoding="async"><figcaption class="c-lightbox-caption"></figcaption></figure>'
      + '<button type="button" class="c-lightbox-previous">' + icon('M15 6l-6 6 6 6') + '</button>'
      + '<button type="button" class="c-lightbox-next">' + icon('M9 6l6 6-6 6') + '</button>'
      + '<p class="c-lightbox-counter" aria-live="polite" aria-atomic="true"></p>';
    image = dialog.querySelector('img');
    caption = dialog.querySelector('figcaption');
    close = dialog.querySelector('.c-lightbox-close');
    previous = dialog.querySelector('.c-lightbox-previous');
    next = dialog.querySelector('.c-lightbox-next');
    counter = dialog.querySelector('.c-lightbox-counter');
    close.addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('keydown', (event) => {
      if (navigation && ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
        event.preventDefault();
        event.stopPropagation();
        move(event.key === 'ArrowLeft' ? -1 : 1);
      }
      // The native dialog handles Escape and keeps focus inside the modal.
      if (event.key === 'Escape') event.stopPropagation();
    });
    dialog.addEventListener('close', () => {
      image.removeAttribute('src');
      items = [];
      if (opener?.isConnected) opener.focus({ preventScroll: true });
    });
    doc.body.append(dialog);
  }

  doc.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('[data-gallery-image]');
    const gallery = trigger?.closest('[data-gallery-lightbox]');
    if (!gallery || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    // The link remains a full-size image fallback in browsers without dialogs.
    if (typeof doc.defaultView?.HTMLDialogElement?.prototype.showModal !== 'function') return;
    event.preventDefault();
    if (!dialog?.isConnected) create();
    items = [...gallery.querySelectorAll('[data-gallery-image]')];
    index = items.indexOf(trigger);
    navigation = gallery.dataset.galleryNavigation === 'true' && items.length > 1;
    opener = trigger;
    dialog.setAttribute('aria-label', gallery.dataset.galleryLabel);
    close.setAttribute('aria-label', gallery.dataset.galleryClose);
    previous.setAttribute('aria-label', gallery.dataset.galleryPrevious);
    next.setAttribute('aria-label', gallery.dataset.galleryNext);
    update();
    dialog.showModal();
  });
}
