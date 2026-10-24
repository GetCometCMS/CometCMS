// Run: node tests/browser/scroll-effects.mjs (PHP and Playwright Chromium required).
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const cssResult = spawnSync('php', ['-r', `require 'tests/php/bootstrap.php'; echo CometCMS\\Site\\Theme::baseStylesheet(); echo CometCMS\\Site\\StyleVariants::css(CometCMS\\Site\\Theme::baseStylesheet());`], { cwd: root, encoding: 'utf8' });
assert.equal(cssResult.status, 0, cssResult.stderr);
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BIN || undefined, args: ['--no-sandbox'] });
try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, reducedMotion: 'no-preference' });
  // Motion layer structure produced by NodeRenderer. Controls use the real generated device utilities.
  await page.setContent(`<style>${cssResult.stdout}
    body{margin:0} .spacer{height:900px} section{height:500px;position:relative} .content{height:50px}
    .image{height:200px}.c-parallax-element{height:100%}
  </style><div class="spacer"></div>
    <section id="background" class="c-scroll-host c-scroll-background c-scroll-background-parallax c-scroll-strength-strong c-overlay-dark c-m-scroll-background-none" style="--c-background-image:linear-gradient(red,blue)">
      <span class="c-parallax-clip"><span class="c-parallax-background"></span></span><div class="content">Stationary content</div>
    </section>
    <div id="slow" class="image c-scroll-host c-scroll-element-parallax c-m-scroll-element-none"><div class="c-parallax-element">Slower</div></div>
    <div id="fast" class="image c-scroll-host c-scroll-element-parallax c-scroll-direction-faster c-m-scroll-element-none"><div class="c-parallax-element">Faster</div></div>
    <div class="spacer"></div>`);
  assert(await page.evaluate(() => CSS.supports('animation-timeline: view()')), 'Browser must support view timelines');
  const settle = () => page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  const metrics = () => page.evaluate(() => {
    const section = document.querySelector('#background');
    const image = section.querySelector('.c-parallax-background');
    const clip = section.querySelector('.c-parallax-clip').getBoundingClientRect();
    const rect = image.getBoundingClientRect();
    const y = (selector) => new DOMMatrixReadOnly(getComputedStyle(document.querySelector(selector)).transform).m42;
    return {
      bg: y('.c-parallax-background'), slow: y('#slow .c-parallax-element'), fast: y('#fast .c-parallax-element'),
      content: section.querySelector('.content').getBoundingClientRect().top - section.getBoundingClientRect().top,
      overlay: getComputedStyle(section, '::before').transform,
      covered: rect.top <= clip.top + 1 && rect.bottom >= clip.bottom - 1,
      animation: getComputedStyle(image).animationName,
    };
  });
  await page.evaluate(() => scrollTo(0, 750)); await settle();
  const before = await metrics();
  await page.evaluate(() => scrollTo(0, 1100)); await settle();
  const after = await metrics();
  assert(after.bg > before.bg, 'Background offsets compensate for scroll');
  assert(after.slow > before.slow, 'Slower element compensates for scroll');
  assert(after.fast < before.fast, 'Faster element follows scroll');
  assert.equal(after.content, before.content, 'Content never follows the background');
  assert.equal(after.overlay, 'none', 'Overlay never follows the background');
  assert(before.covered && after.covered, 'Oversized backgrounds cover the clip throughout movement');
  await page.setViewportSize({ width: 390, height: 800 }); await settle();
  assert.equal((await metrics()).animation, 'none', 'Mobile defaults to no motion');
  await page.locator('#background').evaluate((el) => el.classList.replace('c-m-scroll-background-none', 'c-m-scroll-background-parallax'));
  await settle();
  assert.equal((await metrics()).animation, 'c-parallax-motion', 'Mobile can explicitly enable motion');
  await page.emulateMedia({ reducedMotion: 'reduce' }); await settle();
  assert.equal((await metrics()).animation, 'none', 'Reduced motion wins over device overrides');
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.locator('#background').evaluate((el) => el.classList.add('c-scroll-static')); await settle();
  assert.equal((await metrics()).animation, 'none', 'Canvas remains static');
  console.log('Scroll effects browser checks passed');
} finally {
  await browser.close();
}
