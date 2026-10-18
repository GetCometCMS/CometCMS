// Run after npm run build: node tests/browser/typography.mjs
// Uses only Node, PHP and Chrome. All changes live in a temporary installation.
import { spawn } from 'node:child_process';
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';

const root = fileURLToPath(new URL('../../', import.meta.url));
const directory = mkdtempSync(join(tmpdir(), 'comet-typography-'));
const install = join(directory, 'site');
cpSync(join(root, 'dist'), install, { recursive: true });
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const port = await new Promise((resolve) => {
  const server = createServer();
  server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
});
const origin = `http://127.0.0.1:${port}`;
const php = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', install, join(install, 'router.php')], { stdio: 'ignore' });
const profile = join(directory, 'chrome');
const chrome = spawn(process.env.CHROME_BIN || 'google-chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
let socket;
const pending = new Map();
const errors = [], requests = [];
let sequence = 0;
async function command(method, params = {}) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timed out: ${method}`)); }, 90000);
    pending.set(id, { resolve: (result) => { clearTimeout(timer); resolve(result); }, reject });
    socket.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const result = await command('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  return result.result.value;
}
async function waitFor(expression, label, milliseconds = 20000) {
  const until = Date.now() + milliseconds;
  while (Date.now() < until) {
    if (await evaluate(expression)) return;
    await delay(150);
  }
  throw new Error(`Timed out waiting for ${label}: ${await evaluate('document.body.innerText.slice(-2000)')}`);
}
async function click(text, selector = 'button') {
  await evaluate(`(() => { const button = [...document.querySelectorAll(${JSON.stringify(selector)})].find((node) => node.textContent.trim() === ${JSON.stringify(text)}); if (!button) throw new Error('Missing button: ' + ${JSON.stringify(text)}); button.click(); })()`);
}
async function field(selector, value) {
  await evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); if (!el) throw new Error('Missing field'); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true })); })()`);
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
    if (message.id && pending.has(message.id)) {
      const task = pending.get(message.id); pending.delete(message.id);
      if (message.error) task.reject(new Error(message.error.message)); else task.resolve(message.result);
    }
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    if (message.method === 'Network.requestWillBeSent') requests.push(message.params.request.url);
  };
  await command('Page.enable');
  await command('Runtime.enable');
  await command('Network.enable');
  await command('Network.setBlockedURLs', { urls: ['*fonts.googleapis.com*', '*fonts.gstatic.com*', '*fontsource.org*', '*cdn.jsdelivr.net*'] });
  await command('Page.navigate', { url: `${origin}/admin` });
  await waitFor('!!document.querySelector(\'meta[name="csrf-token"]\')', 'admin shell');
  await evaluate(`(async () => {
    const response = await fetch('/admin/api/setup', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username: 'font-admin', password: 'font-test-password', workspace: 'Typography', workspace_slug: 'typography', starter_template: 'website' }) });
    if (!response.ok) throw new Error(await response.text());
    return true;
  })()`);
  await command('Page.navigate', { url: `${origin}/admin/page-builder/builder/region/header?settings=design` });
  await waitFor('!!document.querySelector("#design-font-body")', 'design panel');
  assert(await evaluate('document.querySelectorAll("#design-font-body optgroup").length') === 2, 'System and bundled groups are visible');
  await click('Typography');
  const beforeChoices = requests.filter((url) => url.includes('/_site/') && url.endsWith('.woff2')).length;
  assert(beforeChoices === 0, 'Opening the editor does not download the whole font library');
  await field('#design-font-body', 'fontsource-inter');
  await field('#design-font-heading', 'fontsource-lora');
  await waitFor('document.fonts.check(\'16px "comet-fontsource-inter"\', "Äöß")', 'local Inter specimen');
  await waitFor('[...document.querySelectorAll("iframe")].some((frame) => frame.contentDocument?.head.textContent.includes("comet-fontsource-lora"))', 'draft font preview');
  await click('Manage fonts');
  await waitFor('!!document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"]\')', 'font manager');
  await click('Upload', '[role="tab"]');
  await field('[role="dialog"][aria-labelledby^="font-manager-"] form input[maxlength="160"]', 'Browser Brand');
  const manifest = JSON.parse(readFileSync(join(root, 'cms/app/Site/assets/fonts/manifest.json'), 'utf8'));
  const latinFace = manifest.families[0].faces.find((face) => face.subset === 'latin' && face.style === 'normal');
  const bytes = readFileSync(join(root, 'cms/app/Site/assets/fonts/files', `${latinFace.hash}.woff2`)).toString('base64');
  await evaluate(`(() => {
    const input = document.querySelector('[role="dialog"][aria-labelledby^="font-manager-"] input[type="file"]');
    const file = new File([Uint8Array.from(atob(${JSON.stringify(bytes)}), (c) => c.charCodeAt(0))], 'brand.woff2', { type: 'font/woff2' });
    const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; input.dispatchEvent(new Event('change', { bubbles: true }));
    const checkbox = document.querySelector('[role="dialog"][aria-labelledby^="font-manager-"] input[type="checkbox"]'); checkbox.click();
  })()`);
  await click('Upload', '[role="dialog"][aria-labelledby^="font-manager-"] button[type="submit"]');
  await waitFor('document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"]\').innerText.includes("Browser Brand") && document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"]\').innerText.includes("The font library has been updated.")', 'custom upload');
  await evaluate('document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] button[aria-label="Close"]\').click()');
  assert(await evaluate('document.querySelector("#design-font-body").value') === 'fontsource-inter', 'Upload preserves unsaved body choice');
  assert(await evaluate('!!document.querySelector("#design-font-body optgroup[label=Installed]")'), 'Upload adds Installed group');
  const customId = await evaluate('[...document.querySelectorAll("#design-font-body option")].find((option) => option.textContent === "Browser Brand").value');
  await field('#design-font-body', customId);
  await waitFor(`document.fonts.check('400 16px "comet-${customId}"')`, 'uploaded font loads');
  await click('Manage fonts');
  await waitFor('!!document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"]\')', 'reopen library');
  assert(await evaluate('[...document.querySelectorAll(\'[role="dialog"][aria-labelledby^="font-manager-"] article\')].find((article) => article.textContent.includes("Browser Brand")).querySelector("button[data-action=remove]").disabled'), 'Current draft blocks removal');
  assert(await evaluate('[...document.querySelectorAll(\'[role="dialog"][aria-labelledby^="font-manager-"] article\')].filter((article) => article.querySelector("p[style*=font-family]")).length > 0'), 'Installed fonts show specimens without a click');
  if (!process.argv.includes('--offline')) {
    await click('Catalogue', '[role="tab"]');
    await waitFor('!!document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] input[placeholder="Search fonts"]\')', 'catalogue');
    await waitFor('!!document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] article[data-font-id]\')', 'catalogue load', 60000);
    await field('[role="dialog"][aria-labelledby^="font-manager-"] input[placeholder="Search fonts"]', 'Fira Sans');
    await waitFor('!!document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] article[data-font-id="fira-sans"]\')', 'catalogue search', 60000);
    await waitFor('document.fonts.check(\'16px "comet-preview-fira-sans"\') && !!document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] article[data-font-id="fira-sans"] span[style*="comet-preview-fira-sans"]\')', 'automatic catalogue preview', 60000);
    await evaluate('document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] article[data-font-id="fira-sans"] > button\').click()');
    await evaluate(`(() => { const greek = [...document.querySelectorAll('[role="dialog"][aria-labelledby^="font-manager-"] input[type=checkbox]')].find((input) => input.value === 'greek'); if (!greek) throw new Error('Greek subset is available'); greek.click(); })()`);
    await click('Install font', '[role="dialog"][aria-labelledby^="font-manager-"] button');
    await waitFor('document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"]\').innerText.includes("The font library has been updated.")', 'catalogue installation', 120000);
    await evaluate('[...document.querySelectorAll(\'[role="tab"]\')].find((tab) => tab.textContent.trim().startsWith("Installed")).click()');
    assert(await evaluate('document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"]\').innerText.includes("Fira Sans")'), 'Installed catalogue family appears');
  }
  await evaluate('document.querySelector(\'[role="dialog"][aria-labelledby^="font-manager-"] button[aria-label="Close"]\').click()');
  // Save through the same authenticated HTTP interface and verify public serving and cache headers.
  const result = await evaluate(`(async () => {
    const headers = { 'X-Comet-Workspace': 'typography', 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content };
    const siteResponse = await fetch('/admin/api/site', { headers }); const site = (await siteResponse.json()).data;
    headers['X-CSRF-Token'] = siteResponse.headers.get('X-CSRF-Token') || headers['X-CSRF-Token'];
    site.theme.fonts = { body: ${JSON.stringify(customId)}, heading: 'fontsource-lora' };
    const save = await fetch('/admin/api/site/theme', { method: 'PUT', headers, body: JSON.stringify(site.theme) });
    if (!save.ok) throw new Error(await save.text());
    const asset = site.font_families.find((font) => font.id === ${JSON.stringify(customId)}).faces[0].hash;
    const url = '/_site/typography/fonts/' + asset + '.woff2';
    const head = await fetch(url, { method: 'HEAD' });
    const cached = await fetch(url, { headers: { 'If-None-Match': head.headers.get('ETag') }, cache: 'no-store' });
    return { head: head.status, type: head.headers.get('Content-Type'), cached: cached.status };
  })()`);
  assert(result.head === 200 && result.type === 'font/woff2' && result.cached === 304, 'Font assets support HEAD, content type and ETag revalidation');
  await command('Page.navigate', { url: origin + '/' });
  await waitFor('document.readyState === "complete" && document.body.classList.contains("c-site")', 'public website');
  await waitFor(`document.fonts.check('400 16px "comet-${customId}"')`, 'public self-hosted font');
  await evaluate(`Promise.all([document.fonts.load('italic 400 16px "comet-fontsource-lato"', 'ÄÖÜ äöü ß'), document.fonts.load('700 16px "comet-fontsource-inter"', 'ÄÖÜ')]).then((faces) => { if (faces.some((group) => !group.length)) throw new Error('Static italic and variable weight faces load'); })`);
  if (!process.argv.includes('--offline')) {
    await evaluate(`document.fonts.load('400 16px "comet-fontsource-fira-sans"', 'Ελληνικά').then((faces) => { if (!faces.length) throw new Error('Greek font face loads'); })`);
  }
  assert(!requests.some((url) => /^https?:/.test(url) && /(?:fontsource\.org|jsdelivr\.net|fonts\.googleapis\.com|fonts\.gstatic\.com)/.test(url)), 'No browser font provider requests');
  assert(errors.length === 0, `No browser exceptions: ${errors.join('\n')}`);
  console.log('Typography browser checks passed: grouped pickers, unsaved previews, upload, draft removal guard, ' + (process.argv.includes('--offline') ? 'offline serving' : 'catalogue preview/install and Greek coverage') + ', static italics, variable weights, HEAD/ETag caching and no browser provider requests.');
} finally {
  socket?.close();
  chrome.kill('SIGTERM'); php.kill('SIGTERM');
  await delay(300);
  rmSync(directory, { recursive: true, force: true });
}
