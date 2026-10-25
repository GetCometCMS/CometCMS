<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;

/** A contained section with one row whose column holds $element (and $style as the column's style). */
function comet_image_sizes_page(array $element, array $span, array $sectionProps = [], array $sectionStyle = [], array $columnStyle = []): array
{
    return LayoutSchema::normalize([LayoutSchema::node('section', $sectionProps, $sectionStyle, [
        LayoutSchema::node('row', [], [], [LayoutSchema::node('column', ['span' => $span], $columnStyle, [$element])]),
    ])]);
}

function comet_image_sizes_jpeg(string $name, int $width, int $height): void
{
    $source = imagecreatetruecolor($width, $height);
    imagejpeg($source, comet_test_workspace_path() . '/media/' . $name, 80);
    unset($source);
}

test('images tell the browser how wide their column is and offer every size up to large', function (): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }

    comet_site_test_setup();
    comet_image_sizes_jpeg('wide.jpg', 3000, 1500);
    comet_image_sizes_jpeg('mid.jpg', 1500, 750);
    $renderer = new NodeRenderer(comet_site_test_context());
    $third = ['mobile' => 12, 'tablet' => 0, 'desktop' => 4];

    $html = $renderer->render(comet_image_sizes_page(LayoutSchema::node('image', ['media' => 'wide.jpg']), $third));
    assert_true(str_contains($html, 'sizes="(min-width: 1024px) min(34vw, 400px), min(100vw, 1200px)"'));
    assert_true(str_contains($html, 'size=thumbnail 400w, /media/default/wide.jpg?size=medium 960w, /media/default/wide.jpg?size=large 1920w"'));
    assert_true(str_contains($html, 'src="/media/default/wide.jpg?size=large"'));
    assert_false(str_contains($html, 'wide.jpg 3000w'), 'Originals larger than the large size are never offered.');
    assert_false(isset(LayoutSchema::node('image')['props']['image_size']), 'There is no size to choose.');

    $html = $renderer->render(comet_image_sizes_page(LayoutSchema::node('image', ['media' => 'mid.jpg', 'max_width' => 300]), $third, ['width' => 'full']));
    assert_true(str_contains($html, 'sizes="(min-width: 1024px) min(34vw, 300px), min(100vw, 300px)"'));
    assert_true(str_contains($html, 'mid.jpg?size=medium 960w, /media/default/mid.jpg 1500w"'));

    $gallery = $renderer->render(comet_image_sizes_page(LayoutSchema::node('gallery', ['media' => ['wide.jpg'], 'columns' => 3]), ['mobile' => 12, 'tablet' => 0, 'desktop' => 0], ['width' => 'full']));
    assert_true(str_contains($gallery, 'sizes="(min-width: 1024px) 34vw, (min-width: 768px) 50vw, 100vw"'));
});

test('backgrounds load a smaller size where the element is narrower', function (): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }

    comet_site_test_setup();
    comet_image_sizes_jpeg('hero.jpg', 3000, 1500);
    $renderer = new NodeRenderer(comet_site_test_context());
    $text = LayoutSchema::node('heading', ['text' => 'Hello']);
    $full = ['mobile' => 12, 'tablet' => 0, 'desktop' => 0];

    $html = $renderer->render(comet_image_sizes_page($text, $full, [], ['background_image' => 'hero.jpg']));
    assert_true(str_contains($html, 'c-bg-responsive'));
    assert_true(str_contains($html, "--c-bg-d:url(&#039;/media/default/hero.jpg?size=large&#039;);--c-bg-t:url(&#039;/media/default/hero.jpg?size=large&#039;);--c-bg-m:url(&#039;/media/default/hero.jpg?size=medium&#039;)"));
    assert_false(str_contains($html, 'background-image:url'));

    // Tall sections are covered on portrait phones, so phones keep the large size.
    $html = $renderer->render(comet_image_sizes_page($text, $full, ['min_height' => 'screen'], ['background_image' => 'hero.jpg']));
    assert_false(str_contains($html, 'c-bg-responsive'));
    assert_true(str_contains($html, "background-image:url(&#039;/media/default/hero.jpg?size=large&#039;)"));

    $html = $renderer->render(comet_image_sizes_page($text, ['mobile' => 12, 'tablet' => 0, 'desktop' => 4], [], [], ['background_image' => 'hero.jpg', 'background_blur' => 4]));
    assert_true(str_contains($html, "--c-bg-d:url(&#039;/media/default/hero.jpg?size=medium&#039;);--c-bg-t:url(&#039;/media/default/hero.jpg?size=large&#039;);--c-bg-m:url(&#039;/media/default/hero.jpg?size=medium&#039;);--c-background-blur:4px"));
});
