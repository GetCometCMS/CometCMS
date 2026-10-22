// Run with: node tests/browser/menu-colors.mjs (requires Chrome/Chromium).
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const css = readFileSync(join(root, 'cms/app/Site/assets/site.css'), 'utf8');
// What Theme::stylesheet() emits, reduced to the colors a menu uses.
const theme = ':root{--c-primary:#2563eb;--c-primary-rgb:37 99 235;--c-on-primary:#ffffff;--c-accent:#0d9488;--c-background:#ffffff;--c-text:#111111;'
  + '--c-muted:#64748b;--c-on-muted:#ffffff;--c-border:#e2e8f0}';
// The markup NodeRenderer::menu() produces for a current page with a submenu, opened.
const menu = (id, style = '', fg = '') => `<div class="c-el c-el-menu${fg}"><nav id="${id}" class="c-menu c-menu-horizontal c-menu-collapsible"${style} aria-label="Main">`
  + `<input type="checkbox" id="${id}-t" class="c-menu-toggle" aria-label="Menu"><label for="${id}-t" class="c-menu-button" aria-hidden="true"><span></span><span></span><span></span></label>`
  + '<ul class="c-menu-list"><li class="c-has-children"><div class="c-menu-label"><a href="/" aria-current="page">Home</a></div>'
  + `<details class="c-menu-disclosure" name="${id}" open><summary aria-label="Home"><span class="c-menu-chevron" aria-hidden="true"></span></summary>`
  + '<ul class="c-submenu"><li><div class="c-menu-label"><a href="/a">Sub</a></div></li><li><div class="c-menu-label"><a href="/b" aria-current="page">Current sub</a></div></li></ul></details></li>'
  + '<li><div class="c-menu-label"><a href="/about">About</a></div></li></ul></nav></div>';
const sections = `<section class="c-bg-muted">${menu('filled')}</section><section class="c-bg-background">${menu('plain')}</section>`
  + `<section class="c-bg-muted">${menu('colored', ' style="--c-menu-color:var(--c-accent)"', ' c-fg-accent')}</section>`
  + `<section class="c-bg-muted">${menu('panel', ' style="--c-menu-background:var(--c-primary);--c-menu-text:var(--c-on-primary)"')}</section>`;
const checks = `
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const color = (selector, property = 'color') => getComputedStyle(document.querySelector(selector))[property];
const white = 'rgb(255, 255, 255)', text = 'rgb(17, 17, 17)', primary = 'rgb(37, 99, 235)', accent = 'rgb(13, 148, 136)';
try {
  assert(color('#filled > ul > li > div a') === white, 'A current link on a fill keeps the fill text color: ' + color('#filled > ul > li > div a'));
  assert(color('#filled a[href="/about"]') === white, 'Bar links on a fill use its text color');
  assert(color('#filled .c-submenu', 'backgroundColor') === white, 'Submenus default to the page background');
  assert(color('#filled a[href="/a"]') === text, 'Submenu links on a fill stay readable: ' + color('#filled a[href="/a"]'));
  assert(color('#filled a[href="/b"]') === primary, 'A current submenu link is highlighted in the primary color');
  assert(color('#plain > ul > li > div a') === primary, 'A current link on a plain section is highlighted in the primary color');
  assert(color('#colored a[href="/about"]') === accent && color('#colored > ul > li > div a') === accent, 'A menu text color tints the bar');
  assert(color('#colored a[href="/a"]') === text, 'A menu text color stays out of its submenus: ' + color('#colored a[href="/a"]'));
  assert(color('#panel .c-submenu', 'backgroundColor') === primary && color('#panel a[href="/a"]') === white && color('#panel a[href="/b"]') === white, 'The panel palette dresses submenus');
  document.body.dataset.testStatus = 'passed';
  document.querySelector('#result').textContent = 'Menu color browser checks passed';
} catch (error) {
  document.body.dataset.testStatus = 'failed';
  document.querySelector('#result').textContent = error.stack;
}
`;
const hash = (source) => "'sha256-" + createHash('sha256').update(source).digest('base64') + "'";
const policy = `default-src 'none'; script-src ${hash(checks)}; style-src 'unsafe-inline'; img-src 'none'`;
const directory = mkdtempSync(join(tmpdir(), 'cometcms-menu-colors-browser-'));
try {
  const file = join(directory, 'menu-colors.html');
  writeFileSync(file, `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${policy}"><style>${theme}${css}</style></head><body class="c-site">${sections}<pre id="result"></pre><script type="module">${checks}</script></body></html>`);
  const result = spawnSync(process.env.CHROME_BIN || 'google-chrome', [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking', '--window-size=1280,900',
    `--user-data-dir=${join(directory, 'profile')}`, '--dump-dom', '--virtual-time-budget=5000', pathToFileURL(file).href,
  ], { encoding: 'utf8', timeout: 45000, maxBuffer: 2 * 1024 * 1024 });
  if (result.error || result.status !== 0 || !result.stdout.includes('data-test-status="passed"')) {
    throw new Error(result.error?.message || result.stdout.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1] || result.stderr);
  }
  console.log('Menu color browser checks passed (fills, plain sections, menu text color, dropdown palette).');
} finally {
  rmSync(directory, { recursive: true, force: true });
}
