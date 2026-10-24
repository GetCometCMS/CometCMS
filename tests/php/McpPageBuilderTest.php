<?php

declare(strict_types=1);

use CometCMS\Content\ContentTypeRepository;
use CometCMS\Mcp\PageBuilderTools;
use CometCMS\Site\SiteRepository;

function comet_test_builder_token(): string
{
    return comet_test_mcp_token([
        ['actions' => ['site.manage', 'schema.create', 'content.create'], 'resources' => ['workspace:default:*']],
    ]);
}

function comet_test_builder_sections(): array
{
    return [
        ['id' => 'hero', 'kind' => 'section', 'children' => [
            ['id' => 'row', 'kind' => 'row', 'children' => [
                ['id' => 'column', 'kind' => 'column', 'children' => [
                    ['id' => 'heading', 'kind' => 'heading', 'props' => ['text' => 'Welcome', 'level' => 'h1']],
                    ['id' => 'body', 'kind' => 'text', 'props' => ['html' => '<p>Body</p>']],
                ]],
            ]],
        ]],
        ['id' => 'other', 'kind' => 'section', 'name' => 'Keep me'],
    ];
}

function comet_test_builder_layout(string $token): array
{
    [, $payload] = comet_test_mcp_tool($token, 'get_layout', ['collection' => 'pages', 'variant' => 'detail']);
    assert_true(isset($payload['data']), json_encode($payload));
    return $payload['data'];
}

function comet_test_builder_setup(): array
{
    $token = comet_test_builder_token();
    [, $created] = comet_test_mcp_tool($token, 'create_content_type', ['name' => 'pages']);
    assert_same('pages', $created['data']['name']);
    $layout = comet_test_builder_layout($token);
    [, $saved] = comet_test_mcp_tool($token, 'set_layout', ['collection' => 'pages', 'variant' => 'detail', 'revision' => $layout['revision'], 'sections' => comet_test_builder_sections()]);
    assert_true(isset($saved['data']['revision']), json_encode($saved));
    return [$token, $saved['data']];
}

test('mcp builder discovery and execution require workspace site manage grants', function (): void {
    $headless = comet_test_mcp_token([['actions' => ['content.*'], 'resources' => ['workspace:default:content:*']]]);
    $wrongWorkspace = comet_test_mcp_token([['actions' => ['site.manage'], 'resources' => ['workspace:other:site:*']]]);
    $denied = comet_test_mcp_token([
        ['actions' => ['site.manage'], 'resources' => ['*']],
        ['effect' => 'deny', 'actions' => ['site.manage'], 'resources' => ['workspace:default:site:*']],
    ]);
    foreach ([null, $headless, $wrongWorkspace, $denied] as $token) {
        [$response] = comet_test_mcp_request($token, 'tools/list');
        $names = array_column($response['result']['tools'], 'name');
        assert_same([], array_values(array_intersect(PageBuilderTools::NAMES, $names)));
        foreach (PageBuilderTools::NAMES as $name) {
            [$response, $payload] = comet_test_mcp_tool($token, $name);
            assert_true($response['result']['isError']);
            assert_same($token === null ? 401 : 403, $payload['error']['status']);
            assert_same(['site.manage'], $payload['error']['required_permissions'][0]['actions']);
        }
    }
    [$response] = comet_test_mcp_request(comet_test_builder_token(), 'tools/list');
    $names = array_column($response['result']['tools'], 'name');
    assert_same(PageBuilderTools::NAMES, array_values(array_intersect(PageBuilderTools::NAMES, $names)));
    foreach ($response['result']['tools'] as $tool) {
        if ($tool['name'] === 'set_layout') {
            assert_true(in_array('revision', $tool['inputSchema']['required'], true));
            assert_true(isset($tool['inputSchema']['$defs']['node']));
            assert_false($tool['annotations']['readOnlyHint']);
        }
    }
});

test('mcp builder creates layouts and edits element props end to end', function (): void {
    [$token, $layout] = comet_test_builder_setup();
    assert_same(['hero', 'other'], array_column($layout['sections'], 'id'));
    assert_same(5, $layout['sections'][0]['node_count']);
    assert_false(isset($layout['sections'][0]['children']));
    assert_false(isset($layout['sections'][0]['props']));
    [$response, $schema] = comet_test_mcp_tool($token, 'get_builder_schema', ['kind' => 'heading']);
    assert_same($schema, $response['result']['structuredContent']);
    assert_same('h2', $schema['data']['props']['level']['default']);
    [, $section] = comet_test_mcp_tool($token, 'get_section', ['collection' => 'pages', 'variant' => 'detail', 'section_id' => 'hero']);
    assert_same('heading', $section['data']['section']['children'][0]['children'][0]['children'][0]['id']);
    [, $updated] = comet_test_mcp_tool($token, 'update_element_props', [
        'collection' => 'pages', 'variant' => 'detail', 'revision' => $layout['revision'], 'element_id' => 'heading',
        'props' => ['text' => 'Hello world'], 'style' => ['align' => 'center'],
    ]);
    assert_same('Hello world', $updated['data']['element']['props']['text']);
    assert_same('h1', $updated['data']['element']['props']['level']);
    assert_same('hero', $updated['data']['section_id']);
    assert_same('center', $updated['data']['element']['style']['align']);
    assert_same([], $updated['data']['element']['children']);
    assert_true($layout['revision'] !== $updated['data']['revision']);
    [, $read] = comet_test_mcp_tool($token, 'get_element', ['collection' => 'pages', 'variant' => 'detail', 'element_id' => 'heading']);
    assert_same($updated['data']['element'], $read['data']['element']);
    [, $entry] = comet_test_mcp_tool($token, 'create_entry', ['collection' => 'pages', 'entry' => ['title' => 'Home']]);
    assert_same('Home', $entry['data']['title']);
    $site = new SiteRepository();
    $saved = $site->layout('pages', 'detail');
    $html = (new \CometCMS\Site\NodeRenderer(comet_site_test_context($site)))->render($saved);
    assert_true(str_contains($html, 'Hello world</h1>'));
    assert_same('<p>Body</p>', $saved[0]['children'][0]['children'][0]['children'][1]['props']['html']);
    assert_same('Keep me', $saved[1]['name']);
    assert_same('mcp.saved', $site->layoutRevisions('pages', 'detail')[0]['event']);
    assert_true($site->layoutRevisions('pages', 'detail')[0]['created_by'] !== null);
});

test('mcp builder section patches preserve siblings and support explicit children replacement', function (): void {
    [$token, $layout] = comet_test_builder_setup();
    $before = (new SiteRepository())->layout('pages', 'detail');
    [, $updated] = comet_test_mcp_tool($token, 'update_section', [
        'collection' => 'pages', 'variant' => 'detail', 'revision' => $layout['revision'], 'section_id' => 'hero',
        'patch' => ['name' => 'Hero', 'props' => ['width' => 'full'], 'style' => ['padding_y' => 'xl']],
    ]);
    $saved = (new SiteRepository())->layout('pages', 'detail');
    assert_same($before[1], $saved[1]);
    assert_same($before[0]['children'], $saved[0]['children']);
    assert_same('full', $saved[0]['props']['width']);
    [, $updated] = comet_test_mcp_tool($token, 'update_section', [
        'collection' => 'pages', 'variant' => 'detail', 'revision' => $updated['data']['revision'], 'section_id' => 'hero',
        'patch' => ['children' => [['kind' => 'row', 'children' => [['kind' => 'column', 'children' => [['kind' => 'heading']]]]]]],
    ]);
    $saved = (new SiteRepository())->layout('pages', 'detail');
    assert_same('full', $saved[0]['props']['width']);
    assert_matches('/^n[a-f0-9]+$/', $saved[0]['children'][0]['id']);
    assert_same('heading', $saved[0]['children'][0]['children'][0]['children'][0]['kind']);
    [, $updated] = comet_test_mcp_tool($token, 'update_section', [
        'collection' => 'pages', 'variant' => 'detail', 'revision' => $updated['data']['revision'], 'section_id' => 'hero', 'patch' => ['children' => []],
    ]);
    assert_same(1, $updated['data']['sections'][0]['node_count']);
});

test('mcp builder rejects stale revisions including intervening admin repository saves', function (): void {
    [$token, $layout] = comet_test_builder_setup();
    $site = new SiteRepository();
    $nodes = $site->layout('pages', 'detail');
    $nodes[1]['name'] = 'Concurrent human edit';
    $site->saveLayout('pages', 'detail', $nodes, [], 'saved', $layout['revision']);
    foreach (['set_layout' => ['sections' => []], 'update_section' => ['section_id' => 'hero', 'patch' => ['children' => []]], 'update_element_props' => ['element_id' => 'heading', 'props' => ['text' => 'Stale']]] as $tool => $extra) {
        [$response, $error] = comet_test_mcp_tool($token, $tool, ['collection' => 'pages', 'variant' => 'detail', 'revision' => $layout['revision']] + $extra);
        assert_true($response['result']['isError']);
        assert_same(409, $error['error']['status']);
        assert_true(str_contains($error['error']['recovery'][0], 'new revision'));
        assert_same($nodes, $site->layout('pages', 'detail'));
    }
    // Exercise the actual under-lock compare-and-save path independently of the early MCP check.
    assert_throws(\CometCMS\Core\ConflictException::class, fn() => $site->saveLayout('pages', 'detail', [], [], 'saved', $layout['revision']));
    assert_same($nodes, $site->layout('pages', 'detail'));
});

test('mcp builder validates inputs without silent normalization or persistence', function (): void {
    [$token, $layout] = comet_test_builder_setup();
    $base = ['collection' => 'pages', 'variant' => 'detail', 'revision' => $layout['revision']];
    $cases = [
        ['update_element_props', ['element_id' => 'heading', 'props' => ['typo' => 'x']]],
        ['update_element_props', ['element_id' => 'heading', 'props' => ['level' => 'h9']]],
        ['update_element_props', ['element_id' => 'heading', 'props' => ['level' => null]]],
        ['update_element_props', ['element_id' => 'heading', 'props' => [], 'style' => ['background_blur' => 99]]],
        ['update_element_props', ['element_id' => 'heading', 'props' => ['link' => ['type' => 'nonsense']]]],
        ['update_element_props', ['element_id' => 'body', 'props' => ['html' => '<script>alert(1)</script>']]],
        ['update_element_props', ['element_id' => 'heading', 'props' => 'bad']],
        ['update_element_props', ['element_id' => 'hero', 'props' => ['scroll_effect' => [['bad']]]]],
        ['update_element_props', ['element_id' => 'heading', 'props' => ['link' => ['type' => 'url', 'url' => ['bad']]]]],
        ['update_section', ['section_id' => 'hero', 'patch' => ['kind' => 'row']]],
        ['update_section', ['section_id' => 'hero', 'patch' => ['children' => [['kind' => 'heading']]]]],
        ['update_section', ['section_id' => 'hero', 'patch' => ['props' => ['width' => 'invalid']]]],
        ['update_section', ['section_id' => 'hero', 'patch' => ['name' => str_repeat('x', 121)]]],
        ['set_layout', ['sections' => [['id' => 'dup', 'kind' => 'section'], ['id' => 'dup', 'kind' => 'section']]]],
        ['set_layout', ['sections' => [['kind' => 'section', 'id' => null]]]],
        ['set_layout', ['sections' => [['kind' => 'section', 'props' => []], ['kind' => 'nope']]]],
        ['set_layout', ['sections' => [['kind' => 'section', 'extra' => true]]]],
        ['set_layout', ['sections' => null]],
        ['set_layout', ['sections' => ['bad' => ['kind' => 'section']]]],
        ['set_layout', ['sections' => [['kind' => 'section', 'children' => 'bad']]]],
    ];
    $before = (new SiteRepository())->layoutRevisions('pages', 'detail');
    foreach ($cases as [$tool, $args]) {
        [, $payload] = comet_test_mcp_tool($token, $tool, $base + $args);
        assert_same(422, $payload['error']['status'] ?? null, $tool . ': ' . json_encode($payload));
        assert_true(isset($payload['error']['details']['path']));
        assert_true(str_contains($payload['error']['recovery'][0], 'get_builder_schema'));
        assert_same($layout['revision'], comet_test_builder_layout($token)['revision']);
    }
    assert_same($before, (new SiteRepository())->layoutRevisions('pages', 'detail'));
});

test('mcp builder stable defaults can be edited and empty layouts are explicit', function (): void {
    $token = comet_test_builder_token();
    (new ContentTypeRepository())->save(['name' => 'pages']);
    $first = comet_test_builder_layout($token);
    assert_same($first, comet_test_builder_layout($token));
    [, $updated] = comet_test_mcp_tool($token, 'update_section', [
        'collection' => 'pages', 'variant' => 'detail', 'revision' => $first['revision'], 'section_id' => $first['sections'][0]['id'], 'patch' => ['props' => ['width' => 'full']],
    ]);
    assert_true(isset($updated['data']['revision']), json_encode($updated));
    [, $empty] = comet_test_mcp_tool($token, 'set_layout', ['collection' => 'pages', 'variant' => 'detail', 'revision' => $updated['data']['revision'], 'sections' => []]);
    assert_same([], $empty['data']['sections']);
    assert_same([], (new SiteRepository())->layout('pages', 'detail'));
    [, $unlisted] = comet_test_mcp_tool($token, 'get_layout', ['collection' => 'pages', 'variant' => 'index']);
    assert_true(isset($unlisted['error']) && str_contains(json_encode($unlisted), 'no list page'), 'List pages that are off do not exist for MCP either.');
    (new SiteRepository())->saveSettings(['routes' => ['pages' => ['index_enabled' => true]]]);
    [, $index] = comet_test_mcp_tool($token, 'get_layout', ['collection' => 'pages', 'variant' => 'index']);
    assert_true(count($index['data']['sections']) > 0);
});

test('mcp builder missing targets and revisions guide the next action', function (): void {
    [$token, $layout] = comet_test_builder_setup();
    foreach ([['get_section', ['section_id' => 'missing']], ['get_element', ['element_id' => 'missing']]] as [$tool, $extra]) {
        [, $payload] = comet_test_mcp_tool($token, $tool, ['collection' => 'pages', 'variant' => 'detail'] + $extra);
        assert_same(404, $payload['error']['status']);
        assert_true(str_contains($payload['error']['recovery'][0], 'get_layout'));
    }
    [, $payload] = comet_test_mcp_tool($token, 'get_layout', ['collection' => 'missing', 'variant' => 'detail']);
    assert_same(404, $payload['error']['status']);
    assert_true(str_contains($payload['error']['recovery'][0], 'list_content_types'));
    foreach ([[], ['revision' => ''], ['revision' => null]] as $extra) {
        [, $payload] = comet_test_mcp_tool($token, 'set_layout', ['collection' => 'pages', 'variant' => 'detail', 'sections' => []] + $extra);
        assert_same(422, $payload['error']['status']);
    }
    assert_same($layout, comet_test_builder_layout($token));
});


test('mcp builder preserves localized props and bindings during partial edits and resets style defaults', function (): void {
    (new \CometCMS\Localization\LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    [$token, $layout] = comet_test_builder_setup();
    $target = ['collection' => 'pages', 'variant' => 'detail', 'element_id' => 'heading'];
    [, $updated] = comet_test_mcp_tool($token, 'update_element_props', $target + [
        'revision' => $layout['revision'], 'props' => ['text' => ['type' => 'localized', 'values' => ['en' => 'Hello', 'de' => 'Hallo']]],
        'style' => ['align' => 'center'],
    ]);
    assert_true(isset($updated['data']), json_encode($updated));
    $localized = $updated['data']['element']['props']['text'];
    [, $updated] = comet_test_mcp_tool($token, 'update_element_props', $target + [
        'revision' => $updated['data']['revision'], 'props' => ['level' => 'h3'], 'style' => ['align' => ''],
    ]);
    assert_same($localized, $updated['data']['element']['props']['text']);
    assert_false(isset($updated['data']['element']['style']['align']));
    [, $updated] = comet_test_mcp_tool($token, 'update_element_props', $target + [
        'revision' => $updated['data']['revision'], 'props' => ['text' => \CometCMS\Site\SmartValue::reference('title')],
    ]);
    assert_same('reference', $updated['data']['element']['props']['text']['type']);
});

test('mcp builder tools read and assign a layout header and footer', function (): void {
    [$token] = comet_test_builder_setup();
    $layout = comet_test_builder_layout($token);
    assert_same(['header' => 'default', 'footer' => 'default'], $layout['frame']);
    assert_same(['default', 'none'], $layout['frame_options']);

    [, $saved] = comet_test_mcp_tool($token, 'set_layout_frame', ['collection' => 'pages', 'variant' => 'detail', 'header' => 'none']);
    assert_same(['header' => 'none', 'footer' => 'default'], $saved['data']['frame'], json_encode($saved));
    assert_same(['pages' => ['detail' => ['header' => 'none']]], (new SiteRepository())->frames());

    [$failed] = comet_test_mcp_tool($token, 'set_layout_frame', ['collection' => 'pages', 'variant' => 'detail', 'footer' => 'no-such-component']);
    assert_true($failed['result']['isError'] ?? false, json_encode($failed));
    assert_same(['pages' => ['detail' => ['header' => 'none']]], (new SiteRepository())->frames());
});
