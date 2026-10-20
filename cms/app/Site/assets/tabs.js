const documents = new WeakMap();

/** Switch one group without affecting nested or neighboring groups. */
export function activateTab(button, focus = false) {
  const group = button?.closest('[data-tabs]');
  if (!group) return;
  const doc = group.ownerDocument;
  for (const tab of group.querySelectorAll(':scope > .c-tabs-list > [role="tab"]')) {
    const selected = tab === button;
    tab.setAttribute('aria-selected', String(selected));
    tab.tabIndex = selected ? 0 : -1;
    const panel = doc.getElementById(tab.getAttribute('aria-controls'));
    if (panel) panel.hidden = !selected;
  }
  group.setAttribute('data-tabs-ready', '');
  documents.get(doc)?.set(group.dataset.tabs, { selected: button.dataset.tabKey, initial: group.dataset.tabsInitial });
  if (focus) {
    button.focus({ preventScroll: true });
    button.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
  }
}

/** Reveal an editor selection, including panels inside other tab groups. */
export function revealTabPanel(element) {
  for (let panel = element?.closest('.c-tabs-panel'); panel; panel = panel.parentElement?.closest('.c-tabs-panel')) {
    const button = panel.ownerDocument.getElementById(panel.getAttribute('aria-labelledby'));
    if (button) activateTab(button);
  }
}

/** Refresh after preview patches; listeners are installed only once per document. */
export function initTabs(doc) {
  if (!documents.has(doc)) {
    documents.set(doc, new Map());
    doc.addEventListener('click', (event) => {
      const button = event.target.closest?.('[data-tabs] > .c-tabs-list > [role="tab"]');
      if (button) activateTab(button);
    });
    doc.addEventListener('keydown', (event) => {
      const button = event.target.closest?.('[data-tabs] > .c-tabs-list > [role="tab"]');
      if (!button || event.altKey || event.ctrlKey || event.metaKey) return;
      const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
      if (!keys.includes(event.key)) return;
      const tabs = [...button.parentElement.children];
      const index = tabs.indexOf(button);
      const rtl = doc.defaultView?.getComputedStyle(button).direction === 'rtl';
      const step = (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + step + tabs.length) % tabs.length;
      event.preventDefault();
      event.stopImmediatePropagation();
      activateTab(tabs[next], true);
    });
  }
  const state = documents.get(doc);
  const present = new Set();
  for (const group of doc.querySelectorAll('[data-tabs]')) {
    present.add(group.dataset.tabs);
    const tabs = [...group.querySelectorAll(':scope > .c-tabs-list > [role="tab"]')];
    const remembered = state.get(group.dataset.tabs);
    const selected = tabs.find((tab) => remembered?.initial === group.dataset.tabsInitial && tab.dataset.tabKey === remembered?.selected)
      ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0];
    if (selected) activateTab(selected);
  }
  for (const key of state.keys()) if (!present.has(key)) state.delete(key);
}
