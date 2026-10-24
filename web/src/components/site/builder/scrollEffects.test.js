import { describe, expect, it } from 'vitest';
import { scrollStyleVisible } from './scrollEffects.js';

const visible = (kind, style) => ['scroll_background', 'scroll_element', 'scroll_strength', 'scroll_direction']
  .filter((key) => scrollStyleVisible({ kind }, key, (name) => style[name]));

describe('scroll effects controls', () => {
  it('shows background movement only for image backgrounds on sections', () => {
    expect(visible('section', {})).toEqual([]);
    expect(visible('section', { background_image: 'photo.jpg' })).toEqual(['scroll_background']);
    expect(visible('section', { background_image: 'photo.jpg', background_gradient: { type: 'linear' } })).toEqual([]);
    expect(visible('row', { background_image: 'photo.jpg' })).toEqual([]);
    expect(visible('gallery', {})).toEqual([]);
  });
  it('offers direction only for standalone images and strength only while enabled', () => {
    expect(visible('image', {})).toEqual(['scroll_element']);
    expect(visible('image', { scroll_element: 'parallax' })).toEqual(['scroll_element', 'scroll_strength', 'scroll_direction']);
    expect(visible('section', { background_image: 'photo.jpg', scroll_background: 'parallax' })).toEqual(['scroll_background', 'scroll_strength']);
  });
});
