// Run with: node tests/browser/menus.mjs (requires Chrome/Chromium).
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const runtime = readFileSync(join(root, 'cms/app/Site/assets/menus.js'), 'utf8') + '\ninitMenus(document);';
const css = readFileSync(join(root, 'cms/app/Site/assets/site.css'), 'utf8');
const theme = ':root{--c-primary:#2563eb;--c-background:#ffffff;--c-text:#111111;--c-border:#e2e8f0;--radius:6px;--radius-box:8px}';
// The markup NodeRenderer::menu() produces: a group heading and a link, each with three levels below.
const chevron = '<span class="c-menu-chevron" aria-hidden="true"></span>';
const details = (name, summary, list) => `<details class="c-menu-disclosure" name="${name}">${summary}<ul class="c-submenu">${list}</ul></details>`;
const leaf = (label, href = '#' + label) => `<li><div class="c-menu-label"><a href="${href}">${label}</a></div></li>`;
const linked = (name, label, list) => `<li class="c-has-children"><div class="c-menu-label"><a href="/${label}">${label}</a></div>${details(name, `<summary aria-label="${label}">${chevron}</summary>`, list)}</li>`;
const group = (name, label, list) => `<li class="c-has-children c-menu-group">${details(name, `<summary class="c-menu-label"><span>${label}</span>${chevron}</summary>`, list)}</li>`;
const nav = '<section><nav class="c-menu c-menu-horizontal c-menu-collapsible" data-menu aria-label="Main"><input type="checkbox" id="m-t" class="c-menu-toggle" aria-label="Menu"><label for="m-t" class="c-menu-button" aria-hidden="true"><span></span><span></span><span></span></label><ul class="c-menu-list">'
  + group('m', 'Products', linked('m-0', 'Software', linked('m-0-0', 'Apps', leaf('iOS') + leaf('Android')) + leaf('Cloud')) + group('m-0', 'Hardware', leaf('Laptops')))
  + linked('m', 'About', leaf('Team') + leaf('Jobs'))
  + leaf('Contact') + '</ul></nav></section><p id="outside">Elsewhere</p>';
const checks = `
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const $ = (selector) => document.querySelector(selector);
const box = (element) => element.getBoundingClientRect();
const products = $('.c-menu-group > details'), about = $('a[href="/About"]').closest('li').querySelector('details');
const software = $('a[href="/Software"]').closest('li').querySelector(':scope > details'), hardware = [...document.querySelectorAll('.c-submenu .c-menu-group > details')][0];
const apps = $('a[href="/Apps"]').closest('li').querySelector(':scope > details');
const middle = (element) => { const rect = box(element); return rect.top + rect.height / 2; };
try {
  products.querySelector('summary').click();
  assert(products.open, 'A group heading opens from its label');
  const label = products.querySelector('summary > span:first-child'), mark = products.querySelector('.c-menu-chevron');
  assert(Math.abs(middle(label) - middle(mark)) < 1 && box(mark).left >= box(label).right - 1, 'The chevron sits centered after the heading');
  const aboutLink = $('a[href="/About"]'), aboutMark = about.querySelector('.c-menu-chevron');
  assert(Math.abs(middle(aboutLink) - middle(aboutMark)) < 1 && Math.abs(box(aboutMark).left - box(aboutLink).right) < 1, 'A link and its toggle share one row');
  assert(getComputedStyle(products.querySelector(':scope > .c-submenu')).position === 'absolute', 'The first level drops down');
  software.querySelector('summary').click();
  apps.querySelector('summary').click();
  const appsList = apps.querySelector(':scope > .c-submenu'), panel = products.querySelector(':scope > .c-submenu');
  assert(software.open && apps.open && getComputedStyle(appsList).position === 'static', 'Deeper levels open in place');
  assert(box(appsList).left > box(software.querySelector(':scope > .c-submenu')).left && box(appsList).right <= box(panel).right + 1, 'Each level indents inside the dropdown');
  assert(Math.abs(middle($('a[href="/Software"]')) - middle(software.querySelector('.c-menu-chevron'))) < 1, 'Nested toggles align with their link');
  hardware.querySelector('summary').click();
  assert(hardware.open && !software.open, 'Opening a sibling closes the other');
  about.querySelector('summary').click();
  assert(about.open && !products.open, 'Opening another dropdown closes the first');
  $('#outside').click();
  assert(!about.open, 'A click elsewhere closes the dropdown');
  products.querySelector('summary').click();
  hardware.querySelector('summary').focus();
  document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  assert(!products.open && document.activeElement === products.querySelector('summary'), 'Escape closes the dropdown and returns focus');
  products.querySelector('summary').click();
  $('a[href="#Laptops"]').click();
  assert(!products.open, 'Following a link closes the dropdown');
  document.body.dataset.testStatus = 'passed';
  document.querySelector('#result').textContent = 'Menu browser checks passed';
} catch (error) {
  document.body.dataset.testStatus = 'failed';
  document.querySelector('#result').textContent = error.stack;
}
`;
const hash = (source) => "'sha256-" + createHash('sha256').update(source).digest('base64') + "'";
const policy = `default-src 'none'; script-src ${hash(runtime)} ${hash(checks)}; style-src 'unsafe-inline'; img-src 'none'`;
const directory = mkdtempSync(join(tmpdir(), 'cometcms-menus-browser-'));
try {
  const file = join(directory, 'menus.html');
  writeFileSync(file, `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${policy}"><style>${theme}${css}</style></head><body class="c-site">${nav}<pre id="result"></pre><script type="module">${runtime}</script><script type="module">${checks}</script></body></html>`);
  const result = spawnSync(process.env.CHROME_BIN || 'google-chrome', [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking', '--window-size=1280,900',
    `--user-data-dir=${join(directory, 'profile')}`, '--dump-dom', '--virtual-time-budget=5000', pathToFileURL(file).href,
  ], { encoding: 'utf8', timeout: 45000, maxBuffer: 2 * 1024 * 1024 });
  if (result.error || result.status !== 0 || !result.stdout.includes('data-test-status="passed"')) {
    throw new Error(result.error?.message || result.stdout.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1] || result.stderr);
  }
  console.log('Menu browser checks passed (group headings, aligned toggles, three nested levels, exclusive siblings, closing).');
} finally {
  rmSync(directory, { recursive: true, force: true });
}
