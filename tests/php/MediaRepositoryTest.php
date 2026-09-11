<?php

declare(strict_types=1);

use CometCMS\Media\MediaRepository;

function comet_media_test_file(string $name, string $body = 'file'): void
{
    file_put_contents(comet_test_workspace_path() . '/media/' . $name, $body);
}

test('media repository manages nested categories and updates assigned files', function (): void {
    comet_media_test_file('hero.jpg');
    $repository = new MediaRepository();

    assert_same(['Images', 'Images / Heroes'], $repository->addCategory('Images / Heroes'));
    $item = $repository->assignCategory('hero.jpg', 'Images / Heroes');

    assert_same('Images / Heroes', $item['category']);

    assert_same(['Assets', 'Assets / Heroes'], $repository->renameCategory('Images', 'Assets'));
    assert_same('Assets / Heroes', $repository->item('hero.jpg')['category']);

    assert_same([], $repository->deleteCategory('Assets'));
    assert_same('', $repository->item('hero.jpg')['category']);
});

test('media repository filters sorts and paginates files', function (): void {
    comet_media_test_file('hero.jpg');
    comet_media_test_file('song.mp3');
    comet_media_test_file('guide.pdf');
    $repository = new MediaRepository();

    $repository->assignCategory('guide.pdf', 'Docs');

    assert_same(['hero.jpg'], array_column($repository->files('', null, 'images', 'name'), 'name'));
    assert_same(['guide.pdf'], array_column($repository->files('guide', 'Docs', 'documents', 'name'), 'name'));
    assert_same(['hero.jpg', 'song.mp3'], array_column($repository->limitedFiles('', null, 2, 1, 'all', 'name')['data'], 'name'));
    assert_same(3, $repository->limitedFiles('', null, 2, 0, 'all', 'name')['meta']['total']);
    assert_same(['song.mp3'], array_column($repository->limitedFiles('', null, 2, 0, 'all', 'name', null, ['guide.pdf' => [], 'hero.jpg' => []])['data'], 'name'));
    assert_same(['total' => 3, 'uncategorized' => 2, 'categories' => ['Docs' => 1]], $repository->stats());
});

test('media repository preserves metadata when renaming and supports bulk updates', function (): void {
    comet_media_test_file('old.jpg');
    comet_media_test_file('other.jpg');
    $repository = new MediaRepository();

    $repository->assignCategory('old.jpg', 'Images');
    $repository->updateMeta('old.jpg', 'Hero alt', 'Hero title');
    $repository->updateVisibility('old.jpg', 'private');

    $renamed = $repository->rename('old.jpg', 'hero');

    assert_same('hero.jpg', $renamed['name']);
    assert_same('Images', $renamed['category']);
    assert_same('Hero alt', $renamed['alt']);
    assert_same('Hero title', $renamed['title']);
    assert_same('private', $renamed['visibility']);
    assert_false(is_file(comet_test_workspace_path() . '/media/old.jpg'));

    assert_same(['hero.jpg', 'other.jpg'], array_column($repository->updateVisibilityForMany(['hero.jpg', 'other.jpg', 'missing.jpg'], 'private'), 'name'));
    assert_same(['hero.jpg', 'other.jpg'], array_column($repository->files('', null, 'all', 'name', 'private'), 'name'));

    assert_same(['hero.jpg', 'other.jpg'], array_column($repository->assignCategoryToMany(['hero.jpg', 'other.jpg'], 'Shared'), 'name'));
    assert_same(['Images', 'Shared'], $repository->categories());
});

test('media repository generates cached variants without upscaling originals', function (): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }

    $path = comet_test_workspace_path() . '/media/landscape.jpg';
    $source = imagecreatetruecolor(1200, 600);
    imagejpeg($source, $path, 90);
    imagedestroy($source);
    $repository = new MediaRepository();

    $variant = $repository->variant('landscape.jpg', ['w' => 320, 'format' => 'jpeg']);
    assert_same(320, $variant['width']);
    assert_same(160, $variant['height']);
    assert_same('image/jpeg', $variant['mime']);
    assert_file_exists_at($variant['path']);
    assert_same($variant['path'], $repository->variant('landscape.jpg', ['w' => 320, 'format' => 'jpeg'])['path']);

    $large = $repository->variant('landscape.jpg', ['w' => 1920, 'format' => 'jpeg']);
    assert_same(1200, $large['width']);
    assert_same(600, $large['height']);
    assert_throws(InvalidArgumentException::class, fn(): array => $repository->variant('landscape.jpg', ['w' => 333]));

    global $cometConfig;
    $cometConfig['media']['variants']['allow_custom_sizes'] = true;
    try {
        $cover = $repository->variant('landscape.jpg', ['w' => 400, 'h' => 400, 'fit' => 'cover', 'format' => 'jpeg']);
        assert_same(400, $cover['width']);
        assert_same(400, $cover['height']);
    } finally {
        $cometConfig['media']['variants']['allow_custom_sizes'] = false;
    }

    $descriptor = $repository->variantDescriptor('landscape.jpg');
    assert_same([320, 640, 960], $descriptor['widths']);
    assert_true(isset($descriptor['formats'][0]));
});

test('media variant cache is disposable and removed with its original', function (): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }

    $path = comet_test_workspace_path() . '/media/photo.jpg';
    $source = imagecreatetruecolor(800, 400);
    imagejpeg($source, $path, 90);
    imagedestroy($source);
    $repository = new MediaRepository();
    $variant = $repository->variant('photo.jpg', ['w' => 320, 'format' => 'jpeg']);
    $cacheDirectory = dirname($variant['path']);

    $repository->delete('photo.jpg');

    assert_false(is_file($path));
    assert_false(is_dir($cacheDirectory));
});
