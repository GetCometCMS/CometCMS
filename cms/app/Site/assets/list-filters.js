const documents = new WeakSet();

/** Apply a list's filters as soon as one changes; the button remains for pages without this script. */
export function initListFilters(doc) {
  for (const form of doc.querySelectorAll('[data-list-filters]')) form.setAttribute('data-list-filters-ready', '');
  if (documents.has(doc)) return;
  documents.add(doc);
  doc.addEventListener('change', (event) => {
    const form = event.target.closest?.('[data-list-filters]');
    if (form && event.target.name) form.requestSubmit();
  });
  // Unused fields stay out of the address, which keeps shared links short.
  doc.addEventListener('submit', (event) => {
    const form = event.target.closest?.('[data-list-filters]');
    if (!form) return;
    for (const field of form.elements) {
      if (field.name && field.value === '' && !field.disabled) {
        field.disabled = true;
        setTimeout(() => { field.disabled = false; });
      }
    }
  });
}
