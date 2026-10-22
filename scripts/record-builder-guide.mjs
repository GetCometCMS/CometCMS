// Record real builder interactions in a disposable installation.
// Requires PHP, FFmpeg and Chrome (or: npx playwright install chromium).
import { chromium } from 'playwright';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';

const root = fileURLToPath(new URL('../', import.meta.url));
const temporary = mkdtempSync(join(tmpdir(), 'comet-builder-guide-'));
const install = join(temporary, 'site');
const output = join(root, 'web/src/assets/builder-guide');
const size = { width: 1440, height: 900 };
const run = (command, args) => {
  const result = spawnSync(command, args, { cwd: root, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`${command} failed (${result.status})`);
};
let php, browser;
try {
  run(process.execPath, ['scripts/build.mjs', 'full', install]);
  const port = await new Promise((resolve) => {
    const server = createServer();
    server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
  const origin = `http://127.0.0.1:${port}`;
  php = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', install, join(install, 'router.php')], { stdio: 'ignore' });
  for (let attempt = 0; attempt < 50; attempt++) {
    try { await fetch(`${origin}/admin`); break; } catch { await new Promise((resolve) => setTimeout(resolve, 100)); }
  }
  browser = await chromium.launch({
    ...(process.env.CHROME_BIN ? { executablePath: process.env.CHROME_BIN } : {}),
    args: ['--no-sandbox'],
  });
  const setup = await browser.newContext({ viewport: size, locale: 'en-GB' });
  const setupPage = await setup.newPage();
  await setupPage.goto(`${origin}/admin`);
  await setupPage.locator('#setup-workspace').fill('Builder Studio');
  await setupPage.locator('#setup-workspace-slug').fill('builder-studio');
  await setupPage.getByRole('button', { name: 'Continue', exact: true }).click();
  await setupPage.locator('#setup-username').fill('demo-editor');
  await setupPage.locator('#setup-password').fill('builder-demo-local-2026');
  await setupPage.getByRole('button', { name: 'Create admin account', exact: true }).click();
  await setupPage.waitForURL('**/dashboard', { timeout: 180000 });
  const state = await setup.storageState();
  await setup.close();
  const entry = JSON.parse(readFileSync(join(install, 'storage/workspaces/builder-studio/content/pages/about.json')));
  const context = await browser.newContext({ viewport: size, locale: 'en-GB', storageState: state, recordVideo: { dir: temporary, size } });
  await context.addInitScript(() => {
    localStorage.setItem('cometcms-builder-guide-seen', '1');
    localStorage.setItem('cometcms-admin-locale', 'en');
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const video = page.video();
  const recordingStart = Date.now();
  await page.goto(`${origin}/admin/page-builder/builder/page/pages/${entry.uid}`);
  const canvas = page.frameLocator('iframe');
  await canvas.locator('[data-kind="heading"]').first().waitFor();
  await page.evaluate(() => {
    const pointer = document.createElement('div');
    pointer.id = 'demo-pointer';
    pointer.style.cssText = 'position:fixed;left:710px;top:440px;width:22px;height:28px;z-index:2147483647;pointer-events:none;filter:drop-shadow(0 1px 2px #0008);transition:left .28s ease,top .28s ease';
    pointer.innerHTML = '<svg viewBox="0 0 22 28" xmlns="http://www.w3.org/2000/svg"><path d="M2 2v21l5-5 4 8 4-2-4-8h8Z" fill="#172554" stroke="white" stroke-width="2" stroke-linejoin="round"/></svg>';
    document.body.append(pointer);
  });
  const pause = (ms = 1000) => page.waitForTimeout(ms);
  async function point(locator) {
    await locator.scrollIntoViewIfNeeded();
    const box = await locator.boundingBox();
    if (!box) throw new Error('Recording target is not visible');
    const position = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
    await page.evaluate(({ x, y }) => {
      const pointer = document.querySelector('#demo-pointer');
      pointer.style.left = `${x}px`; pointer.style.top = `${y}px`;
    }, position);
    await page.mouse.move(position.x, position.y, { steps: 12 });
    await pause(450);
  }
  async function click(locator, double = false) {
    await point(locator);
    if (double) await locator.dblclick(); else await locator.click();
    await page.evaluate(() => document.querySelector('#demo-pointer').animate([
      { filter: 'drop-shadow(0 0 8px #60a5fa)', transform: 'scale(.85)' },
      { filter: 'drop-shadow(0 1px 2px #0008)', transform: 'scale(1)' },
    ], { duration: 450 }));
    await pause();
  }
  const button = (name) => page.getByRole('button', { name, exact: true });
  const clips = [];
  let start;
  function begin(name) { start = (Date.now() - recordingStart) / 1000; console.log(`Recording ${name}`); }
  function end(name) { clips.push({ name, start, duration: (Date.now() - recordingStart) / 1000 - start }); }
  await pause(1500);

  begin('add-elements');
  // The palette starts open. Close and reopen it to demonstrate the rail.
  await click(button('Add'));
  await click(button('Add'));
  await click(button('2 columns'));
  const column = canvas.locator('[data-kind="column"]').last();
  await click(column);
  await click(button('Heading'));
  await canvas.locator('[data-kind="heading"]').last().waitFor();
  await pause(1500);
  end('add-elements');

  begin('edit-content');
  const heading = canvas.locator('[data-kind="heading"]').last();
  await click(heading);
  await click(heading, true);
  const editable = canvas.locator('[contenteditable]:not([contenteditable="false"])');
  await editable.press('ControlOrMeta+A');
  await editable.pressSequentially('Create something great', { delay: 85 });
  await editable.press('Enter');
  await pause(1500);
  await click(page.getByRole('tab', { name: 'Style', exact: true }));
  await pause(1800);
  if (!await heading.innerText().then((text) => text.includes('Create something great'))) throw new Error('Inline edit did not persist');
  end('edit-content');

  // Remove selection and panels so device differences are easy to see.
  await page.keyboard.press('Escape');
  await click(button('Add'));
  await click(button('Inspector'));
  await pause();
  begin('responsive-preview');
  await point(button('Desktop'));
  await pause(1500);
  for (const device of ['Tablet', 'Mobile', 'Desktop']) {
    await click(button(device));
    await pause(2000);
    if (await button(device).getAttribute('aria-pressed') !== 'true') throw new Error(`${device} preview did not activate`);
  }
  end('responsive-preview');

  begin('save-history');
  const saved = page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().includes('/admin/api/site/'));
  await click(button('Save'));
  if (!(await saved).ok()) throw new Error('Demo save failed');
  await pause(1200);
  await click(button('History'));
  await button('Load into editor').first().waitFor();
  await pause(2000);
  await click(button('Load into editor').first());
  await page.getByRole('button', { name: 'Cancel', exact: true }).waitFor();
  await pause(1500);
  await click(button('Cancel'));
  await pause(1200);
  end('save-history');
  if (errors.length) throw new Error(errors.join('\n'));

  await context.close();
  const raw = await video.path();
  mkdirSync(output, { recursive: true });
  for (const clip of clips) {
    const target = join(output, `${clip.name}.mp4`);
    run('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-ss', String(clip.start), '-i', raw, '-t', String(clip.duration), '-an', '-r', '24', '-c:v', 'libx264', '-preset', 'slow', '-crf', '23', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', target]);
    run('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-ss', String(Math.min(clip.duration - 1, clip.name === 'save-history' ? 6 : clip.duration - 2)), '-i', target, '-frames:v', '1', '-vf', 'scale=960:-1', '-c:v', 'libwebp', '-quality', '82', join(output, `${clip.name}.webp`)]);
    console.log(`${clip.name}: ${clip.duration.toFixed(1)}s`);
  }
} finally {
  await browser?.close();
  php?.kill();
  rmSync(temporary, { recursive: true, force: true });
}
