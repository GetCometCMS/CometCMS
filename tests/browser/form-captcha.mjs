// Run with: node tests/browser/form-captcha.mjs (requires PHP and Chrome/Chromium).
// Provider scripts are intercepted so these checks stay offline and deterministic.
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { cpSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const directory = mkdtempSync(join(tmpdir(), 'cometcms-captcha-'));
let php, browser;
try {
  for (const path of ['app', 'config', 'index.php']) cpSync(join(root, 'cms', path), join(directory, path), { recursive: true });
  const configPath = join(directory, 'config/config.php');
  const config = readFileSync(configPath, 'utf8').replaceAll(/getenv\('COMET_(TURNSTILE|RECAPTCHA(?:_V3)?)_(SITE|SECRET)_KEY'\) \?: ''/g, (_match, provider, key) => `'${provider.toLowerCase()}-${key.toLowerCase()}'`);
  writeFileSync(configPath, config);
  writeFileSync(join(directory, 'router.php'), `<?php
$_SERVER['SCRIPT_NAME'] = str_starts_with($_SERVER['REQUEST_URI'], '/cms/') ? '/cms/index.php' : '/index.php';
require __DIR__ . '/index.php';`);
  writeFileSync(join(directory, 'seed.php'), `<?php
require __DIR__ . '/app/bootstrap.php';
(new \\CometCMS\\Workspaces\\WorkspaceRepository())->all();
$types = new \\CometCMS\\Content\\ContentTypeRepository();
$types->save(['name' => 'pages']);
$types->save(['name' => 'scorepages']);
foreach (['turnstile', 'recaptcha', 'recaptcha_v3', 'none'] as $provider) {
    $types->save(['name' => $provider, 'visibility' => 'private', 'allow_submissions' => true,
        'submission_settings' => ['captcha' => $provider], 'fields' => ['email' => ['type' => 'text', 'required' => true]]]);
}
$content = \\CometCMS\\Content\\ContentRepository::make();
$content->save('pages', ['title' => 'Contact', 'slug' => 'contact', 'status' => 'published'], ['id' => null]);
$content->save('scorepages', ['title' => 'Contact', 'slug' => 'contact', 'status' => 'published'], ['id' => null]);
$site = new \\CometCMS\\Site\\SiteRepository();
$site->saveSettings(['enabled' => true, 'routes' => ['pages' => ['enabled' => true, 'pattern' => '/pages/{slug}'], 'scorepages' => ['enabled' => true, 'pattern' => '/score/{slug}']]]);
$forms = array_column((new \\CometCMS\\Site\\FormRepository($site))->all(), null, 'type');
$node = \\CometCMS\\Site\\LayoutSchema::node(...);
$site->saveLayout('pages', 'detail', [$node('section', [], [], [$node('row', [], [], [$node('column', [], [], [
    $node('form', ['form' => $forms['turnstile']['id']]),
    $node('form', ['form' => $forms['turnstile']['id']]),
    $node('form', ['form' => $forms['recaptcha']['id']]),
    $node('form', ['form' => $forms['none']['id']]),
    $node('form', ['form' => $forms['recaptcha_v3']['id']]),
    $node('form', ['form' => $forms['recaptcha_v3']['id']]),
])])])]);
$site->saveLayout('scorepages', 'detail', [$node('section', [], [], [$node('row', [], [], [$node('column', [], [], [
    $node('form', ['form' => $forms['recaptcha_v3']['id']]),
])])])]);`);
  execFileSync('php', [join(directory, 'seed.php')]);
  const port = await new Promise((resolve) => {
    const server = createServer();
    server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
  const origin = `http://127.0.0.1:${port}`;
  php = spawn('php', ['-S', `127.0.0.1:${port}`, join(directory, 'router.php')], { cwd: directory, stdio: 'ignore' });
  browser = await chromium.launch({ executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.clock.install();
  page.setDefaultTimeout(10000);
  await page.addInitScript(() => {
    window.violations = [];
    window.addEventListener('securitypolicyviolation', (event) => window.violations.push(event.violatedDirective));
  });
  let failProvider = false;
  const requests = [];
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.route(/https:\/\/(challenges\.cloudflare\.com|www\.google\.com)\/.*api\.js\?/, async (route) => {
    const url = new URL(route.request().url());
    requests.push(url.hostname);
    if (failProvider) return route.abort();
    const turnstile = url.hostname === 'challenges.cloudflare.com';
    await route.fulfill({ contentType: 'text/javascript', body: `
      (() => {
      window.captchaMocks ||= { turnstile: [], recaptcha: [] };
      window.captchaExecutions ||= [];
      const widgets = window.captchaMocks.${turnstile ? 'turnstile' : 'recaptcha'};
      window.${turnstile ? 'turnstile' : 'grecaptcha'} = {
        render(element, options) {
          const input = document.createElement('input');
          input.type = 'hidden'; input.name = '${turnstile ? 'cf-turnstile-response' : 'g-recaptcha-response'}'; element.append(input);
          const id = widgets.length;
          widgets.push({ element, options, input, solve() { input.value = 'token-' + id; options.callback(input.value); } });
          return id;
        },
        reset(id) { widgets[id].input.value = ''; if (widgets[id].options.size !== 'invisible') widgets[id].options['expired-callback'](); },
        execute(id, options) {
          return new Promise((resolve, reject) => window.captchaExecutions.push({ id, options,
            resolve(token) { widgets[id].input.value = token; resolve(token); }, reject,
          }));
        },
      };
      window.${url.searchParams.get('onload')}();
      })();
    ` });
  });

  for (const base of ['', '/cms']) {
    requests.length = 0;
    const response = await page.goto(origin + base + '/pages/contact');
    assert.equal(response.status(), 200);
    const policy = response.headers()['content-security-policy'];
    assert.match(policy, /script-src 'sha256-/);
    assert.match(policy, /https:\/\/challenges\.cloudflare\.com/);
    assert.match(policy, /https:\/\/www\.gstatic\.com\/recaptcha\//);
    assert.match(policy, /connect-src 'self' https:\/\/challenges\.cloudflare\.com https:\/\/www\.google\.com\/recaptcha\//);
    await page.waitForFunction(() => window.captchaMocks?.turnstile.length === 2 && window.captchaMocks?.recaptcha.length === 3);
    assert.deepEqual(requests.sort(), ['challenges.cloudflare.com', 'www.google.com']);
    const outcome = await page.evaluate(() => {
      const forms = [...document.querySelectorAll('form.c-form')].slice(0, 4);
      const submit = (form) => !form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      const widgets = window.captchaMocks;
      const before = forms.map(submit);
      widgets.turnstile[0].solve();
      widgets.recaptcha[0].solve();
      const solved = forms.map(submit);
      const tokens = forms.map((form) => [...new FormData(form)].filter(([key]) => key.endsWith('-response')));
      widgets.turnstile[0].options['expired-callback']();
      const expired = submit(forms[0]);
      widgets.recaptcha[0].options['error-callback']();
      const failed = submit(forms[2]);
      const error = forms[2].querySelector('[data-captcha-status]').textContent;
      forms[0].reset();
      return { before, solved, tokens, expired, failed, error };
    });
    assert.deepEqual(outcome.before, [true, true, true, false], 'Unsolved CAPTCHAs block only their forms');
    assert.deepEqual(outcome.solved, [false, true, false, false], 'Each successful callback unlocks its own form');
    assert.deepEqual(outcome.tokens, [[['cf-turnstile-response', 'token-0']], [['cf-turnstile-response', '']], [['g-recaptcha-response', 'token-0']], []]);
    assert(outcome.expired && outcome.failed, 'Expiration and errors block sending again');
    assert.match(outcome.error, /unavailable/);
    await page.waitForFunction(() => window.captchaMocks.turnstile[0].input.value === '');
    assert.deepEqual(await page.evaluate(() => window.violations), [], 'The production CSP allows the widgets');
    assert.equal(await page.locator('script[data-captcha-provider]').count(), 2);

    // v3 runs at submission time and resumes the original submitter only after receiving a token.
    assert.equal(await page.evaluate(() => window.captchaExecutions.length), 0, 'No v3 tokens are generated on page load');
    await page.evaluate(() => {
      window.completedScoreForms = [];
      for (const [index, form] of [...document.querySelectorAll('form.c-form')].slice(4).entries()) {
        form.querySelector('[name="fields[email]"]').value = 'ada@example.com';
        const button = form.querySelector('button');
        button.name = 'send'; button.value = 'original-' + index;
        form.addEventListener('submit', (event) => {
          if (!event.defaultPrevented) {
            window.completedScoreForms.push({ index, token: new FormData(form).getAll('g-recaptcha-response').at(-1), submitter: event.submitter?.value });
            event.preventDefault();
          }
        });
      }
      window.scoreForms = [...document.querySelectorAll('form.c-form')].slice(4);
      window.scoreForms[0].querySelector('button').click();
      window.scoreForms[0].querySelector('button').click();
      window.scoreForms[1].querySelector('button').click();
    });
    assert.equal(await page.evaluate(() => window.captchaExecutions.length), 2, 'Repeated clicks share a pending attempt while another form runs independently');
    assert.deepEqual(await page.evaluate(() => window.captchaMocks.recaptcha.slice(1).map((widget) => [widget.options.size, widget.options.badge, widget.options.sitekey])), [
      ['invisible', 'inline', 'recaptcha_v3-site'], ['invisible', 'inline', 'recaptcha_v3-site'],
    ]);
    assert.equal(await page.evaluate(() => window.captchaExecutions[0].options.action), await page.locator('[data-form-captcha="recaptcha_v3"]').first().getAttribute('data-action'));
    assert.match(await page.evaluate(() => window.captchaExecutions[0].options.action), /^[A-Za-z0-9_/]+$/);
    await page.evaluate(() => window.captchaExecutions[1].resolve('second-form-token'));
    await page.waitForFunction(() => window.completedScoreForms.length === 1);
    assert.deepEqual(await page.evaluate(() => window.completedScoreForms), [{ index: 1, token: 'second-form-token', submitter: 'original-1' }]);
    await page.evaluate(() => window.captchaExecutions[0].resolve('first-form-token'));
    await page.waitForFunction(() => window.completedScoreForms.length === 2);
    assert.deepEqual(await page.evaluate(() => window.completedScoreForms[1]), { index: 0, token: 'first-form-token', submitter: 'original-0' });
    await page.evaluate(() => {
      window.scoreForms[0].querySelector('button').click();
      window.captchaExecutions[2].reject(new Error('Provider unavailable'));
    });
    await page.waitForFunction(() => /unavailable/.test(window.scoreForms[0].querySelector('[data-captcha-status]').textContent));
    assert.equal(await page.evaluate(() => window.scoreForms[0].hasAttribute('aria-busy')), false);
    assert.equal(await page.evaluate(() => window.scoreForms[0].querySelector('[data-captcha-token]').value), '');
    await page.evaluate(() => {
      window.scoreForms[0].querySelector('button').click();
      window.captchaExecutions[3].resolve('fresh-retry-token');
    });
    await page.waitForFunction(() => window.completedScoreForms.length === 3);
    assert.equal(await page.evaluate(() => window.completedScoreForms[2].token), 'fresh-retry-token');
    await page.evaluate(() => {
      window.scoreForms[0].querySelector('button').click();
      window.scoreForms[0].reset();
      window.captchaExecutions[4].resolve('canceled-token');
    });
    await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 0)));
    assert.equal(await page.evaluate(() => window.completedScoreForms.length), 3, 'Reset cancels a pending token');
    await page.evaluate(() => {
      window.scoreForms[0].querySelector('[name="fields[email]"]').value = 'ada@example.com';
      window.scoreForms[0].querySelector('button').click();
      window.scoreForms[0].querySelector('[name="fields[email]"]').value = '';
      window.captchaExecutions[5].resolve('invalidated-form-token');
    });
    await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 0)));
    assert.equal(await page.evaluate(() => window.completedScoreForms.length), 3, 'Native validation runs again after the asynchronous token arrives');
    await page.evaluate(() => {
      window.scoreForms[0].querySelector('[name="fields[email]"]').value = 'ada@example.com';
      window.scoreForms[0].querySelector('button').click();
    });
    await page.clock.fastForward(15001);
    await page.waitForFunction(() => /unavailable/.test(window.scoreForms[0].querySelector('[data-captcha-status]').textContent));
    await page.evaluate(() => window.captchaExecutions[6].resolve('too-late-token'));
    await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 0)));
    assert.equal(await page.evaluate(() => window.completedScoreForms.length), 3, 'Timeouts cannot later submit a stale token');

    // Direct requests cannot bypass verification, including attempts to select "none" in the body.
    const action = await page.locator('form.c-form').first().getAttribute('action');
    const rejected = await page.request.post(origin + action, { headers: { Accept: 'application/json' }, form: { 'fields[email]': 'ada@example.com', captcha: 'none' } });
    assert.equal(rejected.status(), 422);
    assert.match((await rejected.json()).error.fields.captcha, /verification failed/);
    const redirect = await page.request.post(origin + action, { maxRedirects: 0, form: { _return: base + '/pages/contact', _anchor: 'contact' } });
    assert.equal(redirect.status(), 303);
    assert.equal(redirect.headers().location, base + '/pages/contact#contact-error');
    const scoreAction = await page.locator('form.c-form').last().getAttribute('action');
    const scoreRejected = await page.request.post(origin + scoreAction, { headers: { Accept: 'application/json' }, form: { 'fields[email]': 'ada@example.com', captcha: 'none' } });
    assert.equal(scoreRejected.status(), 422);
    assert.match((await scoreRejected.json()).error.fields.captcha, /verification failed/);
  }

  requests.length = 0;
  const scoreOnly = await page.goto(origin + '/score/contact');
  await page.waitForFunction(() => window.captchaMocks?.recaptcha.length === 1);
  assert.deepEqual(requests, ['www.google.com'], 'A v3-only page loads the Google API once');
  assert.doesNotMatch(scoreOnly.headers()['content-security-policy'], /challenges\.cloudflare\.com/);
  assert.equal(await page.evaluate(() => window.captchaMocks.recaptcha[0].options.size), 'invisible');
  await page.setViewportSize({ width: 320, height: 640 });
  await page.goto(origin + '/cms/pages/contact');
  await page.waitForFunction(() => window.captchaMocks?.recaptcha.length === 3);
  assert.equal(await page.evaluate(() => window.captchaMocks.recaptcha[0].options.size), 'compact');
  failProvider = true;
  await page.reload();
  await page.waitForFunction(() => [...document.querySelectorAll('[data-captcha-status]')].every((status) => /unavailable/.test(status.textContent)));
  assert.equal(await page.locator('[data-captcha-status]').count(), 5, 'Script load failures explain why each protected form is unavailable');
  assert.deepEqual(errors, []);
  console.log('CAPTCHA browser checks passed (v2/v3 coexistence, fresh submit-time tokens, original submitter, native validation, timeout, reset, errors, CSP, mobile size and direct POST rejection).');
} finally {
  await browser?.close();
  php?.kill();
  rmSync(directory, { recursive: true, force: true });
}
