// Run with: node tests/browser/preview-interactions.mjs (requires PHP and Chrome/Chromium).
import { spawn, execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';

const root = fileURLToPath(new URL('../../', import.meta.url));
const directory = mkdtempSync(join(tmpdir(), 'cometcms-preview-browser-'));
const profile = join(directory, 'chrome');
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const assert = (ok, message) => { if (!ok) throw new Error(message); };
let php, chrome, socket;
const pending = new Map();
let sequence = 0;
const command = (method, params = {}) => new Promise((resolve, reject) => {
  const id = ++sequence;
  pending.set(id, { resolve, reject });
  socket.send(JSON.stringify({ id, method, params }));
});
async function evaluate(expression) {
  const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  return result.result.value;
}
async function waitFor(expression, label) {
  for (let i = 0; i < 100; i++) {
    if (await evaluate(expression)) return;
    await delay(50);
  }
  throw new Error('Timed out: ' + label);
}

try {
  for (const path of ['app', 'config', 'index.php']) cpSync(join(root, 'cms', path), join(directory, path), { recursive: true });
  writeFileSync(join(directory, 'router.php'), `<?php
$_SERVER['SCRIPT_NAME'] = '/cms/index.php';
require __DIR__ . '/index.php';`);
  writeFileSync(join(directory, 'seed.php'), `<?php
require __DIR__ . '/app/bootstrap.php';
$users = new \\CometCMS\\Auth\\UserRepository();
$users->create('preview-admin', 'secret-password', 'admin');
$users->update('preview-admin', ['theme' => 'orange']);
(new \\CometCMS\\Workspaces\\WorkspaceRepository())->all();
(new \\CometCMS\\Content\\ContentTypeRepository())->save(['name' => 'posts']);
$content = \\CometCMS\\Content\\ContentRepository::make();
$content->save('posts', ['title' => 'Gallery draft', 'slug' => 'gallery'], ['id' => 'preview-admin']);
$site = new \\CometCMS\\Site\\SiteRepository();
$site->saveSettings(['routes' => ['posts' => ['enabled' => true, 'pattern' => '/posts/{slug}', 'index_path' => '/posts']]]);
foreach (['one', 'two'] as $file) file_put_contents($site->workspace()->path('media') . '/' . $file . '.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="40"><rect width="60" height="40" fill="teal"/></svg>');
$node = \\CometCMS\\Site\\LayoutSchema::node(...);
$site->saveLayout('posts', 'detail', [$node('section', [], [], [$node('row', [], [], [$node('column', [], [], [
    $node('gallery', ['media' => ['one.svg', 'two.svg'], 'lightbox' => true, 'lightbox_navigation' => true]),
    $node('tabs', [], [], [$node('tab', ['title' => 'First'], [], [$node('heading', ['text' => 'First panel'])]), $node('tab', ['title' => 'Second'], [], [$node('heading', ['text' => 'Second panel'])])]),
    $node('search'), $node('theme_toggle'),
])])])]);
session_id('preview-browser-session'); session_start(); $_SESSION['user_id'] = 'preview-admin'; session_write_close();`);
  execFileSync('php', [join(directory, 'seed.php')]);
  const port = await new Promise((resolve) => {
    const server = createServer();
    server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
  const origin = `http://127.0.0.1:${port}`;
  php = spawn('php', ['-S', `127.0.0.1:${port}`, join(directory, 'router.php')], { cwd: directory, stdio: 'ignore' });
  chrome = spawn(process.env.CHROME_BIN || 'google-chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
  for (let i = 0; i < 100 && !existsSync(join(profile, 'DevToolsActivePort')); i++) await delay(50);
  const chromePort = readFileSync(join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
  const targets = await fetch(`http://127.0.0.1:${chromePort}/json`).then((response) => response.json());
  socket = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  socket.onmessage = ({ data }) => {
    const message = JSON.parse(data);
    if (pending.has(message.id)) {
      const task = pending.get(message.id); pending.delete(message.id);
      if (message.error) task.reject(new Error(message.error.message)); else task.resolve(message.result);
    }
  };
  await command('Page.enable');
  await command('Network.enable');
  await command('Network.setCookie', { name: 'cometcms_admin', value: 'preview-browser-session', url: origin, path: '/' });
  const url = origin + '/cms/admin/site-preview/posts/gallery?workspace=default';
  await command('Page.navigate', { url });
  await waitFor('!!document.querySelector("[data-tabs-ready]")', 'preview scripts under the real CSP');
  const background = await evaluate('getComputedStyle(document.querySelector(".c-preview-bar")).backgroundColor');
  assert(background === 'rgb(255, 255, 255)', 'The user’s orange theme gives the admin bar its light surface');
  await evaluate('document.querySelector("[data-gallery-image]").click()');
  await waitFor('!!document.querySelector(".c-lightbox[open]")', 'gallery popup opens');
  await evaluate('document.querySelector(".c-lightbox-next").click()');
  assert(await evaluate('document.querySelector(".c-lightbox-counter").textContent') === '2 / 2', 'Gallery navigation works');
  await evaluate('document.querySelector(".c-lightbox-close").click()');
  await evaluate('document.querySelectorAll("[role=tab]")[1].click()');
  assert(await evaluate('document.querySelectorAll("[role=tab]")[1].getAttribute("aria-selected")') === 'true', 'Tab switching works');
  assert(await evaluate('document.querySelectorAll(".c-tabs-panel")[0].hidden'), 'Inactive tab content is hidden');
  await evaluate('document.querySelector("[data-theme-toggle]").click()');
  assert(await evaluate('document.documentElement.dataset.theme') === 'dark', 'The website theme toggle works');
  assert(await evaluate('getComputedStyle(document.querySelector(".c-preview-bar")).backgroundColor') === background, 'Website appearance does not change the admin theme');
  await evaluate('(() => { const input = document.querySelector(".c-search-input"); input.value = "Gallery"; input.dispatchEvent(new Event("input", { bubbles: true })); })()');
  await waitFor('!!document.querySelector(".c-search-results a")', 'draft search results');
  const hit = await evaluate('document.querySelector(".c-search-results a").href');
  assert(hit.startsWith(origin + '/cms/admin/site-preview?'), 'Search results stay in preview');
  await evaluate('document.querySelector(".c-search-results a").click()');
  await waitFor('location.search.includes("path=") && !!document.querySelector("[data-tabs-ready]")', 'navigation to the draft result');
  assert(await evaluate('!!document.querySelector(".c-preview-bar")'), 'Search navigation preserves the admin bar');
  const palettes = { blue: '13, 20, 36', green: '6, 40, 32', purple: '27, 19, 50', red: '44, 14, 16', cyan: '6, 36, 48', dark: '13, 16, 22' };
  for (const [theme, color] of Object.entries(palettes)) {
    await evaluate(`document.querySelector('.c-preview-bar').dataset.adminTheme = ${JSON.stringify(theme)}`);
    assert(await evaluate('getComputedStyle(document.querySelector(".c-preview-bar")).backgroundColor') === `rgb(${color})`, theme + ' admin palette renders');
  }
  console.log('Full-page preview browser checks passed (gallery, tabs, search navigation, website toggle, independent user themes, real CSP).');
} finally {
  socket?.close();
  const processes = [chrome, php].filter(Boolean);
  await Promise.all(processes.map((process) => new Promise((resolve) => { process.once('exit', resolve); if (!process.kill('SIGTERM')) resolve(); })));
  rmSync(directory, { recursive: true, force: true });
}
