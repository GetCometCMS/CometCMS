<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\ValidationException;
use CometCMS\Site\SiteRepository;

/** A hierarchical "pages" type served at /{slug} with about → team → berlin. */
function comet_tree_test_setup(): array
{
    (new ContentTypeRepository())->save(['name' => 'pages', 'hierarchical' => true, 'fields' => []]);
    $content = ContentRepository::make();
    $user = comet_site_test_user();
    $about = $content->save('pages', ['title' => 'About', 'status' => 'published'], $user);
    $team = $content->save('pages', ['title' => 'Team', 'parent' => $about['uid'], 'status' => 'published'], $user);
    $berlin = $content->save('pages', ['title' => 'Berlin', 'parent' => $team['uid'], 'status' => 'published'], $user);
    (new SiteRepository())->saveSettings(['title' => 'Tree', 'routes' => ['pages' => ['enabled' => true, 'pattern' => '/{slug}', 'index_path' => '']]]);

    return [$content, $about, $team, $berlin];
}

test('hierarchical pages are served and linked at their nested paths', function (): void {
    [, , , $berlin] = comet_tree_test_setup();

    assert_same(200, comet_site_test_get('/about/team/berlin')['status']);
    assert_same(200, comet_site_test_get('/about/team')['status']);
    assert_same(404, comet_site_test_get('/berlin')['status'], 'A child is not served at its bare slug.');
    assert_same(404, comet_site_test_get('/team/berlin')['status']);
    assert_same('/about/team/berlin', comet_site_test_context()->entryUrl('pages', $berlin));
    assert_true(str_contains(comet_site_test_get('/sitemap.xml')['body'], 'https://example.test/about/team/berlin'));
});

test('hierarchical slugs are unique among siblings only', function (): void {
    [$content, $about] = comet_tree_test_setup();
    $user = comet_site_test_user();

    $nested = $content->save('pages', ['title' => 'Team', 'slug' => 'team', 'status' => 'published'], $user);
    assert_same('team', $nested['slug'], 'A top-level page may reuse a nested slug.');
    assert_throws(ValidationException::class, fn() => $content->save('pages', ['title' => 'Team', 'slug' => 'team', 'parent' => $about['uid']], $user, null, false));
    assert_throws(ValidationException::class, fn() => $content->move('pages', $nested['id'], $about['uid'], 0, $user));
    assert_same(200, comet_site_test_get('/team')['status']);
});

test('pages cannot be moved below themselves', function (): void {
    [$content, $about, , $berlin] = comet_tree_test_setup();
    $user = comet_site_test_user();

    assert_throws(ValidationException::class, fn() => $content->move('pages', $about['id'], $about['uid'], 0, $user));
    assert_throws(ValidationException::class, fn() => $content->move('pages', $about['id'], $berlin['uid'], 0, $user));
    assert_throws(ValidationException::class, fn() => $content->save('pages', ['parent' => 'missing'], $user, $about['id']));
});

test('moving a page renumbers its new siblings and changes its URL', function (): void {
    [$content, $about, $team, $berlin] = comet_tree_test_setup();
    $user = comet_site_test_user();
    $contact = $content->save('pages', ['title' => 'Contact', 'status' => 'published'], $user);

    $moved = $content->move('pages', $berlin['id'], '', 1, $user);

    assert_same('', $moved['parent']);
    assert_same(['about', 'berlin', 'contact'], array_column($content->children('pages', ''), 'slug'));
    assert_same([0, 1, 2], array_map(static fn(array $entry): int => $entry['position'], $content->children('pages', '')));
    assert_same(200, comet_site_test_get('/berlin')['status']);
    assert_same(404, comet_site_test_get('/about/team/berlin')['status']);
    assert_same($about['uid'], $content->find('pages', $team['id'])['parent'], 'Other pages keep their place.');
    assert_same($contact['uid'], $content->children('pages', '')[2]['uid']);
});

test('children of unpublished pages are not reachable', function (): void {
    [$content, $about, , $berlin] = comet_tree_test_setup();
    $content->save('pages', ['status' => 'draft'], comet_site_test_user(), $about['id']);

    assert_same(404, comet_site_test_get('/about/team/berlin')['status']);
    assert_null(comet_site_test_context()->entryUrl('pages', $berlin));
});

test('pages with children cannot be trashed alone, but whole branches can', function (): void {
    [$content, $about, $team, $berlin] = comet_tree_test_setup();
    $user = comet_site_test_user();

    assert_throws(ValidationException::class, fn() => $content->softDelete('pages', $team['id'], $user));
    $order = $content->deepestFirst('pages', [$about['id'], $team['id'], $berlin['id']]);
    assert_same([$berlin['id'], $team['id'], $about['id']], $order);
    foreach ($order as $id) $content->softDelete('pages', $id, $user);
    assert_same([], $content->all('pages'));

    $restored = $content->restore('pages', $berlin['id'], $user);
    assert_same('', $restored['parent'], 'A page whose parent is in the trash returns at the top level.');
});

test('flat types keep single-segment slugs and global uniqueness', function (): void {
    comet_site_test_setup();
    $content = ContentRepository::make();
    $copy = $content->save('blog', ['title' => 'Hello'], comet_site_test_user());

    assert_same('hello-2', $copy['slug']);
    assert_false(array_key_exists('parent', $content->find('blog', $copy['id'])));
    assert_same(404, comet_site_test_get('/blog/hello/extra')['status']);
});
