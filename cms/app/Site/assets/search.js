const DELAY = 180;
const ready = new WeakSet();

/** The first occurrence of the query in a title, marked; other text stays plain. */
function highlighted(title, query) {
  const fragment = document.createDocumentFragment();
  const lower = title.toLocaleLowerCase();
  const index = lower.length === title.length ? lower.indexOf(query.toLocaleLowerCase()) : -1;
  if (index < 0) {
    fragment.append(title);
    return fragment;
  }
  const mark = document.createElement('mark');
  mark.textContent = title.slice(index, index + query.length);
  fragment.append(title.slice(0, index), mark, title.slice(index + query.length));
  return fragment;
}

/** One search element: a combobox whose listbox shows the hits as links. */
function setup(root) {
  const input = root.querySelector('.c-search-input');
  const list = root.querySelector('.c-search-results');
  const status = root.querySelector('.c-search-status');
  if (!input || !list || ready.has(root)) return;
  ready.add(root);

  const data = root.dataset;
  const min = Math.max(2, Number(data.searchMin) || 2);
  let timer = 0;
  let request = null;
  let active = -1;

  const options = () => [...list.querySelectorAll('[role="option"]:not([aria-disabled="true"])')];

  function open() {
    if (!list.children.length) return;
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function close() {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    highlight(-1);
  }

  function highlight(index) {
    active = index;
    options().forEach((option, position) => option.setAttribute('aria-selected', String(position === index)));
    const current = options()[index];
    if (current) {
      input.setAttribute('aria-activedescendant', current.id);
      current.scrollIntoView?.({ block: 'nearest' });
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  }

  function render(hits, query) {
    list.replaceChildren();
    active = -1;
    hits.forEach((hit, index) => {
      const option = document.createElement('li');
      option.id = `${list.id}-${index}`;
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      const link = document.createElement('a');
      link.href = hit.url;
      link.tabIndex = -1;
      const title = document.createElement('span');
      title.className = 'c-search-title';
      title.append(highlighted(String(hit.title || hit.url), query));
      link.append(title);
      if (data.searchShowType !== undefined && hit.type) {
        const type = document.createElement('span');
        type.className = 'c-search-type';
        type.textContent = hit.type;
        link.append(type);
      }
      option.append(link);
      list.append(option);
    });
    if (!hits.length) {
      const empty = document.createElement('li');
      empty.className = 'c-search-empty';
      empty.setAttribute('role', 'option');
      empty.setAttribute('aria-disabled', 'true');
      empty.textContent = data.searchEmpty || '';
      list.append(empty);
    }
    if (status) status.textContent = hits.length ? (data.searchCount || '{count}').replace('{count}', String(hits.length)) : (data.searchEmpty || '');
    open();
  }

  async function run(query) {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    const url = new URL(data.search, document.baseURI);
    url.searchParams.set('q', query);
    for (const [param, key] of [['types', 'searchTypes'], ['fields', 'searchFields'], ['limit', 'searchLimit'], ['locale', 'searchLocale']]) {
      if (data[key]) url.searchParams.set(param, data[key]);
    }
    root.classList.add('c-search-loading');
    try {
      const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`Search failed (${response.status})`);
      const body = await response.json();
      if (input.value.trim() === query) render(Array.isArray(body?.data) ? body.data : [], query);
    } catch (error) {
      if (error?.name !== 'AbortError') close();
    } finally {
      if (request === controller) {
        request = null;
        root.classList.remove('c-search-loading');
      }
    }
  }

  input.addEventListener('input', () => {
    clearTimeout(timer);
    const query = input.value.trim();
    if (query.length < min) {
      request?.abort();
      list.replaceChildren();
      if (status) status.textContent = '';
      close();
      return;
    }
    timer = setTimeout(() => run(query), DELAY);
  });

  input.addEventListener('keydown', (event) => {
    if (event.altKey || event.ctrlKey || event.metaKey || event.isComposing) return;
    const count = options().length;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      if (!list.children.length) return;
      event.preventDefault();
      if (list.hidden) {
        open();
        return;
      }
      if (!count) return;
      const step = event.key === 'ArrowDown' ? 1 : -1;
      highlight(active < 0 ? (step > 0 ? 0 : count - 1) : (active + step + count) % count);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      // Enter opens the highlighted hit, or the best one.
      const link = !list.hidden && (options()[active] ?? options()[0])?.querySelector('a');
      link?.click();
    } else if (event.key === 'Escape' && !list.hidden) {
      event.preventDefault();
      close();
    }
  });

  input.addEventListener('focus', () => {
    if (input.value.trim().length >= min) open();
  });
  root.addEventListener('focusout', (event) => {
    if (!root.contains(event.relatedTarget)) close();
  });
  // Keep the focus in the field while a hit is clicked.
  list.addEventListener('mousedown', (event) => event.preventDefault());
  list.addEventListener('mousemove', (event) => {
    const option = event.target.closest?.('[role="option"]:not([aria-disabled="true"])');
    const index = options().indexOf(option);
    if (index >= 0 && index !== active) highlight(index);
  });
}

function initSearch(doc) {
  for (const root of doc.querySelectorAll('[data-search]')) setup(root);
}
