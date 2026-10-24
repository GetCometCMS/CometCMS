// Run with: node tests/browser/site-search.mjs (requires Chrome/Chromium).
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const runtime = readFileSync(join(root, 'cms/app/Site/assets/search.js'), 'utf8') + '\ninitSearch(document);';
const css = readFileSync(join(root, 'cms/app/Site/assets/site.css'), 'utf8');
// The markup NodeRenderer::search() produces.
const search = `<div class="c-el c-el-search"><div class="c-search c-search-md c-search-outline c-search-icon-left" role="search" data-search="/_site/search/default" data-search-types="blog" data-search-fields="title" data-search-limit="7" data-search-min="2" data-search-locale="de" data-search-empty="Nothing found" data-search-count="{count} results" data-search-show-type>`
  + '<label for="q" class="c-visually-hidden">Search</label><div class="c-search-field"><input type="search" id="q" class="c-search-input" placeholder="Search…" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="q-results"></div>'
  + '<ul id="q-results" class="c-search-results" role="listbox" aria-label="Search results" hidden></ul><p class="c-search-status c-visually-hidden" role="status" aria-live="polite"></p></div></div>';
const checks = `
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const wait = (ms = 260) => new Promise((resolve) => setTimeout(resolve, ms));
const input = document.querySelector('#q');
const list = document.querySelector('#q-results');
const status = document.querySelector('.c-search-status');
const requests = [];
const hits = {
  he: [{ title: 'Hello world', url: '/blog/hello', type: 'Blog' }, { title: 'The <img src=x onerror="window.injected=1"> theme', url: '/blog/theme', type: 'Blog' }],
  zz: [],
};
window.fetch = async (url) => {
  requests.push(new URL(url));
  return new Response(JSON.stringify({ data: hits[new URL(url).searchParams.get('q')] ?? [] }), { headers: { 'Content-Type': 'application/json' } });
};
const type = async (value) => { input.focus(); input.value = value; input.dispatchEvent(new Event('input', { bubbles: true })); await wait(); };
const key = (name) => input.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }));
const selected = () => [...list.querySelectorAll('[aria-selected="true"]')];
let opened = '';
document.addEventListener('click', (event) => { const link = event.target.closest('a'); if (link) { opened = link.getAttribute('href'); event.preventDefault(); } });
try {
  await type('h');
  assert(requests.length === 0 && list.hidden, 'Too short queries do not search');
  await type('he');
  assert(requests.length === 1, 'One request after typing pauses');
  const params = requests[0].searchParams;
  assert(requests[0].pathname === '/_site/search/default', 'The element URL is used');
  assert(params.get('q') === 'he' && params.get('types') === 'blog' && params.get('fields') === 'title' && params.get('limit') === '7' && params.get('locale') === 'de', 'The element configuration is sent');
  assert(!list.hidden && input.getAttribute('aria-expanded') === 'true', 'Hits open the dropdown');
  assert(list.querySelectorAll('[role="option"]').length === 2, 'Each hit is an option');
  assert(list.querySelector('mark').textContent === 'He', 'The match is marked');
  assert(list.querySelector('.c-search-type').textContent === 'Blog', 'Hits show their type');
  assert(!list.querySelector('img') && !window.injected, 'Titles are plain text');
  assert(status.textContent === '2 results', 'The count is announced');
  key('ArrowDown');
  assert(selected().length === 1 && selected()[0] === list.children[0], 'Arrow down highlights the first hit');
  assert(input.getAttribute('aria-activedescendant') === list.children[0].id, 'The active option is referenced');
  key('ArrowDown');
  key('ArrowDown');
  assert(selected()[0] === list.children[0], 'Arrow keys wrap around');
  key('ArrowUp');
  assert(selected()[0] === list.children[1], 'Arrow up moves back');
  key('Enter');
  assert(opened === '/blog/theme', 'Enter opens the highlighted hit');
  key('Escape');
  assert(list.hidden && input.getAttribute('aria-expanded') === 'false' && !input.hasAttribute('aria-activedescendant'), 'Escape closes');
  key('ArrowDown');
  assert(!list.hidden, 'Arrow down reopens the hits');
  opened = '';
  key('Escape');
  key('Enter');
  assert(opened === '', 'Enter on a closed list does nothing');
  input.blur();
  input.focus();
  key('Enter');
  assert(opened === '/blog/hello', 'Enter without a highlight opens the best hit');
  document.querySelector('#outside').focus();
  assert(list.hidden, 'Leaving the field closes the hits');
  await type('zz');
  assert(!list.hidden && list.querySelector('.c-search-empty').textContent === 'Nothing found', 'An empty search says so');
  assert(list.querySelector('.c-search-empty').getAttribute('aria-disabled') === 'true' && status.textContent === 'Nothing found', 'The empty message cannot be chosen');
  key('ArrowDown');
  assert(selected().length === 0, 'Nothing to highlight without hits');
  await type('z');
  assert(list.hidden && list.children.length === 0, 'Shortening the query clears the hits');
  document.body.dataset.testStatus = 'passed';
  document.querySelector('#result').textContent = 'Site search browser checks passed';
} catch (error) {
  document.body.dataset.testStatus = 'failed';
  document.querySelector('#result').textContent = error.stack;
}
`;
const hash = (source) => "'sha256-" + createHash('sha256').update(source).digest('base64') + "'";
const policy = `default-src 'none'; script-src ${hash(runtime)} ${hash(checks)}; style-src 'unsafe-inline'; img-src 'none'`;
const directory = mkdtempSync(join(tmpdir(), 'cometcms-search-browser-'));
try {
  const file = join(directory, 'search.html');
  writeFileSync(file, `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${policy}"><style>${css}</style></head><body class="c-site">${search}<button id="outside">Outside</button><pre id="result"></pre><script type="module">${runtime}</script><script type="module">${checks}</script></body></html>`);
  const result = spawnSync(process.env.CHROME_BIN || 'google-chrome', [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking',
    `--user-data-dir=${join(directory, 'profile')}`, '--dump-dom', '--virtual-time-budget=5000', pathToFileURL(file).href,
  ], { encoding: 'utf8', timeout: 45000, maxBuffer: 2 * 1024 * 1024 });
  if (result.error || result.status !== 0 || !result.stdout.includes('data-test-status="passed"')) {
    throw new Error(result.error?.message || result.stdout.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1] || result.stderr);
  }
  console.log('Site search browser checks passed (debounced requests, combobox keys, plain-text hits, empty state).');
} finally {
  rmSync(directory, { recursive: true, force: true });
}
