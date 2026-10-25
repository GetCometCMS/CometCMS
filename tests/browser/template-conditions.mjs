// Run with: node tests/browser/template-conditions.mjs (requires Chrome/Chromium).
// Exercises the real template editor with in-memory API data only.
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const entry = `
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import Panel from '${root}web/src/components/site/SiteTemplatesPanel.vue';
import { useSiteStore } from '${root}web/src/stores/site.js';
import { api } from '${root}web/src/api/index.js';
import { defaultTemplate } from '${root}web/src/composables/templates.js';
import { setLocale } from '${root}web/src/i18n/index.js';
import '${root}web/src/style.css';
window.setLocale = setLocale;
window.entryReads = [];
window.failEntries = false;
api.content.list = async (type, { offset, limit }) => {
  window.entryReads.push({ type, offset });
  if (window.failEntries) throw new Error('Entries failed to load');
  return { data: Array.from({ length: Math.min(limit, 205 - offset) }, (_, index) => ({
    uid: type + '-' + (offset + index), title: 'Entry ' + (offset + index), slug: 'slug-' + (offset + index),
  })), meta: { total: 205 } };
};
api.site.components = async () => ({ data: [] });
api.site.layout = async () => ({ data: { nodes: [], types: [], settings: {} } });
const pinia = createPinia();
const site = useSiteStore(pinia);
window.site = site;
const chosen = { id: 'chosen', name: 'Chosen entries', header: 'default', footer: 'default', conditions: [{ field: 'uid', op: 'equals', value: [] }] };
site.data = {
  types: ['posts', 'pages'].map(name => ({ name, label: name, fields: { title: { type: 'text', label: 'Title' } } })),
  layouts: { posts: { detail: true }, pages: { detail: true } },
};
site.draft = { templates: Object.fromEntries(['posts', 'pages'].map(name => [name, { detail: [structuredClone(chosen), defaultTemplate()] }])) };
const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }] });
await router.push('/page-builder/templates?type=posts&template=chosen');
createApp({ render: () => h('main', { class: 'p-4' }, [h(Panel)]) }).use(pinia).use(router).mount('#app');
`;

let server, browser;
try {
  server = await createServer({
    configFile: false, root: join(root, 'web'), base: '/admin/', logLevel: 'error',
    css: { postcss: { plugins: [tailwindcss({ ...loadConfig(join(root, 'web/tailwind.config.js')), content: [join(root, 'web/src/**/*.{vue,js}')] })] } },
    plugins: [vue(), {
      name: 'template-conditions-fixture',
      resolveId(id) { if (id === 'virtual:template-conditions') return '\0virtual:template-conditions'; },
      load(id) { if (id === '\0virtual:template-conditions') return entry; },
      configureServer(server) {
        server.middlewares.use(async (req, res, next) => {
          if (!req.url?.endsWith('/template-conditions')) return next();
          const html = await server.transformIndexHtml('/admin/template-conditions', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/@id/__x00__virtual:template-conditions"></script></body></html>');
          res.setHeader('Content-Type', 'text/html'); res.end(html);
        });
      },
    }], server: { host: '127.0.0.1', port: 0 },
  });
  await server.listen();
  browser = await chromium.launch({ executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(() => localStorage.setItem('cometcms-admin-locale', 'en'));
  await page.goto(`http://127.0.0.1:${server.httpServer.address().port}/admin/template-conditions`);
  const picker = page.getByRole('combobox', { name: 'Select entries…', exact: true });
  await picker.waitFor();
  await page.waitForFunction(() => window.entryReads.length === 3);
  assert.deepEqual(await page.evaluate(() => window.entryReads.map(read => read.offset)), [0, 100, 200]);
  assert.equal(await page.getByRole('combobox', { name: 'Comparison', exact: true }).inputValue(), 'equals');
  assert.equal(await page.getByRole('combobox', { name: 'Comparison', exact: true }).textContent(), 'is equal to');

  // Search and pick an entry beyond both the first and second result pages.
  await picker.click();
  await page.getByRole('textbox', { name: 'Search options' }).fill('Entry 204');
  await page.getByRole('option', { name: 'Entry 204', exact: true }).click();
  await page.getByRole('textbox', { name: 'Search options' }).fill('Entry 1');
  await page.getByRole('option', { name: 'Entry 1', exact: true }).click();
  await page.getByRole('textbox', { name: 'Search options' }).press('Escape');
  assert.deepEqual(await page.evaluate(() => window.site.draft.templates.posts.detail[0].conditions[0]), { field: 'uid', op: 'equals', value: ['posts-204', 'posts-1'] });
  await page.getByRole('button', { name: 'Remove Entry 204', exact: true }).click();
  assert.deepEqual(await page.evaluate(() => window.site.draft.templates.posts.detail[0].conditions[0].value), ['posts-1']);

  // Switching field kinds clears incompatible values in both directions.
  await page.getByRole('combobox', { name: 'Field', exact: true }).selectOption('title');
  await page.getByRole('textbox', { name: 'Value', exact: true }).fill('Something');
  await page.getByRole('textbox', { name: 'Value', exact: true }).press('Tab');
  await page.getByRole('combobox', { name: 'Field', exact: true }).selectOption('uid');
  assert.deepEqual(await page.evaluate(() => window.site.draft.templates.posts.detail[0].conditions[0].value), []);

  // A different content type offers only its own entries and preserves the posts draft.
  await page.getByRole('combobox', { name: 'Content type', exact: true }).selectOption('pages');
  await picker.click();
  await page.getByRole('textbox', { name: 'Search options' }).fill('Entry 204');
  await page.getByRole('option', { name: 'Entry 204', exact: true }).click();
  await page.getByRole('textbox', { name: 'Search options' }).press('Escape');
  assert.deepEqual(await page.evaluate(() => window.site.draft.templates.pages.detail[0].conditions[0].value), ['pages-204']);
  assert.deepEqual(await page.evaluate(() => window.site.draft.templates.posts.detail[0].conditions[0].value), []);

  await page.evaluate(() => window.setLocale('de'));
  assert.equal(await page.getByRole('combobox', { name: 'Feld', exact: true }).locator('option:checked').textContent(), 'Eintrag');
  assert.equal(await page.getByRole('combobox', { name: 'Vergleich', exact: true }).textContent(), 'ist gleich');
  for (const width of [1280, 360]) {
    await page.setViewportSize({ width, height: 1000 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `No overflow at ${width}px`);
  }
  await page.evaluate(() => { window.setLocale('en'); window.failEntries = true; });
  await page.getByRole('combobox', { name: 'Content type', exact: true }).selectOption('posts');
  await page.getByRole('alert').filter({ hasText: 'Entries failed to load' }).waitFor();
  await page.evaluate(() => { window.failEntries = false; });
  await page.getByRole('button', { name: 'Try again', exact: true }).click();
  await picker.click();
  await page.getByRole('textbox', { name: 'Search options' }).fill('Entry 204');
  await page.getByRole('option', { name: 'Entry 204', exact: true }).waitFor();
  assert.deepEqual(errors, []);
  console.log('Template entry conditions passed: multi-selection, pagination, field changes, type isolation, localization, responsive layout, and retry.');
} finally {
  await browser?.close();
  await server?.close();
}
