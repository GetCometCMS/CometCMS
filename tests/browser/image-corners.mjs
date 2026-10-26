// Run with: node tests/browser/image-corners.mjs
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const base = readFileSync(new URL('../../cms/app/Site/assets/site.css', import.meta.url), 'utf8');
const variants = execFileSync('php', ['-r', 'require $argv[1]; echo \\CometCMS\\Site\\StyleVariants::css(file_get_contents($argv[2]));',
  `${root}cms/app/Site/StyleVariants.php`, `${root}cms/app/Site/assets/site.css`], { encoding: 'utf8' });
const browser = await chromium.launch({ executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  // Exercise the renderer's natural, cropped, linked and captioned image structures.
  const image = '<img width="120" height="80" alt="Test" src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'120\' height=\'80\'%3E%3Cpath fill=\'purple\' d=\'M0 0h120v80H0z\'/%3E%3C/svg%3E">';
  const structures = [image, `<div class="c-ratio c-ratio-3-2">${image}</div>`,
    `<figure class="c-figure"><a class="c-image-link" href="#"><div class="c-ratio c-ratio-3-2 c-fit-contain">${image}</div></a><figcaption>Caption</figcaption></figure>`];
  const cases = [
    ['', '8px'], ['c-r-none', '0px'], ['c-r-theme', '8px'], ['c-r-sm', '4px'],
    ['c-r-md', '8px'], ['c-r-lg', '16px'], ['c-r-full', '9999px'],
    ['c-t-r-none c-m-r-lg', '8px'],
  ];
  await page.setContent(`<style>${base}\n${variants}
    :root { --radius-box: 8px; --radius-sm: 4px; --radius-md: 8px; --radius-lg: 16px; --c-surface: white; }
    body { display: block; background: slategray; }
    .fixture { width: 120px; margin: 10px; background: white; }
  </style>${cases.flatMap(([classes], index) => structures.map((inner, shape) =>
    `<div class="c-card"><div id="case-${index}-${shape}" class="c-el c-el-image fixture ${classes}">${inner}</div></div>`)).join('')}`);

  async function check(index, shape, expected) {
    const result = await page.locator(`#case-${index}-${shape}`).evaluate((element) => {
      const nodes = [element, ...element.querySelectorAll('.c-figure, .c-image-link, .c-ratio, img')];
      const bounds = element.getBoundingClientRect();
      const corner = document.elementFromPoint(bounds.left + 0.01, bounds.top + 0.01);
      return { radii: nodes.map((node) => getComputedStyle(node).borderTopRightRadius),
        cornerInside: element.contains(corner) };
    });
    assert(result.radii.every((radius) => radius === expected), `Case ${index}/${shape}: expected ${expected}, got ${result.radii}`);
    if (expected !== '0px') assert.equal(result.cornerInside, false, `Case ${index}/${shape}: background leaks into the rounded corner`);
  }
  // Keep each fixture visible for the corner hit test.
  for (let index = 0; index < cases.length; index++) {
    for (let shape = 0; shape < structures.length; shape++) {
      await page.locator(`#case-${index}-${shape}`).scrollIntoViewIfNeeded();
      await check(index, shape, cases[index][1]);
    }
  }
  for (const [width, expected] of [[900, '0px'], [600, '16px']]) {
    await page.setViewportSize({ width, height: 800 });
    for (let shape = 0; shape < structures.length; shape++) {
      await page.locator(`#case-7-${shape}`).scrollIntoViewIfNeeded();
      await check(7, shape, expected);
    }
  }
  for (const expected of ['0px', '16px']) {
    await page.evaluate((radius) => document.documentElement.style.setProperty('--radius-box', radius), expected);
    for (let shape = 0; shape < structures.length; shape++) {
      await page.locator(`#case-0-${shape}`).scrollIntoViewIfNeeded();
      await check(0, shape, expected);
    }
  }
  console.log('Image corners passed: default and explicit radii, crops, links, captions, cards, theme changes and responsive overrides.');
} finally {
  await browser.close();
}
