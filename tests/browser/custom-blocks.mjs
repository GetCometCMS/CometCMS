// Run after npm run build: node tests/browser/custom-blocks.mjs
// Uses Node, PHP and Chrome. Only an isolated temporary installation is modified.
import { spawn, spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';

const root = fileURLToPath(new URL('../../', import.meta.url));
const directory = mkdtempSync(join(tmpdir(), 'comet-blocks-browser-'));
const install = join(directory, 'site');
cpSync(process.env.COMET_TEST_DIST || join(root, 'dist'), install, { recursive: true });
const example = join(root, 'examples/blocks/acme-marketing');
const zips = { v1: join(directory, 'acme-marketing.zip'), v2: join(directory, 'acme-marketing-2.zip') };
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const seed = spawnSync('php', [], { encoding: 'utf8', input: `<?php
require ${JSON.stringify(join(install, 'app/bootstrap.php'))};
(new \\CometCMS\\Workspaces\\WorkspaceContext())->ensure();
(new \\CometCMS\\Workspaces\\WorkspaceRepository())->all();
(new \\CometCMS\\Auth\\RoleRepository())->seed();
$user = (new \\CometCMS\\Auth\\UserRepository())->create('block-admin', 'block-test-password', 'admin');
(new \\CometCMS\\Content\\ContentTypeRepository())->save(['name' => 'home', 'label' => 'Home', 'fields' => []]);
\\CometCMS\\Content\\ContentRepository::make()->save('home', ['title' => 'Home', 'status' => 'published'], $user);
(new \\CometCMS\\Site\\SiteRepository())->saveSettings(['title' => 'Block example', 'homepage' => ['type' => 'home', 'entry' => 'home']]);
// Version 2 drops the feature block, which needs confirmation when updating.
foreach ([${JSON.stringify(zips.v1)} => false, ${JSON.stringify(zips.v2)} => true] as $target => $breaking) {
    $zip = new ZipArchive();
    $zip->open($target, ZipArchive::CREATE);
    foreach (\\CometCMS\\Site\\Blocks\\BlockPackage::readDirectory(${JSON.stringify(example)}) as $path => $bytes) {
        if ($breaking && str_starts_with($path, 'blocks/feature/')) continue;
        $zip->addFromString('acme-marketing/' . $path, $breaking && $path === 'comet-package.json' ? str_replace('"1.2.0"', '"2.0.0"', $bytes) : $bytes);
    }
    $zip->close();
}
echo (new \\CometCMS\\Workspaces\\WorkspaceContext())->slug();
` });
assert(seed.status === 0, seed.stderr || 'Seed installation');
const workspace = seed.stdout.trim();
const port = await new Promise((resolve) => {
  const server = createServer();
  server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
});
const origin = `http://127.0.0.1:${port}`;
const php = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', install, join(install, 'router.php')], { stdio: 'ignore' });
const profile = join(directory, 'chrome');
const chrome = spawn(process.env.CHROME_BIN || 'google-chrome', [
  '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking',
  '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], { stdio: 'ignore' });
let socket;
let sequence = 0;
const pending = new Map();
const errors = [];

async function command(method, params = {}) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timed out: ${method}`)); }, 30000);
    pending.set(id, { resolve: (result) => { clearTimeout(timer); resolve(result); }, reject: (error) => { clearTimeout(timer); reject(error); } });
    socket.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const result = await command('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  return result.result.value;
}
async function waitFor(expression, label) {
  const until = Date.now() + 20000;
  while (Date.now() < until) {
    if (await evaluate(expression)) return;
    await delay(100);
  }
  throw new Error(`Timed out: ${label}. ${await evaluate('document.body.innerText.slice(-2000)')}`);
}
async function field(selector, value) {
  await evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); if (!el) throw new Error('Missing field'); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event('input', { bubbles: true })); })()`);
}
async function click(text, selector = 'button') {
  await evaluate(`(() => { const button = [...document.querySelectorAll(${JSON.stringify(selector)})].find((el) => el.textContent.trim() === ${JSON.stringify(text)}); if (!button) throw new Error('Missing button: ' + ${JSON.stringify(text)}); button.click(); })()`);
}
/** Packages with JavaScript ask before installing. */
async function allowScripts() {
  await waitFor('!!document.querySelector(\'[role="alertdialog"]\') && document.body.innerText.includes("Allow JavaScript?")', 'script consent');
  assert(await evaluate('[...document.querySelectorAll(\'[role="alertdialog"]\')].some((dialog) => dialog.innerText.includes("Allow JavaScript?") && dialog.innerText.includes("acme/stats"))'), 'Consent names the blocks with scripts');
  await click('Install with JavaScript', '[role="alertdialog"] button');
  // Its overlay fades out; a drop before then would land on it.
  await waitFor('!document.querySelector(\'[role="alertdialog"]\')', 'script consent closed');
}
async function dropFiles(paths) {
  const point = await evaluate(`(() => { const el = document.querySelector('#block-drop-zone'); el.scrollIntoView({ block: 'center' }); const rect = el.getBoundingClientRect(); return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 }; })()`);
  const data = { items: [], files: paths, dragOperationsMask: 1 };
  for (const type of ['dragEnter', 'dragOver', 'drop']) {
    await command('Input.dispatchDragEvent', { type, ...point, data });
  }
}

try {
  const activePort = join(profile, 'DevToolsActivePort');
  for (let count = 0; count < 100 && !existsSync(activePort); count++) await delay(100);
  assert(existsSync(activePort), 'Chrome started');
  const chromePort = readFileSync(activePort, 'utf8').split('\n')[0];
  const targets = await fetch(`http://127.0.0.1:${chromePort}/json`).then((response) => response.json());
  socket = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  socket.onmessage = ({ data }) => {
    const message = JSON.parse(data);
    if (pending.has(message.id)) {
      const task = pending.get(message.id); pending.delete(message.id);
      if (message.error) task.reject(new Error(message.error.message)); else task.resolve(message.result);
    }
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
  };
  await command('Page.enable');
  await command('Runtime.enable');
  await command('Page.navigate', { url: `${origin}/admin` });
  await waitFor('!!document.querySelector("#login-username")', 'login');
  await field('#login-username', 'block-admin');
  await field('#login-password', 'block-test-password');
  await click('Sign in');
  await waitFor('location.pathname.endsWith("/dashboard")', 'dashboard');
  await command('Page.navigate', { url: `${origin}/admin/page-builder/blocks` });
  await waitFor('!!document.querySelector("#block-package")', 'package upload');
  await dropFiles([join(example, 'comet-package.json')]);
  await waitFor('document.body.innerText.includes("Choose a single .zip file.")', 'non-ZIP rejected before upload');
  await command('DOM.enable');
  const { root: documentNode } = await command('DOM.getDocument');
  const { nodeId } = await command('DOM.querySelector', { nodeId: documentNode.nodeId, selector: '#block-package' });
  await command('DOM.setFileInputFiles', { nodeId, files: [zips.v1] });
  await allowScripts();
  await waitFor('document.body.innerText.includes("Acme marketing blocks installed.")', 'example package installation');
  assert(await evaluate('document.body.innerText.includes("JavaScript")'), 'Inventory marks packages with scripts');
  assert(await evaluate('document.body.innerText.includes("acme/marketing") && document.body.innerText.includes("Pricing plan")'), 'Inventory lists the package and its blocks');
  const asset = await evaluate(`fetch('/_site/${workspace}/blocks/${createHash('sha256').update(readFileSync(join(example, 'assets/check.svg'))).digest('hex')}.svg').then((res) => ({ status: res.status, type: res.headers.get('content-type'), csp: res.headers.get('content-security-policy'), cache: res.headers.get('cache-control') }))`);
  assert(asset.status === 200 && asset.type === 'image/svg+xml' && asset.csp.includes('sandbox') && asset.cache.includes('immutable'), 'Package asset is served safely: ' + JSON.stringify(asset));
  await command('Page.navigate', { url: `${origin}/admin/page-builder/builder/layout/home/detail` });
  await waitFor('[...document.querySelectorAll("button")].some((el) => el.textContent.trim() === "Callout")', 'custom palette entry');
  await evaluate('document.querySelector(\'[role="dialog"][aria-labelledby^="builder-guide-"] button[aria-label="Collapse panel"]\')?.click()');
  await click('Callout');
  // Single-line text props are bindable and use the smart value editor; the multi-line message is a textarea.
  await waitFor('!!document.querySelector(\'textarea[id$="-props-message"]\')', 'generated inspector');
  await field('textarea[id$="-props-message"]', 'Custom blocks work');
  const previewDocument = '[...document.querySelectorAll("iframe")].map((el) => el.contentDocument).find((doc) => doc?.querySelector(".acme-callout"))';
  await waitFor(`(${previewDocument})?.querySelector('.acme-callout p')?.textContent === 'Custom blocks work'`, 'live custom block preview');
  const preview = await evaluate(`(() => { const doc = ${previewDocument}; const el = doc.querySelector('.acme-callout'); const copy = el.cloneNode(true); copy.querySelectorAll('[draggable]').forEach((node) => node.removeAttribute('draggable')); return { html: copy.outerHTML, border: doc.defaultView.getComputedStyle(el).borderInlineStartWidth, annotated: !!el.closest('[data-kind="block:acme/callout"]'), cssCount: doc.querySelectorAll('style[data-comet-blocks]').length }; })()`);
  assert(preview.border === '4px', 'Preview loads block CSS');
  assert(preview.annotated && preview.cssCount === 1, 'Builder annotations and one block stylesheet');
  assert(await evaluate('!document.querySelector("style[data-comet-blocks]")'), 'Package CSS stays out of admin parent');
  const icons = await evaluate(`(() => { const item = (label) => [...document.querySelectorAll("button")].find((el) => el.textContent.trim() === label); return { feature: !!item("Feature")?.querySelector('span[style*="mask"]'), card: !!item("Card")?.querySelector("svg") }; })()`);
  assert(icons.feature && icons.card, 'Palette shows file and named block icons: ' + JSON.stringify(icons));
  if (process.env.COMET_BLOCK_SCREENSHOT) {
    const palette = await command('Page.captureScreenshot', { format: 'png' });
    writeFileSync(process.env.COMET_BLOCK_SCREENSHOT.replace(/\.png$/, '-builder.png'), Buffer.from(palette.data, 'base64'));
  }
  await click('Stats');
  await waitFor(`!!(${previewDocument})?.querySelector('.acme-stats')`, 'stats block in preview');
  assert(await evaluate(`!(${previewDocument}).querySelector('[data-comet-block]') && ![...(${previewDocument}).querySelectorAll('script')].some((el) => el.textContent.includes('import mount'))`), 'Builder preview never loads block scripts');
  await click('Pricing plan');
  await waitFor('[...document.querySelectorAll("button")].some((el) => el.textContent.trim() === "Add item")', 'list editor');
  await click('Add item');
  await waitFor('!!document.querySelector(\'input[id$="-props-features-3-text"]\')', 'new list item fields');
  await field('input[id$="-props-features-3-text"]', 'Custom feature');
  await waitFor(`[...(${previewDocument})?.querySelectorAll('.acme-plan__feature') ?? []].map((el) => el.textContent).includes('Custom feature')`, 'list item in preview');
  // A selected slot block takes the next element inside it.
  await click('Card');
  await waitFor(`!!(${previewDocument})?.querySelector('.acme-card .c-placeholder-drop')`, 'empty card slot');
  await click('Heading');
  await waitFor(`!!(${previewDocument})?.querySelector('.acme-card__body .c-el-heading')`, 'heading placed in card slot');
  await click('Save');
  await waitFor('document.body.innerText.includes("Layout saved.") || [...document.querySelectorAll("button")].some((el) => el.textContent.trim() === "Save" && el.disabled)', 'layout saved');
  await command('Page.navigate', { url: `${origin}/` });
  await waitFor('!!document.querySelector(".acme-callout")', 'published custom block');
  const published = await evaluate(`({ html: document.querySelector('.acme-callout').outerHTML, border: getComputedStyle(document.querySelector('.acme-callout')).borderInlineStartWidth, annotated: !!document.querySelector('[data-node]') })`);
  assert(published.html === preview.html, 'Preview and published custom markup match: ' + JSON.stringify({ preview: preview.html, published: published.html }));
  assert(published.border === preview.border, 'Published CSS matches preview');
  assert(!published.annotated, 'Public output excludes editor annotations');
  assert(await evaluate(`!!document.querySelector('.acme-card__body .c-el-heading') && [...document.querySelectorAll('.acme-plan__feature')].some((el) => el.textContent === 'Custom feature')`), 'Published slot children and list items');
  await waitFor('!!document.querySelector(\'[data-comet-block="acme/stats"][data-mounted="true"]\')', 'block script mounted under the site CSP');
  const csp = await evaluate(`fetch('/').then((res) => res.headers.get('content-security-policy'))`);
  assert(/script-src 'sha256-[^']+' 127\.0\.0\.1:\d+\/_site\/[^ ]+\/blocks\/[a-f0-9]{64}\.js/.test(csp) && !csp.includes("'self' 'sha") && !/script-src[^;]*'unsafe/.test(csp), 'CSP allows exactly the block script: ' + csp);
  if (process.env.COMET_BLOCK_SCREENSHOT) {
    const { cssContentSize: size } = await command('Page.getLayoutMetrics');
    const screenshot = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: size.width, height: size.height, scale: 1 } });
    writeFileSync(process.env.COMET_BLOCK_SCREENSHOT, Buffer.from(screenshot.data, 'base64'));
  }
  await command('Page.navigate', { url: `${origin}/admin/page-builder/blocks` });
  await waitFor('!!document.querySelector(\'button[aria-label="Delete package: Acme marketing blocks"]\')', 'delete action');
  await click('Delete');
  await waitFor('!!document.querySelector(\'[role="alertdialog"]\')', 'delete confirmation');
  await click('Cancel', '[role="alertdialog"] button');
  assert(await evaluate('document.body.innerText.includes("acme/marketing")'), 'Cancel keeps installed package');
  await click('Delete');
  await waitFor('!!document.querySelector(\'[role="alertdialog"]\')', 'delete confirmation reopened');
  await click('Delete', '[role="alertdialog"] button');
  await waitFor('document.body.innerText.includes("Package deleted.")', 'package deleted');
  assert(await evaluate('document.body.innerText.includes("No block packages installed.")'), 'Deleted package leaves inventory');
  assert(await evaluate(`fetch('/').then((res) => res.text()).then((html) => !html.includes('Custom blocks work'))`), 'Deletion invalidates published output');
  await command('Page.navigate', { url: `${origin}/admin/page-builder/builder/layout/home/detail` });
  await waitFor('[...document.querySelectorAll("iframe")].some((frame) => frame.contentDocument?.body?.textContent.includes("Missing block: acme/callout"))', 'placed instance preserved as missing block');
  await command('Page.navigate', { url: `${origin}/admin/page-builder/blocks` });
  await waitFor('!!document.querySelector("#block-drop-zone")', 'drop area');
  await dropFiles([zips.v1]);
  await allowScripts();
  await waitFor('document.body.innerText.includes("Acme marketing blocks installed.")', 'ZIP drop installation');
  assert(await evaluate(`fetch('/').then((res) => res.text()).then((html) => html.includes('>Custom blocks work</p>'))`), 'Reinstall restores saved instance props');
  await dropFiles([zips.v2]);
  await waitFor('!!document.querySelector(\'[role="alertdialog"]\') && document.body.innerText.includes("acme/feature is removed.")', 'breaking update confirmation');
  await click('Update anyway', '[role="alertdialog"] button');
  await allowScripts();
  await waitFor('document.body.innerText.includes("updated from 1.2.0 to 2.0.0")', 'confirmed update');
  assert(errors.length === 0, errors.join('\n'));
  console.log('Custom blocks browser checks passed (ZIP picker/drop, script consent and CSP, asset serving, icons, list editing, slots, palette, preview, publish, deletion, cancel, reinstall and confirmed update).');
} finally {
  socket?.close();
  chrome.kill();
  php.kill();
  await delay(300);
  rmSync(directory, { recursive: true, force: true });
}
