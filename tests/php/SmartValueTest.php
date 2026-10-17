<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\ValidationException;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SmartValue;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\Text;
use CometCMS\Storage\JsonStore;

function smart_test_state(): array
{
    $context = comet_site_test_context();
    return [$context, ['type' => $context->type('blog'), 'entry' => $context->findEntry('blog', 'hello')]];
}

test('smart values resolve current items singles stable entries and site information', function (): void {
    comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $smart = new SmartValue($context);
    assert_same('Writes code.', $smart->resolve(SmartValue::reference('bio') + [], ['entry' => $context->findEntry('authors', 'ada'), 'type' => $context->type('authors')])['value']);
    $related = SmartValue::reference('author');
    $related['path'][] = 'bio';
    assert_same('Writes code.', $smart->resolve($related, $state)['value']);
    $single = SmartValue::reference('intro', 'single') + ['collection' => 'about'];
    assert_same('We build things.', $smart->resolve($single, [])['value']);
    $author = $context->findEntry('authors', 'ada');
    $explicit = SmartValue::reference('bio', 'entry') + ['collection' => 'authors', 'entry' => $author['uid']];
    assert_same('Writes code.', $smart->resolve($explicit, [])['value']);
    assert_same(date('Y'), $smart->resolve(SmartValue::reference('year', 'site'), [])['value']);
    assert_same('Step', $smart->resolve(SmartValue::reference('heading', 'item'), ['row' => ['heading' => 'Step'], 'row_fields' => ['heading' => ['type' => 'text']]])['value']);
    assert_same('/blog/hello', $smart->resolve(SmartValue::reference('url'), $state)['value']);
});

test('smart references retain stable identity when selected entries are renamed', function (): void {
    comet_site_test_setup();
    $content = ContentRepository::make();
    $entry = $content->findByIdentifier('authors', 'ada');
    $ref = SmartValue::reference('bio', 'entry') + ['collection' => 'authors', 'entry' => $entry['uid']];
    $content->save('authors', [...$entry, 'slug' => 'ada-lovelace', 'bio' => 'Renamed author.'], comet_site_test_user(), 'ada');
    assert_same('Renamed author.', (new SmartValue(comet_site_test_context()))->resolve($ref, [])['value']);
});

test('smart references obey public visibility even when the root is a draft preview', function (): void {
    comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $context->preview = true;
    $smart = new SmartValue($context);
    foreach ([['blog', 'secret-draft'], ['notes', 'private-note'], ['blog', 'deleted']] as [$type, $id]) {
        $ref = SmartValue::reference('title', 'entry') + ['collection' => $type, 'entry' => $id];
        assert_null($smart->resolve($ref, $state)['value']);
    }
    $ref = SmartValue::reference('author');
    $ref['path'] = ['author', 'bio'];
    $state['type']['fields']['author']['multiple'] = true;
    assert_null($smart->resolve($ref, $state)['value']);
});

test('smart fallbacks preserve zero and false and do not recursively evaluate content', function (): void {
    comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $smart = new SmartValue($context);
    foreach ([0, false, '{{ site.title }} {site}'] as $value) {
        $state['type']['fields']['value'] = ['type' => is_bool($value) ? 'boolean' : 'text'];
        $state['entry']['value'] = $value;
        $ref = SmartValue::reference('value');
        $ref['filters'][] = ['name' => 'fallback', 'value' => 'fallback'];
        assert_same($value, $smart->resolve($ref, $state)['value']);
        $html = (new NodeRenderer($context))->render([LayoutSchema::node('text', ['html' => $ref])], $state);
        if (is_string($value)) assert_true(str_contains($html, $value));
    }
    $ref = SmartValue::reference('missing');
    $ref['filters'][] = ['name' => 'fallback', 'value' => 'Fallback'];
    $resolved = $smart->resolve($ref, $state);
    assert_same('Fallback', $resolved['value']);
    assert_true($resolved['missing']);
});

test('empty fields fall back without a warning and rich text falls back to markup', function (): void {
    comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $state['type']['fields']['intro'] = ['type' => 'html'];
    $state['type']['fields']['tagline'] = ['type' => 'text'];
    $state['entry']['intro'] = '';
    $smart = new SmartValue($context);

    $ref = SmartValue::reference('tagline');
    $ref['filters'][] = ['name' => 'fallback', 'value' => 'Our story'];
    $resolved = $smart->resolve($ref, $state);
    assert_same(['Our story', false], [$resolved['value'], $resolved['missing']], 'An existing empty field is resolved by its fallback.');

    $ref = SmartValue::reference('intro');
    $ref['filters'][] = ['name' => 'fallback', 'value' => '<p>We <strong>build</strong> things.</p>'];
    $context->annotate = true;
    $html = (new NodeRenderer($context))->render([LayoutSchema::node('text', ['html' => $ref])], $state);
    assert_true(str_contains($html, '<strong>build</strong>'), 'Rich text keeps the markup of its fallback.');
    assert_false(str_contains($html, 'c-placeholder-warning'));
});

test('smart rich text preserves whole values and escapes inline references', function (): void {
    comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $renderer = new NodeRenderer($context);
    $whole = $renderer->render([LayoutSchema::node('text', ['html' => SmartValue::reference('body')])], $state);
    assert_true(str_contains($whole, '<strong>bold</strong>'));
    assert_false(str_contains($whole, '<script'));
    $template = ['type' => 'template', 'parts' => ['<p>Title: ', SmartValue::reference('title'), '</p>']];
    $inline = $renderer->render([LayoutSchema::node('text', ['html' => $template])], $state);
    assert_true(str_contains($inline, '&lt;script&gt;'));
    assert_false(str_contains($inline, '<script'));
    $template['parts'] = ['<p>', SmartValue::reference('body'), '</p>'];
    assert_false(str_contains($renderer->render([LayoutSchema::node('text', ['html' => $template])], $state), '<strong>'));
});

test('smart schema rejects malformed envelopes attribute interpolation and unsupported filters', function (): void {
    foreach ([
        ['type' => 'invalid', 'text' => '{{ bad'],
        ['type' => 'reference', 'source' => 'query', 'path' => ['title']],
        ['type' => 'reference', 'source' => 'current', 'path' => ['a','b','c','d','e','f','g']],
        ['type' => 'reference', 'source' => 'current', 'path' => ['title'], 'filters' => [['name' => 'execute', 'value' => 'php']]],
        ['type' => 'template', 'parts' => ['<a href="', SmartValue::reference('title'), '">link</a>']],
    ] as $value) assert_throws(ValidationException::class, fn() => SmartValue::normalize($value, 'html'));
    $value = SmartValue::normalize(['type' => 'template', 'parts' => ['<p onclick="evil()">', SmartValue::reference('title'), '</p><script>bad()</script>']], 'html');
    assert_false(str_contains(json_encode($value), 'onclick'));
    assert_false(str_contains(json_encode($value), '<script'));
    assert_same('text', LayoutSchema::spec()['kinds']['heading']['props']['text']['binding']);
    assert_false(isset(LayoutSchema::spec()['style']['background']['binding']));
    assert_same('media', LayoutSchema::spec()['style']['background_image']['binding']);
    $bound = LayoutSchema::normalize([LayoutSchema::node('section', [], ['background_image' => SmartValue::reference('photo')])]);
    assert_same(SmartValue::reference('photo'), $bound[0]['style']['background_image']);
    assert_throws(ValidationException::class, fn() => LayoutSchema::normalize([LayoutSchema::node('section', [], ['background_image' => ['type' => 'reference', 'source' => 'query', 'path' => ['photo']]])]));
});

test('smart renderer shares previews lists repeaters and live resolution', function (): void {
    $site = comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $ref = SmartValue::reference('author');
    $ref['path'][] = 'bio';
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [
        LayoutSchema::node('heading', ['text' => $ref]),
        LayoutSchema::node('field', ['source' => SmartValue::reference('steps'), 'display' => 'items'], [], [LayoutSchema::node('text', ['html' => SmartValue::reference('heading', 'item')])]),
    ])])])];
    $site->saveLayout('blog', 'detail', $nodes);
    $live = (new PageRenderer(comet_site_test_context()))->entryPage($state['type'], $state['entry']);
    $context->preview = true;
    $context->annotate = true;
    $preview = (new PageRenderer($context))->layoutPreview($state['type'], 'detail', $state['entry']);
    foreach (['Writes code.', 'First step', 'Second step'] as $text) {
        assert_true(str_contains($live, $text));
        assert_true(str_contains($preview, $text));
    }
    assert_same(1, substr_count($preview, 'data-node="' . $nodes[0]['children'][0]['children'][0]['children'][1]['children'][0]['id'] . '"'));
});

test('incompatible designs use defaults without deleting data and old revisions cannot restore', function (): void {
    $site = comet_site_test_setup();
    $store = new JsonStore($site->workspace()->path('site'));
    $old = ['nodes' => [LayoutSchema::node('section')]];
    $store->write($old, 'layouts', 'blog', 'detail');
    $store->write($old, 'regions', 'footer');
    assert_true($site->needsLayoutUpgrade('blog', 'detail'));
    assert_true($site->needsRegionUpgrade('footer'));
    assert_same($old, $store->read('layouts', 'blog', 'detail'));
    assert_true(str_contains(json_encode($site->layout('blog', 'detail')), 'reference'));
    $revisions = new JsonStore($site->workspace()->path('revisions') . '/site');
    $revisions->write(['id' => 'old', 'snapshot' => $old], 'layouts', 'blog', 'detail', 'old');
    assert_null($site->restoreLayoutRevision('blog', 'detail', 'old'));
    assert_same([], $site->layoutRevisions('blog', 'detail'));
    $site->saveLayout('blog', 'detail', $site->layout('blog', 'detail'));
    assert_false($site->needsLayoutUpgrade('blog', 'detail'));
    assert_true(ContentRepository::make()->findByIdentifier('blog', 'hello') !== null);
    assert_same(LayoutSchema::FORMAT_VERSION, $store->read('layouts', 'blog', 'detail')['format_version']);
});

test('columns and column components resolve bound backgrounds while preserving responsive order', function (): void {
    $site = comet_site_test_setup();
    file_put_contents(comet_test_workspace_path() . '/media/photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>');
    [$context, $state] = smart_test_state();
    $state['type']['fields']['photo'] = ['type' => 'media'];
    $state['entry']['photo'] = ['photo.svg'];
    $column = LayoutSchema::node('column', ['span' => ['mobile' => 12, 'tablet' => 6]], ['background_image' => SmartValue::reference('photo')], [LayoutSchema::node('heading', ['text' => 'Content'])]);
    $component = $site->createComponent('Bound column', [$column], 'blog');
    $instance = LayoutSchema::node('component', ['component' => $component['id'], 'root' => 'column', 'span' => ['mobile' => 12, 'tablet' => 6]]);
    $layout = LayoutSchema::normalize([LayoutSchema::node('section', [], [], [LayoutSchema::node('row', ['reverse_mobile' => true], [], [$column, $instance])])]);
    foreach ([false, true] as $annotate) {
        $context->annotate = $annotate;
        $html = (new NodeRenderer($context))->render($layout, $state);
        assert_same(2, substr_count($html, 'background-image:'), 'Both columns resolve their media reference.');
        assert_same(2, substr_count($html, 'c-t-col-6'));
        assert_true(str_contains($html, '--c-column-order:0'));
        assert_true(str_contains($html, '--c-column-order:-1'));
        assert_false(str_contains($html, 'Unresolved content reference'));
    }
    (new \CometCMS\Media\MediaRepository())->updateVisibility('photo.svg', 'private');
    $html = (new NodeRenderer(comet_site_test_context($site)))->render($layout, $state);
    assert_false(str_contains($html, 'background-image:'), 'Bound column backgrounds never expose private media.');
});

test('smart media and links preserve types and reject unsafe or ambiguous values', function (): void {
    comet_site_test_setup();
    file_put_contents(comet_test_workspace_path() . '/media/photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>');
    [$context, $state] = smart_test_state();
    $state['type']['fields']['photo'] = ['type' => 'media'];
    $state['entry']['photo'] = ['photo.svg'];
    $ref = SmartValue::reference('photo');
    $renderer = new NodeRenderer($context);
    assert_true(str_contains($renderer->render([LayoutSchema::node('image', ['media' => $ref])], $state), '<img'));
    $state['type']['fields']['photo']['multiple'] = true;
    assert_false(str_contains($renderer->render([LayoutSchema::node('image', ['media' => $ref])], $state), '<img'));
    $state['type']['fields']['photo']['multiple'] = false;
    $nodes = [LayoutSchema::node('section', [], ['background_image' => $ref], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('text', ['html' => 'Body'])])])])];
    assert_true(str_contains($renderer->render($nodes, $state), 'background-image:'));
    assert_true(str_contains($renderer->render([LayoutSchema::node('text', ['html' => 'Body'], ['background_image' => $ref])], $state), 'background-image:'), 'Elements resolve bound backgrounds too.');
    (new \CometCMS\Media\MediaRepository())->updateVisibility('photo.svg', 'private');
    assert_false(str_contains((new NodeRenderer(comet_site_test_context()))->render([LayoutSchema::node('image', ['media' => $ref])], $state), '<img'));
    $link = ['type' => 'dynamic', 'value' => SmartValue::reference('url'), 'new_tab' => true];
    $html = $renderer->render([LayoutSchema::node('button', ['link' => $link, 'label' => SmartValue::reference('title')])], $state);
    assert_true(str_contains($html, 'href="/blog/hello"'));
    assert_true(str_contains($renderer->render([LayoutSchema::node('button', ['link' => SmartValue::reference('url')])], $state), 'href="/blog/hello"'));
    assert_true(str_contains($html, 'rel="noopener noreferrer"'));
    $state['type']['fields']['unsafe'] = ['type' => 'text'];
    $state['entry']['unsafe'] = 'javascript:alert(1)';
    $link['value'] = SmartValue::reference('unsafe');
    assert_false(str_contains($renderer->render([LayoutSchema::node('button', ['link' => $link])], $state), 'javascript:'));
});

test('smart references localize secondary entries and remain workspace scoped', function (): void {
    $types = new ContentTypeRepository();
    $types->save(['name' => 'company', 'singleton' => true, 'locales' => ['en', 'de'], 'default_locale' => 'en', 'fields' => ['intro' => ['type' => 'textarea']]]);
    $content = ContentRepository::make();
    $content->save('company', ['title' => 'Company', 'intro' => 'English', 'status' => 'published', 'locale' => 'en'], comet_site_test_user());
    $content->save('company', ['title' => 'Firma', 'intro' => 'Deutsch', 'status' => 'published', 'locale' => 'de'], comet_site_test_user(), 'company');
    $site = new SiteRepository();
    $site->saveSettings(['enabled' => true, 'locales' => ['en', 'de'], 'default_locale' => 'en']);
    $ref = SmartValue::reference('intro', 'single') + ['collection' => 'company'];
    $context = comet_site_test_context();
    $context->locale = 'de';
    assert_same('Deutsch', (new SmartValue($context))->resolve($ref, [])['value']);
    (new \CometCMS\Workspaces\WorkspaceRepository())->save(['slug' => 'another', 'label' => 'Another']);
    $workspace = new \CometCMS\Workspaces\WorkspaceContext('another');
    $other = new SiteRepository($workspace);
    $otherContext = \CometCMS\Site\SiteContext::make($other);
    assert_null((new SmartValue($otherContext))->resolve($ref, [])['value']);
});

test('smart relation traversal is bounded and formatting uses the existing date and word rules', function (): void {
    comet_site_test_setup();
    $types = new ContentTypeRepository();
    $types->save(['name' => 'people', 'fields' => ['next' => ['type' => 'relation', 'target' => 'people']]]);
    $content = ContentRepository::make();
    $content->save('people', ['title' => 'Ada', 'status' => 'published'], comet_site_test_user());
    $content->save('people', ['title' => 'Ada', 'next' => 'ada', 'status' => 'published'], comet_site_test_user(), 'ada');
    $context = comet_site_test_context();
    $state = ['type' => $context->type('people'), 'entry' => $context->findEntry('people', 'ada')];
    $ref = SmartValue::reference('title');
    $ref['path'] = ['next', 'next', 'next', 'next', 'next', 'title'];
    assert_same('Ada', (new SmartValue($context))->resolve(SmartValue::normalize($ref), $state)['value']);
    array_unshift($ref['path'], 'next');
    assert_throws(ValidationException::class, fn() => SmartValue::normalize($ref));
    assert_null((new SmartValue($context))->resolve($ref, $state)['value']);
    $ref = SmartValue::reference('title');
    $ref['filters'] = [['name' => 'truncate', 'value' => 2]];
    $state['entry']['title'] = 'One two three four';
    assert_same('One two…', (new SmartValue($context))->resolve($ref, $state)['value']);
    $state['entry']['amount'] = 1234.5;
    $state['type']['fields']['amount'] = ['type' => 'number'];
    assert_same(Text::number(1234.5, $context->htmlLang()), (new SmartValue($context))->text(SmartValue::reference('amount'), $state)['value']);
    $state['entry']['created_at'] = '2026-10-17T13:10:00Z';
    $ref = SmartValue::reference('created_at');
    $ref['filters'] = [['name' => 'date', 'value' => 'long']];
    assert_same(Text::date($state['entry']['created_at'], 'long', $context->htmlLang()), (new SmartValue($context))->resolve($ref, $state)['value']);
});

test('builder API returns validation errors for invalid smart content instead of server errors', function (): void {
    $seed = '(new \\CometCMS\\Content\\ContentTypeRepository())->save(["name" => "pages"]);';
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('text', ['html' => ['type' => 'invalid', 'text' => '{{ broken']])])])])];
    [$status, $json] = comet_admin_request('admin', $seed, 'PUT', '/admin/api/site/layouts/pages/detail',
        '(new \\CometCMS\\Controllers\\Admin\\SiteController(new \\CometCMS\\Core\\Http()))->updateLayout("pages", "detail");', ['nodes' => $nodes]);
    assert_same(422, $status);
    assert_same('validation_failed', $json['error']['code']);
});


test('new defaults use ordinary scalar elements and explicit item and site references', function (): void {
    $site = comet_site_test_setup();
    $nodes = $site->layout('blog', 'detail');
    $elements = $nodes[0]['children'][0]['children'][0]['children'];
    assert_same('heading', $elements[0]['kind']);
    assert_same(SmartValue::reference('title'), $elements[0]['props']['text']);
    $body = array_values(array_filter($elements, static fn(array $node): bool => $node['kind'] === 'text' && ($node['props']['html']['path'] ?? []) === ['body']));
    assert_same(1, count($body));
    $repeaters = array_values(array_filter($elements, static fn(array $node): bool => $node['kind'] === 'field' && ($node['props']['source']['path'] ?? []) === ['steps']));
    assert_same('item', $repeaters[0]['children'][0]['props']['text']['source']);
    $footer = json_encode($site->region('footer'));
    assert_true(str_contains($footer, '"source":"site"'));
    assert_false(str_contains($footer, '{year}'));
});


test('specific entry references require immutable UIDs rather than mutable slugs', function (): void {
    comet_site_test_setup();
    [$context, $state] = smart_test_state();
    $ref = SmartValue::reference('title', 'entry') + ['collection' => 'blog', 'entry' => 'hello'];
    assert_null((new SmartValue($context))->resolve($ref, $state)['value']);
    $ref['entry'] = $state['entry']['uid'];
    assert_same($state['entry']['title'], (new SmartValue($context))->resolve($ref, $state)['value']);
});

test('bound page URLs keep a single base path and locale prefix in links', function (): void {
    comet_site_test_setup();
    $context = SiteContext::make(new SiteRepository(), '/cms', 'https://example.test');
    $state = ['type' => $context->type('blog'), 'entry' => $context->findEntry('blog', 'hello')];
    $renderer = new NodeRenderer($context);
    $url = SmartValue::reference('url');

    $button = $renderer->render([LayoutSchema::node('button', ['label' => 'Read', 'link' => ['type' => 'dynamic', 'value' => $url, 'new_tab' => false]])], $state);
    assert_true(str_contains($button, 'href="/cms/blog/hello"'), $button);
    $heading = $renderer->render([LayoutSchema::node('heading', ['text' => 'Read', 'link' => $url])], $state);
    assert_true(str_contains($heading, 'href="/cms/blog/hello"'), $heading);
    assert_same('/cms/blog/hello', (new SmartValue($context))->resolve($url, $state)['value'], 'Text output shows the full path once.');

    $custom = ['type' => 'dynamic', 'value' => SmartValue::reference('title', 'site'), 'new_tab' => false];
    $context->site->saveSettings(['title' => '/about'] + $context->site->settings());
    $relative = (new NodeRenderer(SiteContext::make(new SiteRepository(), '/cms', 'https://example.test')))->render([LayoutSchema::node('button', ['label' => 'About', 'link' => $custom])], $state);
    assert_true(str_contains($relative, 'href="/cms/about"'), 'Site-relative field values still get the base path: ' . $relative);
});
