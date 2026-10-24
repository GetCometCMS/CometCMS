<?php

declare(strict_types=1);

use CometCMS\Core\ValidationException;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\LinkModal;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteRepository;

function comet_modal_test_template(string $title = 'Shared modal frame'): array
{
    return [LayoutSchema::node('section', ['width' => 'full'], ['background' => 'surface', 'padding_y' => 'lg'], [
        LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [
            LayoutSchema::node('heading', ['text' => $title]),
            LayoutSchema::node(LayoutSchema::SITE_CONTENT, ['content_height' => 420], ['radius' => 'lg']),
        ])]),
    ])];
}

test('modal template keeps one site content slot through save history reset and restore', function (): void {
    $site = comet_site_test_setup();
    $defaults = $site->region('modal');
    assert_same(LayoutSchema::SITE_CONTENT, $defaults[0]['children'][0]['children'][0]['children'][0]['kind']);
    assert_true(str_contains(LinkModal::markup(comet_site_test_context($site)), ' data-modal-content '));
    assert_true(in_array('modal', LayoutSchema::spec()['special_pages'], true));
    assert_false(in_array(LayoutSchema::SITE_CONTENT, LayoutSchema::spec()['elements'], true));
    assert_true(in_array(LayoutSchema::SITE_CONTENT, LayoutSchema::spec(false, true)['elements'], true));
    assert_false(in_array(LayoutSchema::SITE_CONTENT, LayoutSchema::spec(true)['elements'], true));
    $saved = $site->saveRegion('modal', comet_modal_test_template(), comet_site_test_user());
    assert_same($saved, (new SiteRepository())->region('modal'));
    assert_same(420, $saved[0]['children'][0]['children'][0]['children'][1]['props']['content_height']);
    $site->saveRegion('modal', comet_modal_test_template('Updated frame'), comet_site_test_user());
    $revisions = $site->regionRevisions('modal');
    $previous = array_values(array_filter($revisions, static fn(array $revision): bool => ($site->regionRevision('modal', $revision['id'])['nodes'][0]['children'][0]['children'][0]['children'][0]['props']['text'] ?? '') === 'Shared modal frame'))[0];
    assert_same($saved, $site->restoreRegionRevision('modal', $previous['id'])['nodes']);
    assert_same($defaults, $site->resetRegion('modal'));
    assert_false($site->regionCustomized('modal'));
});

test('modal templates reject missing duplicate or repeated content slots without changing the saved design', function (): void {
    $site = comet_site_test_setup();
    $saved = $site->saveRegion('modal', comet_modal_test_template());
    $duplicate = comet_modal_test_template();
    $duplicate[0]['children'][0]['children'][0]['children'][] = LayoutSchema::node(LayoutSchema::SITE_CONTENT);
    foreach ([[], [LayoutSchema::node('section')], $duplicate] as $invalid) {
        assert_throws(ValidationException::class, fn() => $site->saveRegion('modal', $invalid));
        assert_same($saved, $site->region('modal'));
    }
    // Other document types and collection cards cannot capture the selected page.
    assert_same([], LayoutSchema::normalize([LayoutSchema::node(LayoutSchema::SITE_CONTENT)]));
    assert_same([], LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [LayoutSchema::node(LayoutSchema::SITE_CONTENT)])], 'column')[0]['children']);
    $card = LayoutSchema::node('collection', ['type' => 'blog'], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node(LayoutSchema::SITE_CONTENT)])]);
    $invalid = comet_modal_test_template();
    $invalid[0]['children'][0]['children'][0]['children'] = [$card];
    assert_throws(ValidationException::class, fn() => $site->saveRegion('modal', $invalid));
});

test('modal builder previews show an annotated placeholder and live pages use the saved template', function (): void {
    $site = comet_site_test_setup();
    $saved = $site->saveRegion('modal', comet_modal_test_template());
    $ctx = comet_site_test_context($site);
    $ctx->preview = $ctx->annotate = true;
    $html = (new PageRenderer($ctx))->regionPreview('modal');
    assert_true(str_contains($html, 'c-link-modal-preview'));
    assert_true(str_contains($html, 'data-drop-root'));
    assert_true(str_contains($html, 'data-kind="site_content"'));
    assert_true(str_contains($html, 'data-placeholder="siteContent"'));
    assert_true(str_contains($html, '--c-modal-content-height:420px'));
    assert_true(str_contains($html, 'Shared modal frame'));
    assert_false(str_contains($html, '<iframe'));
    assert_false(str_contains($html, '<script'));
    assert_false(str_contains($html, '<header'));
    assert_false(str_contains($html, '<footer'));
    $ctx->annotate = false;
    $ctx->browsePreview = true;
    $html = (new PageRenderer($ctx))->modalPreview();
    assert_true(str_contains($html, 'Site content: the selected page appears here.'));
    assert_false(str_contains($html, 'data-kind="site_content"'));
    $button = LayoutSchema::node('button', ['link' => ['type' => 'home', 'open_modal' => true]]);
    $site->saveLayout('blog', 'detail', [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [$button])])])]);
    $ctx = comet_site_test_context($site);
    $html = (new PageRenderer($ctx))->entryPage($ctx->type('blog'), $ctx->findEntry('blog', 'hello'));
    assert_same(1, substr_count($html, ' data-link-modal-dialog'));
    assert_same(1, substr_count($html, ' data-modal-content '));
    assert_true(str_contains($html, 'Shared modal frame'));
    assert_false(str_contains($html, 'Site content: the selected page appears here.'));
    assert_false(str_contains($html, 'data-node="' . $saved[0]['id'] . '"'));
});

test('modal editor API exposes its slot schema and previews unsaved template content', function (): void {
    $controller = '(new \\CometCMS\\Controllers\\Admin\\SiteController(new \\CometCMS\\Core\\Http()))';
    [$status, $json] = comet_admin_request('admin', '', 'GET', '/admin/api/site/regions/modal', $controller . '->region("modal");');
    assert_same(200, $status);
    assert_true(in_array(LayoutSchema::SITE_CONTENT, $json['data']['spec']['elements'], true));
    assert_true(in_array(LayoutSchema::SITE_CONTENT, $json['data']['spec']['kinds']['column']['children'], true));
    [$status, $json] = comet_admin_request('admin', '', 'POST', '/admin/api/site/preview', $controller . '->preview();', ['target' => 'region', 'region' => 'modal', 'nodes' => comet_modal_test_template('Unsaved modal frame')]);
    assert_same(200, $status);
    assert_true(str_contains($json['data']['html'], 'Unsaved modal frame'));
    assert_true(str_contains($json['data']['html'], 'data-kind="site_content"'));
    [$status, $json] = comet_admin_request('admin', '', 'GET', '/admin/api/site', $controller . '->index();');
    assert_same(200, $status);
    assert_same(false, $json['data']['special_pages']['modal']);
});

test('modal template interactions keep unique identifiers alongside the parent page', function (): void {
    $site = comet_site_test_setup();
    $tabs = static fn(string $prefix): array => LayoutSchema::node('tabs', [], [], [
        LayoutSchema::node('tab', ['title' => $prefix . ' first'], [], [LayoutSchema::node('heading', ['text' => 'First panel'])]),
        LayoutSchema::node('tab', ['title' => $prefix . ' second'], [], [LayoutSchema::node('heading', ['text' => 'Second panel'])]),
    ]);
    $component = $site->createComponent('Shared tabs', [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [$tabs('Shared')])])]);
    $instance = static fn(): array => LayoutSchema::node(LayoutSchema::COMPONENT, ['component' => $component['id'], 'root' => 'row']);
    $template = comet_modal_test_template();
    $template[0]['children'][0]['children'][0]['children'][] = $instance();
    $site->saveRegion('modal', $template);
    $site->saveLayout('blog', 'detail', [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [
        $instance(), LayoutSchema::node('button', ['link' => ['type' => 'home', 'open_modal' => true]]),
    ])])])]);
    $ctx = comet_site_test_context($site);
    $html = (new PageRenderer($ctx))->entryPage($ctx->type('blog'), $ctx->findEntry('blog', 'hello'));
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $element) $ids[] = $element->getAttribute('id');
    assert_same(count($ids), count(array_unique($ids)), 'Parent and modal controls must not share IDs.');
    assert_same(4, $xpath->query('//*[@role="tab"]')->length);
});
