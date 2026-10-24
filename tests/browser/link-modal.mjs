// Run with: node tests/browser/link-modal.mjs (requires PHP and Chrome/Chromium).
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { cpSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const directory = mkdtempSync(join(tmpdir(), 'cometcms-link-modal-'));
let php, browser;
try {
  for (const path of ['app', 'config', 'index.php']) cpSync(join(root, 'cms', path), join(directory, path), { recursive: true });
  writeFileSync(join(directory, 'router.php'), `<?php
$_SERVER['SCRIPT_NAME'] = str_starts_with($_SERVER['REQUEST_URI'], '/cms/') ? '/cms/index.php' : '/index.php';
require __DIR__ . '/index.php';`);
  writeFileSync(join(directory, 'seed.php'), `<?php
require __DIR__ . '/app/bootstrap.php';
(new \\CometCMS\\Auth\\UserRepository())->create('modal-admin', 'secret-password', 'admin');
(new \\CometCMS\\Workspaces\\WorkspaceRepository())->all();
(new \\CometCMS\\Content\\ContentTypeRepository())->save(['name' => 'posts']);
$content = \\CometCMS\\Content\\ContentRepository::make();
$content->save('posts', ['title' => 'Source page', 'slug' => 'source', 'status' => 'published'], ['id' => 'modal-admin']);
$target = $content->save('posts', ['title' => 'Target page', 'slug' => 'target', 'status' => 'published'], ['id' => 'modal-admin']);
$site = new \\CometCMS\\Site\\SiteRepository();
$site->saveSettings(['homepage' => ['type' => 'posts', 'entry' => ''], 'routes' => ['posts' => ['enabled' => true, 'pattern' => '/posts/{slug}', 'index_enabled' => true, 'index_path' => '/posts']]]);
$node = \\CometCMS\\Site\\LayoutSchema::node(...);
$site->saveLayout('posts', 'detail', [$node('section', [], [], [$node('row', [], [], [$node('column', [], [], [
    $node('heading', ['text' => \\CometCMS\\Site\\SmartValue::reference('title')]),
    $node('button', ['label' => 'Entry modal', 'link' => ['type' => 'entry', 'collection' => 'posts', 'entry' => $target['uid'], 'anchor' => 'main', 'open_modal' => true]]),
    $node('button', ['label' => 'List modal', 'link' => ['type' => 'index', 'collection' => 'posts', 'open_modal' => true]]),
    $node('button', ['label' => 'Home modal', 'link' => ['type' => 'home', 'open_modal' => true]]),
    $node('button', ['label' => 'Current modal', 'link' => ['type' => 'current', 'open_modal' => true]]),
    $node('button', ['label' => 'Normal internal', 'link' => ['type' => 'entry', 'collection' => 'posts', 'entry' => $target['uid']]]),
    $node('button', ['label' => 'External link', 'link' => ['type' => 'url', 'url' => 'https://example.org/other', 'open_modal' => true, 'new_tab' => true]]),
    $node('tabs', [], [], [$node('tab', ['title' => 'First'], [], [$node('heading', ['text' => 'First panel'])]), $node('tab', ['title' => 'Second'], [], [$node('heading', ['text' => 'Second panel'])])]),
])])])]);
session_id('modal-browser-session'); session_start(); $_SESSION['user_id'] = 'modal-admin'; session_write_close();`);
  execFileSync('php', [join(directory, 'seed.php')]);
  const port = await new Promise((resolve) => {
    const server = createServer();
    server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
  const origin = `http://127.0.0.1:${port}`;
  php = spawn('php', ['-S', `127.0.0.1:${port}`, join(directory, 'router.php')], { cwd: directory, stdio: 'ignore' });
  browser = await chromium.launch({ executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  page.setDefaultTimeout(10000);
  for (const base of ['', '/cms']) {
    const source = origin + base + '/posts/source';
    const response = await page.goto(source);
    assert.equal(response.status(), 200);
    assert.match(response.headers()['content-security-policy'], /frame-src 'self'/);
    assert.match(response.headers()['content-security-policy'], /script-src 'sha256-/);
    await page.evaluate(() => { window.violations = []; document.addEventListener('securitypolicyviolation', (event) => window.violations.push(event.violatedDirective)); });
    const opener = page.getByRole('link', { name: 'Entry modal', exact: true });
    await opener.click();
    const dialog = page.getByRole('dialog', { name: 'Entry modal' });
    await dialog.waitFor({ state: 'visible' });
    assert(await dialog.evaluate((element) => element.matches(':modal')));
    assert.equal(page.url(), source, 'Opening a modal leaves the parent URL unchanged');
    const content = page.frameLocator('.c-link-modal-frame');
    await content.getByRole('heading', { name: 'Target page', exact: true }).waitFor();
    assert.equal(await dialog.locator('iframe').getAttribute('src'), origin + base + '/posts/target#main');
    await content.getByRole('tab', { name: 'Second', exact: true }).click();
    assert.equal(await content.getByRole('tab', { name: 'Second', exact: true }).getAttribute('aria-selected'), 'true', 'Destination scripts work under their CSP');
    await page.keyboard.press('Escape');
    await dialog.waitFor({ state: 'hidden' });
    assert(await opener.evaluate((element) => element === document.activeElement), 'Escape from the frame restores focus');
    assert.equal(await page.locator('.c-link-modal-frame').getAttribute('src'), 'about:blank');

    for (const label of ['List modal', 'Home modal', 'Current modal']) {
      await page.getByRole('link', { name: label, exact: true }).click();
      await page.locator('.c-link-modal[open]').waitFor();
      await content.locator('main').waitFor();
      await page.getByRole('button', { name: 'Close', exact: true }).click();
      await page.locator('.c-link-modal[open]').waitFor({ state: 'hidden' });
    }
    const external = page.getByRole('link', { name: 'External link', exact: true });
    assert.equal(await external.getAttribute('data-link-modal'), null);
    assert.equal(await external.getAttribute('target'), '_blank');
    assert.equal(await external.getAttribute('href'), 'https://example.org/other');
    const modified = await opener.evaluate((element) => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true });
      let prevented;
      document.addEventListener('click', (event) => { prevented = event.defaultPrevented; event.preventDefault(); }, { once: true });
      element.dispatchEvent(event);
      return prevented;
    });
    assert.equal(modified, false, 'Modified clicks retain browser navigation');
    assert.equal(await page.locator('.c-link-modal[open]').count(), 0);
    // Defend against an external URL accidentally receiving a trigger attribute.
    assert.equal(await external.evaluate((element) => {
      element.dataset.linkModal = '';
      let prevented;
      document.addEventListener('click', (event) => { prevented = event.defaultPrevented; event.preventDefault(); }, { once: true });
      element.click();
      return prevented;
    }), false);
    assert.deepEqual(await page.evaluate(() => window.violations), []);

    await page.setViewportSize({ width: 375, height: 667 });
    await opener.click();
    const bounds = await page.locator('.c-link-modal').boundingBox();
    assert(bounds.width <= 375 && bounds.height <= 667, 'Modal fits narrow screens');
    await page.mouse.click(1, 1);
    await page.locator('.c-link-modal[open]').waitFor({ state: 'hidden' });
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.getByRole('link', { name: 'Normal internal', exact: true }).click();
    await page.waitForURL(origin + base + '/posts/target');
  }

  await page.context().addCookies([{ name: 'cometcms_admin', value: 'modal-browser-session', url: origin }]);
  await page.goto(origin + '/cms/admin/site-preview/posts/source?workspace=default');
  await page.getByRole('link', { name: 'Entry modal', exact: true }).click();
  await page.frameLocator('.c-link-modal-frame').getByRole('heading', { name: 'Target page', exact: true }).waitFor();
  assert.match(await page.locator('.c-link-modal-frame').getAttribute('src'), /\/cms\/admin\/site-preview\?/);
  await page.getByRole('button', { name: 'Close', exact: true }).click();
  console.log('Link modal browser checks passed (internal destinations, external links, focus, Escape, backdrop, mobile, nested interactions, root/subdirectory installs, preview and real CSP).');
} finally {
  await browser?.close();
  if (php) await new Promise((resolve) => { php.once('exit', resolve); if (!php.kill('SIGTERM')) resolve(); });
  rmSync(directory, { recursive: true, force: true });
}
