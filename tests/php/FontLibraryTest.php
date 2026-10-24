<?php

declare(strict_types=1);

use CometCMS\Backups\BackupService;
use CometCMS\Backups\RestoreService;
use CometCMS\Site\FontFiles;
use CometCMS\Site\FontInstaller;
use CometCMS\Site\FontRegistry;
use CometCMS\Site\Fontsource;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\Theme;
use CometCMS\Workspaces\WorkspaceContext;

function comet_font_bytes(): string
{
    $manifest = json_decode((string) file_get_contents(COMET_TEST_ROOT . '/tests/fixtures/fonts/manifest.json'), true);
    return (string) file_get_contents(COMET_TEST_ROOT . '/tests/fixtures/fonts/files/' . $manifest['families'][0]['faces'][0]['hash'] . '.woff2');
}

function comet_font_provider(array &$calls, bool &$fail, bool $variable = true, string $version = '1.2.3'): Fontsource
{
    return new Fontsource(static function (string $url) use (&$calls, &$fail, $variable, $version): string {
        $calls[] = $url;
        if (str_ends_with($url, '.woff2')) {
            if ($fail) {
                throw new RuntimeException('Simulated provider outage.');
            }
            return comet_font_bytes();
        }
        if (str_contains($url, '/version/')) {
            return json_encode($variable ? ['latest' => $version, 'latestVariable' => $version] : ['latest' => $version]);
        }
        if (str_ends_with($url, 'unicode.json')) {
            return json_encode(['latin' => 'U+0000-00FF', 'latin-ext' => 'U+0100-024F', 'greek' => 'U+0370-03FF']);
        }
        if (str_ends_with($url, 'metadata.json') || str_contains($url, '/fonts/example')) {
            return json_encode(['id' => 'example', 'family' => 'Example', 'category' => 'serif', 'subsets' => ['latin', 'latin-ext', 'greek'], 'defSubset' => 'latin', 'styles' => ['normal', 'italic'], 'variable' => $variable ? ['wght' => ['min' => 100, 'max' => 900]] : false]);
        }
        if (str_ends_with($url, '/fonts')) {
            return json_encode([['id' => 'example', 'family' => 'Example', 'type' => 'google', 'category' => 'serif', 'subsets' => ['latin'], 'defSubset' => 'latin']]);
        }
        if (str_ends_with($url, 'LICENSE')) {
            return 'Open font test licence';
        }
        if (str_ends_with($url, '.css')) {
            $italic = str_contains($url, 'italic');
            $css = '';
            foreach (['latin' => 'U+0000-00FF', 'latin-ext' => 'U+0100-024F', 'greek' => 'U+0370-03FF'] as $subset => $range) {
                foreach ([$italic ? 'italic' : 'normal'] as $style) {
                    $css .= '@font-face{font-family:"untrusted";font-style:' . $style . ';font-weight:' . ($variable ? '100 900' : '400') . ';src:url(./files/example-' . $subset . '-' . ($variable ? 'wght' : '400') . '-' . $style . '.woff2) format("woff2");' . ($variable ? 'unicode-range:' . $range . ';' : '') . '}';
                }
            }
            return $css;
        }
        throw new RuntimeException('Unexpected test URL: ' . $url);
    });
}

function comet_install_job(FontInstaller $installer, array $job): array
{
    while ($job['status'] === 'pending') {
        $job = $installer->step($job['id'], $job['cursor']);
    }
    return $job;
}

function comet_install_fixture_fonts(FontRegistry $fonts): void
{
    $root = COMET_TEST_ROOT . '/tests/fixtures/fonts';
    $manifest = json_decode((string) file_get_contents($root . '/manifest.json'), true);
    foreach ($manifest['families'] as $font) {
        foreach ($font['faces'] as $face) {
            $bytes = (string) file_get_contents($root . '/files/' . $face['hash'] . '.woff2');
            FontFiles::write($fonts->directory() . '/files/' . $face['hash'] . '.woff2', $bytes);
            FontFiles::json($fonts->directory() . '/files/' . $face['hash'] . '.json', ['hash' => $face['hash']]);
        }
        $fonts->save($font);
    }
}

test('empty workspaces have only system font stacks and no shipped website fonts', function (): void {
    $fonts = FontRegistry::active();
    assert_same([], $fonts->families());
    assert_same(Theme::FONT_STACKS, $fonts->stacks());
    assert_false(is_dir(COMET_APP . '/Site/assets/fonts'));
    assert_false(str_contains($fonts->css(), '@font-face'));
    assert_false(is_dir($fonts->directory()));
    assert_same('system', Theme::normalize([])['fonts']['body']);
});

test('installed fonts are licensed, content addressed, local and removable when unused', function (): void {
    $fonts = FontRegistry::active();
    comet_install_fixture_fonts($fonts);
    assert_same(3, count($fonts->families()));
    foreach ($fonts->families() as $font) {
        assert_same('fontsource', $font['source']);
        assert_true($font['available']);
        assert_true($font['license'] !== '');
        foreach ($font['faces'] as $face) {
            $path = $fonts->asset($face['hash']);
            assert_true($path !== null);
            assert_same($face['hash'], FontFiles::validate((string) file_get_contents($path)));
        }
    }
    $css = Theme::stylesheet(['fonts' => ['body' => 'fontsource-inter'], 'headings' => ['h1' => ['font' => 'fontsource-lora']]], $fonts, '/cms');
    assert_true(str_contains($css, '--font-body:"comet-fontsource-inter", sans-serif;'));
    assert_true(str_contains($css, 'h1{font-family:"comet-fontsource-lora", serif;'));
    assert_true(str_contains($css, 'font-display:swap'));
    assert_true(str_contains($css, 'unicode-range:'));
    assert_true(str_contains($css, '/cms/_site/default/fonts/'));
    assert_false(str_contains($css, 'fonts.googleapis.com'));
    assert_false(str_contains($css, 'cdn.jsdelivr.net'));
    $fonts->delete('fontsource-inter');
    assert_false(isset($fonts->families()['fontsource-inter']));
});

test('font choices retain missing registry IDs safely across themes and layouts', function (): void {
    $id = 'custom-' . str_repeat('a', 24);
    assert_same($id, Theme::normalize(['fonts' => ['body' => $id]])['fonts']['body']);
    assert_true(str_contains(Theme::stylesheet(['fonts' => ['body' => $id]]), '--font-body:' . Theme::FONT_STACKS['system']));
    $tree = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('heading', [], ['font_family' => $id])])])])];
    assert_same($id, LayoutSchema::normalize($tree)[0]['children'][0]['children'][0]['children'][0]['style']['font_family']);
    assert_same('system', Theme::normalize(['fonts' => ['body' => 'fontsource-x"; color:red']])['fonts']['body']);
    assert_false(FontRegistry::validId(['fontsource-inter']));
    assert_null(FontRegistry::active()->asset('../secret'));
});

test('catalogue installs are pinned, retryable, idempotent and atomically activated', function (): void {
    $calls = []; $fail = false;
    $fonts = FontRegistry::active();
    $installer = new FontInstaller($fonts, comet_font_provider($calls, $fail));
    $job = $installer->prepare('example', []);
    assert_same(4, $job['total']);
    assert_false(isset($fonts->families()['fontsource-example']));
    assert_false((bool) array_filter($calls, static fn(string $url): bool => str_ends_with($url, '.woff2')));
    $fail = true;
    assert_throws(RuntimeException::class, static fn() => $installer->step($job['id'], 0));
    assert_same(0, $installer->jobs()[0]['cursor']);
    $fail = false;
    $first = $installer->step($job['id'], 0);
    $before = count($calls);
    assert_same($first, $installer->step($job['id'], 0));
    assert_same($before, count($calls));
    assert_false(isset($fonts->families()['fontsource-example']));
    $complete = comet_install_job($installer, $first);
    assert_same('complete', $complete['status']);
    $family = $fonts->families()['fontsource-example'];
    assert_same('1.2.3', $family['version']);
    assert_same('Open font test licence', $family['license']);
    assert_same(['latin', 'latin-ext'], $family['subsets']);
    assert_true($family['available']);
    assert_same([], $installer->jobs());
    foreach (array_filter($calls, static fn(string $url): bool => str_contains($url, 'cdn.jsdelivr.net')) as $url) {
        assert_true(str_contains($url, '@1.2.3/'));
    }
    assert_throws(InvalidArgumentException::class, static fn() => $installer->prepare('example', []));
});

test('explicit font updates preserve the old family until all files are ready', function (): void {
    $calls = []; $fail = false;
    $fonts = FontRegistry::active();
    $old = new FontInstaller($fonts, comet_font_provider($calls, $fail));
    comet_install_job($old, $old->prepare('example', ['latin']));
    $fingerprint = $fonts->fingerprint();
    $new = new FontInstaller($fonts, comet_font_provider($calls, $fail, true, '2.0.0'));
    $job = $new->prepare('example', ['greek'], true);
    $job = $new->step($job['id'], 0);
    assert_same($fingerprint, $fonts->fingerprint());
    assert_same('1.2.3', $fonts->families()['fontsource-example']['version']);
    comet_install_job($new, $job);
    assert_same('2.0.0', $fonts->families()['fontsource-example']['version']);
    assert_true($fingerprint !== $fonts->fingerprint());
    assert_true(str_contains($fonts->css(), 'U+0370-03FF'));
});

test('cancelled and competing font installations cannot overwrite a committed family', function (): void {
    $calls = []; $fail = false;
    $fonts = FontRegistry::active();
    $installer = new FontInstaller($fonts, comet_font_provider($calls, $fail));
    $cancelled = $installer->prepare('example', []);
    $installer->step($cancelled['id'], 0);
    assert_same('cancelled', $installer->cancel($cancelled['id'])['status']);
    assert_throws(InvalidArgumentException::class, static fn() => $installer->step($cancelled['id'], 1));
    $first = $installer->prepare('example', []);
    $second = $installer->prepare('example', []);
    comet_install_job($installer, $first);
    assert_throws(InvalidArgumentException::class, static fn() => comet_install_job($installer, $second));
    assert_same('1.2.3', $fonts->families()['fontsource-example']['version']);
});

test('provider CSS selects exact subsets and supports static normal and italic faces', function (): void {
    $calls = []; $fail = false;
    $provider = comet_font_provider($calls, $fail, false);
    $family = $provider->prepare('example', ['latin']);
    assert_same(2, count($family['faces']));
    assert_same(['normal', 'italic'], array_column($family['faces'], 'style'));
    assert_same(['latin', 'latin'], array_column($family['faces'], 'subset'));
    assert_same(['U+0000-00FF', 'U+0000-00FF'], array_column($family['faces'], 'unicode_range'));
    assert_throws(InvalidArgumentException::class, static fn() => $provider->get('https://example.com/font.woff2'));
    assert_throws(InvalidArgumentException::class, static fn() => $provider->get('https://cdn.jsdelivr.net/npm/@fontsource/example@latest/latin.css'));
    assert_throws(InvalidArgumentException::class, static fn() => $provider->prepare('example', ['unsupported']));
    assert_throws(RuntimeException::class, static fn() => FontFiles::parse('@font-face{src:url(https://evil.test/a.woff2)}', ['latin']));
});

test('custom font uploads validate headers, metadata and conflicting faces before activation', function (): void {
    $fonts = FontRegistry::active();
    $installer = new FontInstaller($fonts);
    $bytes = comet_font_bytes();
    assert_throws(InvalidArgumentException::class, static fn() => FontFiles::validate('wOF2' . str_repeat('x', 100)));
    assert_throws(InvalidArgumentException::class, static fn() => FontFiles::validate($bytes . 'trailing'));
    assert_throws(InvalidArgumentException::class, static fn() => FontFiles::validate(str_repeat('x', FontFiles::MAX_FILE + 1)));
    $face = ['bytes' => $bytes, 'style' => 'normal', 'weight' => [100, 900]];
    $family = $installer->upload('Brand "Font"', 'sans-serif', [$face]);
    assert_same('Brand "Font"', $fonts->families()[$family['id']]['name']);
    assert_false(str_contains($fonts->css(), 'Brand'));
    assert_throws(InvalidArgumentException::class, static fn() => $installer->upload('Brand', 'serif', [$face], $family['id']));
    $italic = ['bytes' => $bytes, 'style' => 'italic', 'weight' => [100, 900]];
    $installer->upload('Brand', 'serif', [$italic], $family['id']);
    assert_same(2, count($fonts->families()[$family['id']]['faces']));
    assert_throws(InvalidArgumentException::class, static fn() => $installer->upload('Bad', 'serif', [['bytes' => $bytes, 'style' => 'normal', 'weight' => [900, 100]]]));
    $other = new FontRegistry(new WorkspaceContext('other'));
    assert_false(isset($other->families()[$family['id']]));
    assert_null($other->asset(hash('sha256', $bytes . 'different')));
});

test('font deletion reports current usages and site backups restore uploaded font files', function (): void {
    $fonts = FontRegistry::active();
    $family = (new FontInstaller($fonts))->upload('Brand', 'sans-serif', [['bytes' => comet_font_bytes(), 'style' => 'normal', 'weight' => [400, 400]]]);
    $site = new SiteRepository();
    $before = $site->stylesheetVersion();
    $site->saveTheme(['fonts' => ['body' => $family['id']]]);
    assert_true($before !== $site->stylesheetVersion());
    assert_true(in_array('theme/theme.json', $fonts->usages($family['id']), true));
    assert_throws(InvalidArgumentException::class, static fn() => $fonts->delete($family['id']));
    assert_throws(InvalidArgumentException::class, static fn() => $fonts->delete('fontsource-inter'));
    $backup = (new BackupService())->create(['id' => 'admin'], ['site']);
    $path = COMET_STORAGE . '/backups/' . $backup['name'];
    $site->saveTheme([]);
    $fonts->delete($family['id']);
    assert_false(isset($fonts->families()[$family['id']]));
    // Simulate a fresh restore, not merely an existing immutable asset cache.
    comet_test_remove_directory($fonts->directory());
    (new RestoreService())->restore($path, true, ['id' => 'admin'], ['site']);
    assert_true($fonts->families()[$family['id']]['available']);
    assert_same($family['id'], $site->theme()['fonts']['body']);
    assert_true($fonts->asset($family['faces'][0]['hash']) !== null);
});

test('unavailable font assets remain visible in the library and are omitted from generated faces', function (): void {
    $fonts = FontRegistry::active();
    $family = (new FontInstaller($fonts))->upload('Missing', 'serif', [['bytes' => comet_font_bytes(), 'style' => 'normal', 'weight' => [400, 400]]]);
    unlink($fonts->directory() . '/files/' . $family['faces'][0]['hash'] . '.woff2');
    assert_false($fonts->families()[$family['id']]['available']);
    assert_false(str_contains($fonts->css(), '@font-face{font-family:"comet-' . $family['id'] . '"'));
    assert_same($family['id'], Theme::normalize(['fonts' => ['heading' => $family['id']]])['fonts']['heading']);
});

test('font preview fetches one local face only after an explicit request and caches it', function (): void {
    $calls = []; $fail = false;
    $installer = new FontInstaller(FontRegistry::active(), comet_font_provider($calls, $fail));
    assert_same([], $calls);
    $path = $installer->preview('example');
    assert_file_exists_at($path);
    assert_same(1, count(array_filter($calls, static fn(string $url): bool => str_ends_with($url, '.woff2'))));
    $count = count($calls);
    assert_same($path, $installer->preview('example'));
    assert_same($count, count($calls));
    assert_false(isset(FontRegistry::active()->families()['fontsource-example']));
});

test('font preview downloads the static package file directly when the version is known', function (): void {
    $calls = [];
    $provider = new Fontsource(static function (string $url) use (&$calls): string {
        $calls[] = $url;
        if (str_ends_with($url, '/fonts/quick')) {
            return json_encode(['id' => 'quick', 'family' => 'Quick', 'subsets' => ['latin'], 'defSubset' => 'latin', 'weights' => [100, 900, 500], 'styles' => ['normal'], 'npmVersion' => '5.1.0']);
        }
        if ($url === 'https://cdn.jsdelivr.net/npm/@fontsource/quick@5.1.0/files/quick-latin-500-normal.woff2') {
            return comet_font_bytes();
        }
        throw new RuntimeException('Unexpected test URL: ' . $url);
    });
    $path = (new FontInstaller(FontRegistry::active(), $provider))->preview('quick');
    assert_file_exists_at($path);
    assert_same(2, count($calls));
});

test('font management requires website permissions and CSRF before mutations', function (): void {
    $seed = <<<'PHP'
$roles->create(['label' => 'No website', 'permissions' => [['effect' => 'allow', 'actions' => ['content.read'], 'resources' => ['content:*']]]]);
$users->create('font-reader', 'secret-password', 'no-website');
PHP;
    $controller = '(new \\CometCMS\\Controllers\\Admin\\FontsController(new \\CometCMS\\Core\\Http()))';
    assert_same(403, comet_admin_request('font-reader', $seed, 'GET', '/admin/api/site/fonts', $controller . '->index();')[0]);
    [$status, $response] = comet_admin_request('admin', '', 'GET', '/admin/api/site/fonts', $controller . '->index();');
    assert_same(200, $status);
    assert_same([], $response['data']['font_families']);
    assert_same(419, comet_admin_request('admin', '$_SERVER["HTTP_X_CSRF_TOKEN"] = "invalid";', 'POST', '/admin/api/site/fonts/install', $controller . '->install();', ['id' => 'example'])[0]);
});

test('catalogue metadata falls back to the cached list and rejects oversized responses', function (): void {
    $calls = []; $fail = false;
    $provider = comet_font_provider($calls, $fail);
    $catalogue = $provider->catalogue();
    assert_false($catalogue['stale']);
    assert_same('example', $catalogue['families'][0]['id']);
    $count = count($calls);
    $provider->catalogue();
    assert_same($count, count($calls));
    touch(COMET_STORAGE . '/cache/fonts/catalogue.json', time() - 86401);
    clearstatcache(true, COMET_STORAGE . '/cache/fonts/catalogue.json');
    $unavailable = new Fontsource(static function (): never { throw new RuntimeException('Offline'); });
    assert_true($unavailable->catalogue()['stale']);
    $oversized = new Fontsource(static fn(): string => str_repeat('x', 101));
    assert_throws(RuntimeException::class, static fn() => $oversized->get('https://api.fontsource.org/v1/fonts', 100));
});

test('font popularity caches combined monthly downloads and survives provider outages', function (): void {
    $calls = 0;
    $provider = new Fontsource(static function (string $url) use (&$calls): string {
        assert_same('https://api.fontsource.org/v1/stats', $url);
        $calls++;
        return json_encode([
            'example' => ['total' => ['npmDownloadMonthly' => 120], 'static' => ['npmDownloadMonthly' => 100], 'variable' => ['npmDownloadMonthly' => 20]],
            'invalid' => ['total' => ['npmDownloadMonthly' => -1]],
        ]);
    });
    assert_same(['scores' => ['example' => 120], 'stale' => false], $provider->popularity());
    $provider->popularity();
    assert_same(1, $calls);
    $path = COMET_STORAGE . '/cache/fonts/popularity.json';
    touch($path, time() - 86401);
    clearstatcache(true, $path);
    $offline = new Fontsource(static function (): never { throw new RuntimeException('Offline'); });
    assert_same(['scores' => ['example' => 120], 'stale' => true], $offline->popularity());
    unlink($path);
    assert_same(['scores' => [], 'stale' => true], $offline->popularity());
    $invalid = new Fontsource(static fn(): string => '{"error":"unavailable"}');
    assert_same(['scores' => [], 'stale' => true], $invalid->popularity());
});

test('font catalogue defaults to popularity before pagination and supports alphabetical sorting', function (): void {
    $families = [];
    $scores = [];
    for ($i = 0; $i < 55; $i++) {
        $id = sprintf('font-%02d', $i);
        $families[] = ['id' => $id, 'family' => $id, 'category' => $i % 2 ? 'serif' : 'sans-serif'];
        $scores[$id] = $i;
    }
    // An equal score uses family name as a stable tie-breaker.
    $scores['font-53'] = 54;
    $seed = '\\CometCMS\\Site\\FontFiles::json(COMET_STORAGE . \'/cache/fonts/catalogue.json\', ' . var_export($families, true) . ');'
        . '\\CometCMS\\Site\\FontFiles::json(COMET_STORAGE . \'/cache/fonts/popularity.json\', ' . var_export($scores, true) . ');';
    $controller = '(new \\CometCMS\\Controllers\\Admin\\FontsController(new \\CometCMS\\Core\\Http()))->catalogue();';
    [$status, $response] = comet_admin_request('admin', $seed, 'GET', '/admin/api/site/fonts/catalogue', $controller);
    assert_same(200, $status);
    assert_same(55, $response['data']['total']);
    assert_same(50, count($response['data']['families']));
    assert_same(['font-53', 'font-54', 'font-52'], array_column(array_slice($response['data']['families'], 0, 3), 'id'));
    assert_false($response['data']['stale']);
    [, $response] = comet_admin_request('admin', $seed, 'GET', '/admin/api/site/fonts/catalogue?offset=50', $controller);
    assert_same(['font-04', 'font-03', 'font-02', 'font-01', 'font-00'], array_column($response['data']['families'], 'id'));
    [, $response] = comet_admin_request('admin', $seed, 'GET', '/admin/api/site/fonts/catalogue?category=serif&q=font-5', $controller);
    assert_same(['font-53', 'font-51'], array_column($response['data']['families'], 'id'));
    [, $response] = comet_admin_request('admin', $seed, 'GET', '/admin/api/site/fonts/catalogue?sort=alphabetical', $controller);
    assert_same('font-00', $response['data']['families'][0]['id']);
    assert_false($response['data']['stale']);
});

test('abandoned font jobs and previews expire outside the backed-up theme directory', function (): void {
    $calls = []; $fail = false;
    $fonts = FontRegistry::active();
    $installer = new FontInstaller($fonts, comet_font_provider($calls, $fail));
    $job = $installer->prepare('example', []);
    $preview = $installer->preview('example');
    $jobPath = $fonts->workspace->path('cache') . '/fonts/jobs/' . $job['id'];
    touch($jobPath . '/job.json', time() - 86401);
    touch($preview, time() - 86401);
    assert_same([], $installer->jobs());
    assert_false(is_dir($jobPath));
    assert_false(is_file($preview));
    assert_false(is_dir($fonts->directory()));
});

test('registered element fonts render in reusable components and unsaved previews', function (): void {
    $site = comet_site_test_setup();
    comet_install_fixture_fonts(FontRegistry::active());
    $heading = LayoutSchema::node('heading', ['text' => 'ÄÖÜ and Greek ΑΒΓ'], ['font_family' => 'fontsource-lato']);
    $column = LayoutSchema::node('column', [], [], [$heading]);
    $component = $site->createComponent('Font sample', [$column]);
    $nodes = [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('component', ['component' => $component['id'], 'root' => 'column'])])])];
    $site->saveLayout('about', 'detail', $nodes);
    $site->saveTheme(['fonts' => ['body' => 'fontsource-inter', 'heading' => 'fontsource-lora']]);
    $live = comet_site_test_get('/about');
    assert_same(200, $live['status']);
    assert_true(str_contains($live['body'], 'c-ff-fontsource-lato'));
    $context = comet_site_test_context($site);
    $context->preview = true;
    $context->override('theme', Theme::normalize(['fonts' => ['body' => 'fontsource-lato']]));
    $preview = (new \CometCMS\Site\SiteService($context))->respond('/about', []);
    assert_true(str_contains($preview['body'], '--font-body:"comet-fontsource-lato"'));
    assert_true(str_contains($preview['body'], 'c-ff-fontsource-lato'));
    assert_false(str_contains($preview['body'], 'fonts.googleapis.com'));
});

test('font activation recovers if the process stopped after registering the family', function (): void {
    $calls = []; $fail = false;
    $fonts = FontRegistry::active();
    $installer = new FontInstaller($fonts, comet_font_provider($calls, $fail));
    $job = comet_install_job($installer, $installer->prepare('example', []));
    $path = $fonts->workspace->path('cache') . '/fonts/jobs/' . $job['id'] . '/job.json';
    $stored = json_decode((string) file_get_contents($path), true);
    $stored['status'] = 'pending';
    FontFiles::json($path, $stored);
    $before = count($calls);
    assert_same('complete', $installer->step($job['id'], $job['cursor'])['status']);
    assert_same($before, count($calls));
    assert_true($fonts->families()['fontsource-example']['available']);
});
