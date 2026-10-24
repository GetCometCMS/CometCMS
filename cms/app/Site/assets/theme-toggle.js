// The visitor's light/dark choice. It runs in the head, so a saved choice
// applies before the page paints; buttons are found by delegation.
(() => {
  const KEY = 'cometcms-theme';
  const root = document.documentElement;

  const isDark = () => getComputedStyle(root).colorScheme === 'dark';

  const sync = () => {
    for (const button of document.querySelectorAll('[data-theme-toggle]')) {
      button.setAttribute('aria-pressed', String(isDark()));
    }
  };

  const apply = (theme) => {
    if (theme === 'light' || theme === 'dark') root.dataset.theme = theme;
    else delete root.dataset.theme;
    sync();
  };

  try { apply(localStorage.getItem(KEY)); } catch { apply(null); }

  // The first change is to "interactive": the buttons exist, before deferred scripts run.
  document.addEventListener('readystatechange', sync, { once: true });
  // Without a saved choice, "auto" themes follow the system.
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', sync);
  addEventListener('storage', (event) => { if (event.key === KEY) apply(event.newValue); });
  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-theme-toggle]')) return;
    const theme = isDark() ? 'light' : 'dark';
    try { localStorage.setItem(KEY, theme); } catch {}
    apply(theme);
  });
})();
