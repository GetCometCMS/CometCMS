<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\ConflictException;
use CometCMS\Core\ValidationException;
use CometCMS\Localization\LanguageSettings;
use CometCMS\Localization\LocalizedValue;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Storage\JsonStore;

function comet_localization_setup(): ContentRepository
{
    (new LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    (new ContentTypeRepository())->save(['name' => 'articles', 'api_enabled' => true, 'fields' => [
        'intro' => ['type' => 'textarea'], 'sku' => ['type' => 'text', 'localized' => false],
        'enabled' => ['type' => 'boolean'], 'quantity' => ['type' => 'number'],
    ]]);
    return ContentRepository::make();
}

function comet_localization_user(): array { return ['id' => 'writer']; }

test('legacy workspace choices and entry translations survive the localization upgrade', function (): void {
    unlink(comet_test_workspace_path() . '/languages.json');
    $typeStore = new JsonStore(comet_test_workspace_path() . '/content-types');
    $typeStore->write(['name' => 'pages', 'locales' => ['de', 'en'], 'default_locale' => 'de', 'fields' => [
        'intro' => ['type' => 'text'], 'sku' => ['type' => 'text', 'localized' => false],
    ]], 'pages');
    $languages = new LanguageSettings();
    assert_same(['locales' => ['de', 'en'], 'default_locale' => 'de'], $languages->all());
    assert_file_exists_at(comet_test_workspace_path() . '/languages.json');
    $store = new JsonStore(comet_test_workspace_path() . '/content');
    $legacy = ['id' => 'about', 'uid' => 'stable-about', 'collection' => 'pages', 'title' => 'Über uns', 'slug' => 'about',
        'intro' => 'Deutsch', 'sku' => 'ABC', 'status' => 'published', 'published_at' => '2026-01-01T00:00:00Z',
        'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z',
        'translations' => ['de' => ['title' => 'Über uns', 'intro' => 'Deutsch'], 'en' => ['title' => 'About', 'intro' => 'English', 'sku' => 'OLD']],
    ];
    $store->write($legacy, 'pages', 'about');
    $content = ContentRepository::make();
    $entry = $content->find('pages', 'about');
    assert_same('Über uns', $entry['title']);
    assert_same('English', $content->resolveLocaleFields($entry, 'en')['intro']);
    assert_same('ABC', $content->resolveLocaleFields($entry, 'en')['sku']);
    assert_true($content->isPubliclyVisible($entry, 'en'));
    assert_same('about', $content->resolveLocaleFields($entry, 'en')['slug']);
    assert_same($entry['revision'], $content->find('pages', 'about')['revision']);
    assert_same($legacy, $store->read('pages', 'about'));
    assert_throws(ValidationException::class, fn() => $languages->save(['locales' => ['de'], 'default_locale' => 'de']));
    $saved = $content->save('pages', ['locale' => 'en', 'intro' => 'Updated', 'revision' => $entry['revision']], comet_localization_user(), 'about');
    assert_same('stable-about', $saved['uid']);
    assert_same('Deutsch', $content->find('pages', 'about')['intro']);
    $raw = $store->read('pages', 'about');
    assert_false(array_key_exists('translations', $raw));
    assert_same('Updated', $raw['values']['intro']['values']['en']);
    assert_same('published', $raw['publication']['en']['status']);
});

test('legacy flat entries retain field values publication and identity when edited', function (): void {
    (new ContentTypeRepository())->save(['name' => 'pages', 'fields' => ['body' => ['type' => 'text']]]);
    $store = new JsonStore(comet_test_workspace_path() . '/content');
    $store->write(['id' => 'about', 'collection' => 'pages', 'title' => 'About', 'slug' => 'about', 'body' => 'Original body',
        'status' => 'published', 'created_at' => '2025-01-01T00:00:00Z', 'author_id' => 'author'], 'pages', 'about');
    $content = ContentRepository::make();
    $entry = $content->find('pages', 'about');
    assert_same('Original body', $entry['body']);
    assert_true($content->isPubliclyVisible($entry, 'en'));
    assert_false($content->isPubliclyVisible($entry, 'de'));
    $id = $content->stableId($entry);
    $saved = $content->save('pages', ['body' => 'Updated body', 'revision' => $entry['revision']], comet_localization_user(), 'about');
    assert_same($id, $content->stableId($saved));
    assert_same('About', $saved['title']);
    assert_same('published', $saved['status']);
    assert_same('author', $saved['author_id']);
    assert_same('Updated body', $store->read('pages', 'about')['values']['body']['values']['en']);
});

test('website and content types use workspace languages without storing copies', function (): void {
    $languages = new LanguageSettings();
    $languages->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    $types = new ContentTypeRepository();
    $types->save(['name' => 'inherited']);
    assert_false(array_key_exists('inherit_locales', $types->find('inherited')));
    assert_same(['en', 'de'], $types->find('inherited')['locales']);
    // Ordinary schema edits must not store a copy of the workspace language list.
    $types->save([...$types->find('inherited'), 'label' => 'Renamed']);
    $raw = (new JsonStore(comet_test_workspace_path() . '/content-types'))->read('inherited');
    assert_false(array_key_exists('locales', $raw));
    assert_false(array_key_exists('default_locale', $raw));
    assert_same(['en', 'de'], $types->find('fallback')['locales']);

    // Previously saved website subsets no longer override workspace settings.
    (new JsonStore(comet_test_workspace_path() . '/site'))->write(['locales' => ['en'], 'default_locale' => 'en'], 'settings');
    assert_same(['en', 'de'], (new SiteRepository())->settings()['locales']);
    (new SiteRepository())->saveSettings(['locales' => ['en'], 'default_locale' => 'en']);
    $raw = (new JsonStore(comet_test_workspace_path() . '/site'))->read('settings');
    assert_false(array_key_exists('locales', $raw));
    assert_false(array_key_exists('default_locale', $raw));

    $languages->save(['locales' => ['en', 'de', 'fr'], 'default_locale' => 'de']);
    assert_same(['en', 'de', 'fr'], $types->find('inherited')['locales']);
    assert_same('de', $types->find('inherited')['default_locale']);
    $site = new SiteRepository();
    assert_same(['en', 'de', 'fr'], $site->settings()['locales']);
    assert_same('de', $site->settings()['default_locale']);
});

test('saved content type language overrides are ignored and removed on the next save', function (): void {
    $languages = new LanguageSettings();
    $languages->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    $store = new JsonStore(comet_test_workspace_path() . '/content-types');
    $store->write(['name' => 'limited', 'inherit_locales' => false, 'locales' => ['en'], 'default_locale' => 'en'], 'limited');
    $types = new ContentTypeRepository();
    assert_same(['en', 'de'], $types->find('limited')['locales']);
    assert_false(array_key_exists('inherit_locales', $types->find('limited')));
    $types->save([...$types->find('limited'), 'label' => 'Renamed']);
    $raw = $store->read('limited');
    foreach (['locales', 'default_locale', 'inherit_locales'] as $key) assert_false(array_key_exists($key, $raw));
    $languages->save(['locales' => ['en', 'de', 'fr'], 'default_locale' => 'de']);
    assert_same(['en', 'de', 'fr'], $types->find('limited')['locales']);
    assert_same('de', $types->find('limited')['default_locale']);
});

test('MCP schema tools use workspace languages and expose no language configuration', function (): void {
    (new LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    $token = comet_test_mcp_token([['actions' => ['schema.create', 'schema.update', 'schema.read'], 'resources' => ['workspace:default:schema:*']]]);
    [$tools] = comet_test_mcp_request($token, 'tools/list');
    foreach ($tools['result']['tools'] as $tool) {
        if (!in_array($tool['name'], ['create_content_type', 'update_content_type'], true)) continue;
        foreach (['locales', 'default_locale', 'inherit_locales'] as $key) assert_false(array_key_exists($key, $tool['inputSchema']['properties']));
    }
    [, $created] = comet_test_mcp_tool($token, 'create_content_type', ['name' => 'pages']);
    assert_same(['en', 'de'], $created['data']['locales']);
    (new LanguageSettings())->save(['locales' => ['en', 'de', 'fr'], 'default_locale' => 'de']);
    [, $updated] = comet_test_mcp_tool($token, 'update_content_type', ['collection' => 'pages', 'label' => 'Renamed']);
    assert_same(['en', 'de', 'fr'], $updated['data']['locales']);
    assert_same('de', $updated['data']['default_locale']);
});

test('workspace languages support translated pages without per-resource language setup', function (): void {
    (new LanguageSettings())->save(['locales' => ['en'], 'default_locale' => 'en']);
    // A schema saved with a single-language override must not hide workspace languages.
    (new JsonStore(comet_test_workspace_path() . '/content-types'))->write(['name' => 'pages', 'inherit_locales' => false, 'locales' => ['en']], 'pages');
    (new SiteRepository())->saveSettings(['routes' => ['pages' => ['enabled' => true]]]);
    $content = ContentRepository::make();
    $entry = $content->save('pages', ['title' => 'Hello', 'status' => 'published'], comet_localization_user());
    (new LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    $service = new SiteService(SiteContext::make(new SiteRepository()));
    assert_same([], $content->query('pages', ['locale' => 'de'])['data']);
    assert_same(404, $service->respond('/de/pages/hello', [])['status']);
    $content->save('pages', ['locale' => 'de', 'title' => 'Hallo', 'slug' => 'hallo', 'status' => 'published'], comet_localization_user(), $entry['id']);
    $service = new SiteService(SiteContext::make(new SiteRepository()));
    $response = $service->respond('/de/pages/hallo', []);
    assert_same(200, $response['status']);
    assert_true(str_contains($response['body'], 'Hallo'));
    assert_true(str_contains($response['body'], 'hreflang="de"'));
    assert_true(str_contains($service->respond('/sitemap.xml', [])['body'], '/de/pages/hallo'));
    assert_true(str_contains($service->previewEntry('pages', $entry['uid'], 'de'), 'Hallo'));
});

test('localized values distinguish absence from intentionally empty values and track source review', function (): void {
    assert_same('shared', LocalizedValue::state('Source', 'en', 'en'));
    assert_same('inherited', LocalizedValue::state('Source', 'de', 'en'));
    foreach (['', null, false, 0, []] as $empty) {
        $value = LocalizedValue::set(null, 'en', 'Source', 'en');
        assert_same('Source', LocalizedValue::resolve($value, 'de', 'en'));
        assert_same('inherited', LocalizedValue::state($value, 'de', 'en'));
        $value = LocalizedValue::set($value, 'de', $empty, 'en');
        assert_same($empty, LocalizedValue::resolve($value, 'de', 'en'));
        assert_same('translated', LocalizedValue::state($value, 'de', 'en'));
        $value = LocalizedValue::set($value, 'en', 'Changed', 'en');
        assert_same('review', LocalizedValue::state($value, 'de', 'en'));
        assert_same($empty, LocalizedValue::resolve($value, 'de', 'en'));
        assert_same('Changed', LocalizedValue::resolve(LocalizedValue::remove($value, 'de'), 'de', 'en'));
    }
    assert_throws(ValidationException::class, fn() => LocalizedValue::normalize(['type' => 'localized', 'values' => ['xx' => 'bad']], strval(...), ['en']));
});

test('entries store each localized value once and locale writes preserve siblings and shared fields', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['locale' => 'en', 'title' => 'About', 'slug' => 'about', 'intro' => 'Source', 'sku' => 'ABC', 'status' => 'published'], comet_localization_user());
    $translated = $content->save('articles', ['locale' => 'de', 'title' => 'Über uns', 'slug' => 'ueber-uns', 'revision' => $entry['revision']], comet_localization_user(), $entry['id']);
    assert_same('Über uns', $translated['title']);
    assert_same('ABC', $translated['sku']);
    assert_same('About', $content->find('articles', $entry['id'])['title']);
    $raw = (new JsonStore(comet_test_workspace_path() . '/content'))->read('articles', $entry['id']);
    assert_false(array_key_exists('title', $raw));
    assert_false(array_key_exists('translations', $raw));
    assert_false(array_key_exists('status', $raw));
    assert_same('ABC', $raw['values']['sku']);
    assert_false(array_key_exists('de', $raw['values']['intro']['values']));
    assert_same('Source', $content->resolveLocaleFields($translated, 'de')['intro']);
    assert_throws(ValidationException::class, fn() => $content->save('articles', ['locale' => 'de', 'sku' => 'Changed'], comet_localization_user(), $entry['id']));
    assert_throws(ValidationException::class, fn() => $content->save('articles', ['locale' => 'de', 'values' => $raw['values']], comet_localization_user(), $entry['id']));
    assert_throws(ValidationException::class, fn() => $content->save('articles', ['locale' => 'xx', 'title' => 'Invalid'], comet_localization_user(), $entry['id']));
    assert_throws(ConflictException::class, fn() => $content->save('articles', ['locale' => 'en', 'title' => 'Stale', 'revision' => $entry['revision']], comet_localization_user(), $entry['id']));
});

test('publication is independent by language and public fallback never reveals drafts', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['locale' => 'en', 'title' => 'Secret draft', 'intro' => 'Do not expose'], comet_localization_user());
    $entry = $content->save('articles', ['locale' => 'de', 'title' => 'Öffentlich', 'slug' => 'oeffentlich', 'status' => 'published'], comet_localization_user(), $entry['id']);
    assert_false($content->isPubliclyVisible($entry, 'en'));
    assert_true($content->isPubliclyVisible($entry, 'de'));
    assert_same([], $content->query('articles', ['locale' => 'en'])['data']);
    assert_null($content->query('articles', ['locale' => 'de'])['data'][0]['intro']);
    assert_same('Do not expose', $content->query('articles', ['locale' => 'de'], true)['data'][0]['intro']);
    $content->save('articles', ['locale' => 'en', 'status' => 'published'], comet_localization_user(), $entry['id']);
    assert_same('Do not expose', $content->query('articles', ['locale' => 'de'])['data'][0]['intro']);
});

test('resetting a translated field removes the override without materializing fallback content', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['title' => 'Hello', 'intro' => 'English'], comet_localization_user());
    $content->save('articles', ['locale' => 'de', 'intro' => 'Deutsch'], comet_localization_user(), $entry['id']);
    $entry = $content->save('articles', ['locale' => 'de', 'reset_fields' => ['intro']], comet_localization_user(), $entry['id']);
    assert_same('English', $entry['intro']);
    assert_false(array_key_exists('de', $entry['values']['intro']['values']));
});

test('website routes localized slugs and paths and only advertises published entry variants', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['title' => 'About', 'slug' => 'about', 'status' => 'published'], comet_localization_user());
    $content->save('articles', ['locale' => 'de', 'title' => 'Über uns', 'slug' => 'ueber-uns'], comet_localization_user(), $entry['id']);
    $site = new SiteRepository();
    $site->saveSettings(['locales' => ['en', 'de'], 'routes' => ['articles' => ['enabled' => true,
        'pattern' => ['type' => 'localized', 'values' => ['en' => '/articles/{slug}', 'de' => '/artikel/{slug}']],
        'index_path' => ['type' => 'localized', 'values' => ['en' => '/articles', 'de' => '/artikel']],
    ]]]);
    $context = SiteContext::make($site);
    assert_same(404, (new SiteService($context))->respond('/de/artikel/ueber-uns', [])['status']);
    $entry = $content->save('articles', ['locale' => 'de', 'status' => 'published'], comet_localization_user(), $entry['id']);
    $context = SiteContext::make($site);
    $response = (new SiteService($context))->respond('/de/artikel/ueber-uns', []);
    assert_same(200, $response['status']);
    assert_true(str_contains($response['body'], 'hreflang="en"'));
    assert_same('/de/artikel/ueber-uns', $context->entryUrl('articles', $entry, 'de'));
    assert_same('/articles/about', $context->entryUrl('articles', $entry, 'en'));
    assert_same(404, (new SiteService(SiteContext::make($site)))->respond('/de/artikel/about', [])['status']);
    $sitemap = (new SiteService(SiteContext::make($site)))->respond('/sitemap.xml', [])['body'];
    assert_true(str_contains($sitemap, '/de/artikel/ueber-uns'));
    assert_false(str_contains($sitemap, '/de/articles/about'));
});

test('final localized URLs reject collisions between collection entries and fixed pages', function (): void {
    $content = comet_localization_setup();
    (new ContentTypeRepository())->save(['name' => 'contact', 'singleton' => true]);
    $site = new SiteRepository();
    $site->saveSettings(['routes' => ['articles' => ['enabled' => true, 'pattern' => '/{slug}', 'index_path' => '/articles'], 'contact' => ['enabled' => true, 'pattern' => '/contact']]]);
    assert_throws(ValidationException::class, fn() => $content->save('articles', ['title' => 'Contact', 'slug' => 'contact'], comet_localization_user()));
});

test('builder localizes sanitized rich text and dynamic templates before binding evaluation', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['title' => 'Hello', 'status' => 'published'], comet_localization_user());
    $content->save('articles', ['locale' => 'de', 'title' => 'Hallo', 'status' => 'published'], comet_localization_user(), $entry['id']);
    $site = new SiteRepository();
    $site->saveSettings(['routes' => ['articles' => ['enabled' => true]]]);
    $text = ['type' => 'localized', 'values' => [
        'en' => '<p>English</p>',
        'de' => ['type' => 'template', 'parts' => ['<p><script>alert(1)</script>Grüße ', \CometCMS\Site\SmartValue::reference('title'), '</p>']],
    ]];
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('text', ['html' => $text])])])])];
    $site->saveLayout('articles', 'detail', $nodes);
    $response = (new SiteService(SiteContext::make($site)))->respond('/de/articles/hallo', []);
    assert_same(200, $response['status']);
    assert_true(str_contains($response['body'], 'Grüße Hallo'));
    assert_false(str_contains($response['body'], '<script>alert(1)</script>'));
    $raw = $site->layout('articles', 'detail');
    assert_same(2, count($raw[0]['children'][0]['children'][0]['children'][0]['props']['html']['values']));
    $revision = LocalizedValue::hash($raw);
    $site->saveLayout('articles', 'detail', [], [], 'saved', $revision);
    assert_throws(ConflictException::class, fn() => $site->saveLayout('articles', 'detail', $raw, [], 'saved', $revision));
});

test('language settings are workspace owned and per-type language input cannot override them', function (): void {
    comet_localization_setup();
    assert_same(['en', 'de'], (new SiteRepository())->availableLocales());
    $types = new ContentTypeRepository();
    $types->save(['name' => 'pages', 'locales' => ['en', 'fr'], 'default_locale' => 'fr', 'inherit_locales' => false]);
    assert_same(['en', 'de'], $types->find('pages')['locales']);
    assert_same('en', $types->find('pages')['default_locale']);
    assert_throws(ValidationException::class, fn() => ContentRepository::make()->query('pages', ['locale' => 'fr']));
    (new LanguageSettings())->save(['locales' => ['de'], 'default_locale' => 'de']);
    assert_same(['de'], (new ContentTypeRepository())->find('articles')['locales']);
    assert_same('de', (new ContentTypeRepository())->find('articles')['default_locale']);
});

test('form text translations are independent of shared submission fields and select option values', function (): void {
    comet_localization_setup();
    $label = ['type' => 'localized', 'values' => ['en' => 'Your name', 'de' => 'Ihr Name']];
    (new ContentTypeRepository())->save(['name' => 'inquiries', 'allow_submissions' => true, 'fields' => [
        'name' => ['type' => 'text', 'label' => $label, 'localized' => false, 'required' => true],
        'topic' => ['type' => 'select', 'localized' => false, 'options' => ['general' => ['type' => 'localized', 'values' => ['en' => 'General', 'de' => 'Allgemein']]]],
    ], 'submission_settings' => ['submit_label' => ['type' => 'localized', 'values' => ['en' => 'Send now', 'de' => 'Jetzt senden']]]]);
    $site = new SiteRepository();
    $forms = new \CometCMS\Site\FormRepository($site);
    $form = $forms->all()[0];
    $inputs = $forms->inputs($form, 'de');
    assert_same('Ihr Name', $inputs[0]['label']);
    assert_same(['general' => 'Allgemein'], $inputs[1]['config']['options']);
    $context = SiteContext::make($site);
    $context->locale = 'de';
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('form', ['form' => $form['id']])])])])];
    $html = (new \CometCMS\Site\NodeRenderer($context))->render($nodes);
    assert_true(str_contains($html, 'Ihr Name'));
    assert_true(str_contains($html, 'Jetzt senden'));
    assert_true(str_contains($html, 'value="general"'));
    $result = (new \CometCMS\Site\FormSubmission($site, $forms, ContentRepository::make()))->submit($form, ['fields' => ['name' => 'Ada', 'topic' => 'general']], 'test-visitor', 'de');
    assert_same(\CometCMS\Site\FormSubmission::SENT, $result['status']);
    $saved = ContentRepository::make()->all('inquiries')[0];
    assert_same('Ada', $saved['name']);
    assert_same('general', $saved['topic']);
});

test('localized reusable components and revisions retain every language and source review state', function (): void {
    comet_localization_setup();
    $site = new SiteRepository();
    $text = LocalizedValue::set(LocalizedValue::set(null, 'en', 'Contact us', 'en'), 'de', 'Kontakt', 'en');
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('button', ['label' => $text])])])])];
    $component = $site->createComponent('Contact', $nodes);
    $copy = LayoutSchema::withNewIds($component['nodes']);
    assert_true($copy[0]['id'] !== $nodes[0]['id']);
    assert_same($text['values'], $copy[0]['children'][0]['children'][0]['children'][0]['props']['label']['values']);
    $site->saveComponent($component['id'], $copy);
    $revision = $site->componentRevisions($component['id'])[0];
    $site->restoreComponentRevision($component['id'], $revision['id']);
    $saved = $site->component($component['id'])['nodes'][0]['children'][0]['children'][0]['children'][0]['props']['label'];
    assert_same('Kontakt', LocalizedValue::resolve($saved, 'de', 'en'));
    assert_same('translated', LocalizedValue::state($saved, 'de', 'en'));
});

test('locale restricted grants cannot authorize whole resource writes or another language', function (): void {
    $permissions = new \CometCMS\Auth\PermissionService();
    $user = ['_principal_type' => 'token', 'permissions' => [['actions' => ['content.update', 'content.publish', 'site.manage'], 'resources' => ['*'], 'conditions' => ['locales' => ['de']]]]];
    assert_true($permissions->allows($user, 'content.update', ['locale' => 'de']));
    assert_false($permissions->allows($user, 'content.update', ['locale' => 'en']));
    assert_false($permissions->allows($user, 'content.update'));
    assert_false($permissions->allows($user, 'site.manage'));
});

test('reusing a renamed slug cannot overwrite a stable entry or its links', function (): void {
    $content = comet_localization_setup();
    $first = $content->save('articles', ['title' => 'Original', 'slug' => 'original'], comet_localization_user());
    $content->save('articles', ['slug' => 'renamed'], comet_localization_user(), $first['id']);
    $second = $content->save('articles', ['title' => 'Replacement', 'slug' => 'original'], comet_localization_user());
    assert_true($second['id'] !== $first['id']);
    assert_same('Original', $content->findByIdentifier('articles', $first['uid'])['title']);
    assert_same('Replacement', $content->findByIdentifier('articles', 'original')['title']);
});

test('MCP enforces locale permissions and returns conflicts while preserving other languages', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['title' => 'English', 'status' => 'published'], comet_localization_user());
    $token = comet_test_mcp_token([['actions' => ['content.update', 'content.publish'], 'resources' => ['content:articles:*'], 'conditions' => ['locales' => ['de']]]]);
    [, $saved] = comet_test_mcp_tool($token, 'update_entry', ['collection' => 'articles', 'identifier' => $entry['uid'], 'entry' => ['locale' => 'de', 'title' => 'Deutsch', 'status' => 'published', 'revision' => $entry['revision']]]);
    assert_same('Deutsch', $saved['data']['title']);
    assert_same('de', $saved['data']['locale']);
    [, $denied] = comet_test_mcp_tool($token, 'update_entry', ['collection' => 'articles', 'identifier' => $entry['uid'], 'entry' => ['locale' => 'en', 'title' => 'Not allowed']]);
    assert_same(403, $denied['error']['status']);
    [, $conflict] = comet_test_mcp_tool($token, 'update_entry', ['collection' => 'articles', 'identifier' => $entry['uid'], 'entry' => ['locale' => 'de', 'title' => 'Stale', 'revision' => $entry['revision']]]);
    assert_same(409, $conflict['error']['status']);
    [, $whole] = comet_test_mcp_tool($token, 'update_entry', ['collection' => 'articles', 'identifier' => $entry['uid'], 'entry' => ['locale' => 'de', 'values' => ['title' => ['type' => 'localized', 'values' => ['en' => 'Attack']]]]]);
    assert_same(422, $whole['error']['status']);
    assert_same('English', $content->find('articles', $entry['id'])['title']);
});

test('menus schemas settings and media metadata reject stale localized maps', function (): void {
    comet_localization_setup();
    $types = new ContentTypeRepository();
    $schema = $types->find('articles');
    $types->save([...$schema, 'label' => 'Changed']);
    assert_throws(ConflictException::class, fn() => $types->save($schema));
    $site = new SiteRepository();
    $settings = $site->saveSettings([]);
    $site->saveSettings([...$settings, 'title' => 'Changed']);
    assert_throws(ConflictException::class, fn() => $site->saveSettings($settings));
    $menus = $site->menus();
    $revision = LocalizedValue::hash($menus);
    $site->saveMenus([], $revision);
    assert_throws(ConflictException::class, fn() => $site->saveMenus($menus, $revision));
    file_put_contents(comet_test_workspace_path() . '/media/example.txt', 'test');
    $media = new \CometCMS\Media\MediaRepository();
    $file = $media->find('example.txt');
    $alt = LocalizedValue::set(LocalizedValue::set(null, 'en', 'English', 'en'), 'de', 'Deutsch', 'en');
    $media->updateMeta('example.txt', $alt, '', $file['revision']);
    assert_throws(ConflictException::class, fn() => $media->updateMeta('example.txt', 'Stale', '', $file['revision']));
    assert_same('Deutsch', LocalizedValue::resolve($media->find('example.txt')['alt'], 'de', 'en'));
});

test('REST resolves a requested published language without exposing internal maps', function (): void {
    $output = comet_test_run_php(['-r', comet_api_controller_test_bootstrap_require_snippet() . <<<'PHP'
comet_test_reset_storage();
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'pages', 'api_enabled' => true, 'locales' => ['en', 'de']]);
$content = \CometCMS\Content\ContentRepository::make();
$entry = $content->save('pages', ['title' => 'About', 'slug' => 'about', 'status' => 'published'], ['id' => 'writer']);
$content->save('pages', ['locale' => 'de', 'title' => 'Über uns', 'slug' => 'ueber-uns', 'status' => 'published'], ['id' => 'writer'], $entry['id']);
$_GET = ['locale' => 'de'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/workspaces/default/content/pages/ueber-uns?locale=de';
$_SERVER['SCRIPT_NAME'] = '/index.php';
(new \CometCMS\Controllers\ApiController(new \CometCMS\Core\Http()))->useWorkspace('default')->contentShow('pages', 'ueber-uns');
PHP]);
    $body = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    assert_same('de', $body['data']['locale']);
    assert_same('Über uns', $body['data']['title']);
    assert_same('ueber-uns', $body['data']['slug']);
    assert_true(is_string($body['data']['revision']));
    assert_false(array_key_exists('values', $body['data']));
    assert_false(array_key_exists('publication', $body['data']));
});

test('builder previews advertise the same published language variants as live pages', function (): void {
    $content = comet_localization_setup();
    $entry = $content->save('articles', ['title' => 'About', 'status' => 'published'], comet_localization_user());
    $content->save('articles', ['locale' => 'de', 'title' => 'Über uns'], comet_localization_user(), $entry['id']);
    $site = new SiteRepository();
    $site->saveSettings(['routes' => ['articles' => ['enabled' => true]]]);
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('language_switcher')])])])];
    $site->saveLayout('articles', 'detail', $nodes);
    $context = SiteContext::make($site);
    $context->preview = $context->annotate = true;
    $type = (new ContentTypeRepository())->find('articles');
    $html = (new \CometCMS\Site\PageRenderer($context))->layoutPreview($type, 'detail', $content->find('articles', $entry['id']));
    assert_matches('/<a[^>]+hreflang="en"/', $html);
    assert_false((bool) preg_match('/<a[^>]+hreflang="de"/', $html));
    $content->save('articles', ['locale' => 'de', 'status' => 'published'], comet_localization_user(), $entry['id']);
    $html = (new \CometCMS\Site\PageRenderer($context))->layoutPreview($type, 'detail', $content->find('articles', $entry['id']));
    assert_matches('/<a[^>]+hreflang="de"/', $html);
});

test('MCP media details resolve translated text using the common resolver', function (): void {
    comet_localization_setup();
    file_put_contents(comet_test_workspace_path() . '/media/readme.txt', 'test');
    $media = new \CometCMS\Media\MediaRepository();
    $text = LocalizedValue::set(LocalizedValue::set(null, 'en', 'English', 'en'), 'de', 'Deutsch', 'en');
    $media->updateMeta('readme.txt', $text, $text);
    [, $result] = comet_test_mcp_tool(null, 'get_media_item', ['filename' => 'readme.txt', 'locale' => 'de']);
    assert_same('Deutsch', $result['data']['alt']);
    assert_same('Deutsch', $result['data']['title']);
});
