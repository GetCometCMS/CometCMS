// Run with: node tests/browser/gallery-lightbox.mjs (requires Chrome/Chromium).
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const runtime = readFileSync(join(root, 'cms/app/Site/assets/gallery.js'), 'utf8') + '\ninitGalleryLightbox(document);';
const css = readFileSync(join(root, 'cms/app/Site/assets/site.css'), 'utf8');
const picture = (color) => 'data:image/svg+xml;base64,' + Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="400" height="600"><rect width="400" height="600" fill="${color}"/></svg>`).toString('base64');
const first = picture('teal'), second = picture('orange');
const gallery = (id, navigation, images) => `<div id="${id}" class="c-gallery" data-gallery-lightbox data-gallery-navigation="${navigation}" data-gallery-label="Image gallery" data-gallery-close="Close" data-gallery-previous="Previous image" data-gallery-next="Next image">`
  + images.map((src, index) => `<a data-gallery-image href="${src}" data-caption="Caption &lt;b&gt;${index}&lt;/b&gt;"><img src="${src}" alt="Image ${index}"></a>`).join('') + '</div>';
const checks = `
const assert = (ok, message) => { if (!ok) throw new Error(message); };
const tick = () => new Promise((resolve) => setTimeout(resolve, 20));
const click = (selector) => document.querySelector(selector).click();
const viewer = () => document.querySelector('.c-lightbox');
const arrow = (key) => viewer().dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
try {
  assert(!window.untrustedExecuted, 'CSP blocks untrusted scripts');
  click('#main a');
  assert(viewer().open, 'Click opens a native dialog');
  assert(viewer().matches(':modal'), 'Background becomes inert');
  assert(document.activeElement.className === 'c-lightbox-close', 'Focus starts on close');
  document.querySelector('#background').focus();
  assert(document.activeElement.className === 'c-lightbox-close', 'Focus stays inside the modal');
  assert(viewer().querySelector('img').src === document.querySelector('#main a').href, 'Original image opens');
  assert(viewer().querySelector('figcaption').textContent === 'Caption <b>0</b>', 'Captions are plain text');
  assert(!viewer().querySelector('figcaption b'), 'Caption markup never executes');
  click('.c-lightbox-next');
  assert(viewer().querySelector('img').alt === 'Image 1', 'Next advances');
  arrow('ArrowRight');
  assert(viewer().querySelector('img').alt === 'Image 0', 'Navigation wraps within this gallery');
  arrow('ArrowLeft');
  assert(viewer().querySelector('img').alt === 'Image 1', 'Keyboard previous works');
  assert(viewer().querySelector('.c-lightbox-counter').textContent === '2 / 2', 'Position updates');
  viewer().requestClose();
  await tick();
  assert(!viewer().open, 'Native close requests dismiss the dialog');
  assert(document.activeElement === document.querySelector('#main a'), 'Focus returns to the clicked image');
  click('#single a');
  assert(viewer().querySelector('.c-lightbox-next').hidden, 'Single image has no navigation');
  assert(viewer().querySelector('img').alt === 'Image 0', 'Galleries stay isolated');
  click('.c-lightbox-close');
  await tick();
  click('#no-navigation a');
  assert(viewer().querySelector('.c-lightbox-previous').hidden, 'Navigation can be disabled');
  arrow('ArrowRight');
  assert(viewer().querySelector('img').alt === 'Image 0', 'Disabled navigation also disables arrow keys');
  viewer().click();
  await tick();
  assert(!viewer().open, 'Clicking the backdrop closes');
  viewer().remove();
  click('#main a');
  assert(document.querySelectorAll('.c-lightbox').length === 1 && viewer().open, 'Viewer survives preview DOM updates without duplicate handlers');
  click('.c-lightbox-close');
  await tick();
  let modifiedPrevented;
  document.addEventListener('click', (event) => { modifiedPrevented = event.defaultPrevented; event.preventDefault(); }, { once: true });
  document.querySelector('#main a').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true }));
  assert(modifiedPrevented === false && !viewer().open, 'Modified clicks keep normal link behavior');
  document.body.dataset.testStatus = 'passed';
  document.querySelector('#result').textContent = 'Gallery modal browser checks passed';
} catch (error) {
  document.body.dataset.testStatus = 'failed';
  document.querySelector('#result').textContent = error.stack;
}
`;
const testScript = checks;
// Test the production module under its real hash, then run assertions in a second allowed module.
const hash = (source) => "'sha256-" + createHash('sha256').update(source).digest('base64') + "'";
const policy = `default-src 'none'; script-src ${hash(runtime)} ${hash(testScript)}; style-src 'unsafe-inline'; img-src data:`;
const directory = mkdtempSync(join(tmpdir(), 'cometcms-gallery-browser-'));
try {
  const file = join(directory, 'gallery.html');
  writeFileSync(file, `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${policy}"><style>${css}</style></head><body class="c-site"><button id="background">Background</button>${gallery('main', true, [first, second])}${gallery('single', true, [second])}${gallery('no-navigation', false, [first, second])}<pre id="result"></pre><script>window.untrustedExecuted = true;</script><script type="module">${runtime}</script><script type="module">${testScript}</script></body></html>`);
  const result = spawnSync(process.env.CHROME_BIN || 'google-chrome', [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking',
    `--user-data-dir=${join(directory, 'profile')}`, '--dump-dom', '--virtual-time-budget=1500', pathToFileURL(file).href,
  ], { encoding: 'utf8', timeout: 45000, maxBuffer: 2 * 1024 * 1024 });
  if (result.error || result.status !== 0 || !result.stdout.includes('data-test-status="passed"')) {
    throw new Error(result.error?.message || result.stdout.match(/<pre id="result">([\s\S]*?)<\/pre>/)?.[1] || result.stderr);
  }
  console.log('Gallery modal browser checks passed (native dialog, navigation, focus, captions, CSP, preview updates).');
} finally {
  rmSync(directory, { recursive: true, force: true });
}
