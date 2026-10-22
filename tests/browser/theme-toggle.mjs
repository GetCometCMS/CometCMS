// Run with: node tests/browser/theme-toggle.mjs (requires Chrome/Chromium).
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const runtime = readFileSync(join(root, 'cms/app/Site/assets/theme-toggle.js'), 'utf8');
const css = readFileSync(join(root, 'cms/app/Site/assets/site.css'), 'utf8');
// What Theme::stylesheet() emits for a light theme, reduced to one color.
const theme = ':root{color-scheme:light;--c-text:#111}:root[data-theme="light"]{color-scheme:light;--c-text:#111}:root[data-theme="dark"]{color-scheme:dark;--c-text:#eee}';
// A choice saved on an earlier visit, before the toggle's head script runs.
const saved = "localStorage.setItem('cometcms-theme', 'dark');";
// The markup NodeRenderer::themeToggle() produces.
const toggle = '<div class="c-el c-el-theme_toggle"><button type="button" class="c-theme-toggle c-theme-toggle-plain" data-theme-toggle aria-pressed="false" aria-label="Dark mode" title="Dark mode">'
  + '<svg class="c-theme-icon c-theme-icon-light" width="20" height="20"></svg><svg class="c-theme-icon c-theme-icon-dark" width="20" height="20"></svg></button></div>';
const checks = `
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const root = document.documentElement;
const button = document.querySelector('[data-theme-toggle]');
const shown = (mode) => getComputedStyle(button.querySelector('.c-theme-icon-' + mode)).display !== 'none';
try {
  assert(root.dataset.theme === 'dark' && getComputedStyle(root).colorScheme === 'dark', 'A saved choice applies');
  assert(button.getAttribute('aria-pressed') === 'true', 'The button starts pressed in dark mode');
  assert(shown('dark') && !shown('light'), 'Only the dark icon shows in dark mode');
  button.querySelector('svg').dispatchEvent(new MouseEvent('click', { bubbles: true }));
  assert(root.dataset.theme === 'light' && getComputedStyle(root).colorScheme === 'light', 'A click on an icon switches to light');
  assert(button.getAttribute('aria-pressed') === 'false' && shown('light') && !shown('dark'), 'The button follows the mode');
  assert(localStorage.getItem('cometcms-theme') === 'light', 'The choice is saved');
  button.click();
  assert(root.dataset.theme === 'dark' && localStorage.getItem('cometcms-theme') === 'dark', 'A second click switches back');
  document.body.dataset.testStatus = 'passed';
  document.querySelector('#result').textContent = 'Theme toggle browser checks passed';
} catch (error) {
  document.body.dataset.testStatus = 'failed';
  document.querySelector('#result').textContent = error.stack;
}
`;
const hash = (source) => "'sha256-" + createHash('sha256').update(source).digest('base64') + "'";
const policy = `default-src 'none'; script-src ${hash(saved)} ${hash(runtime)} ${hash(checks)}; style-src 'unsafe-inline'; img-src 'none'`;
const directory = mkdtempSync(join(tmpdir(), 'cometcms-theme-toggle-browser-'));
try {
  const file = join(directory, 'theme-toggle.html');
  writeFileSync(file, `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${policy}"><style>${theme}${css}</style><script>${saved}</script><script>${runtime}</script></head><body class="c-site">${toggle}<pre id="result"></pre><script type="module">${checks}</script></body></html>`);
  const result = spawnSync(process.env.CHROME_BIN || 'google-chrome', [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking',
    `--user-data-dir=${join(directory, 'profile')}`, '--dump-dom', '--virtual-time-budget=5000', pathToFileURL(file).href,
  ], { encoding: 'utf8', timeout: 45000, maxBuffer: 2 * 1024 * 1024 });
  if (result.error || result.status !== 0 || !result.stdout.includes('data-test-status="passed"')) {
    throw new Error(result.error?.message || result.stdout.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1] || result.stderr);
  }
  console.log('Theme toggle browser checks passed (saved choice before paint, click switching, pressed state, icons).');
} finally {
  rmSync(directory, { recursive: true, force: true });
}
