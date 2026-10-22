<?php

declare(strict_types=1);

use CometCMS\Site\Blocks\BlockPackage;
use CometCMS\Site\Blocks\BlockPackageChanges;
use CometCMS\Site\Blocks\BlockPackageException;
use CometCMS\Site\Blocks\BlockRegistry;
use CometCMS\Site\Blocks\BlockTemplate;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Workspaces\WorkspaceContext;

const COMET_BLOCK_EXAMPLE = COMET_TEST_ROOT . '/examples/blocks/acme-marketing';

/** The example package as path => bytes, optionally changed. */
function comet_block_files(array $changes = []): array
{
    $files = BlockPackage::readDirectory(COMET_BLOCK_EXAMPLE);
    foreach ($changes as $path => $bytes) {
        if ($bytes === null) {
            unset($files[$path]);
        } else {
            $files[$path] = is_array($bytes) ? json_encode($bytes, JSON_UNESCAPED_SLASHES) : $bytes;
        }
    }

    return $files;
}

function comet_block_manifest(string $path, array $changes): array
{
    return array_replace(json_decode(comet_block_files()[$path], true), $changes);
}

/** @param array<string, string> $files entry name => bytes */
function comet_block_zip(array $files, string $prefix = ''): string
{
    $path = COMET_STORAGE . '/package-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    foreach ($files as $name => $bytes) {
        $zip->addFromString($prefix . $name, $bytes);
    }
    $zip->close();

    return $path;
}

/** Upload a ZIP to the admin endpoint through PHP's built-in server, so $_FILES is real. */
function comet_block_upload(string $zipPath, array $fields = [], string $seed = ''): array
{
    $router = COMET_STORAGE . '/block-upload-router-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($router, "<?php\nrequire " . var_export(__DIR__ . '/bootstrap.php', true) . ";\n"
        . "session_start();\n\$users = new \\CometCMS\\Auth\\UserRepository();\n(new \\CometCMS\\Auth\\RoleRepository())->seed();\n"
        . "if (\$users->find('admin') === null) \$users->create('admin', 'secret-password', 'admin');\n" . $seed . "\n"
        . "\$_SESSION['user_id'] = 'admin';\n\$_SESSION['cometcms_csrf'] = 'test-token';\n"
        . "(new \\CometCMS\\Controllers\\Admin\\SiteController(new \\CometCMS\\Core\\Http()))->installBlock();\n");
    $boundary = 'comet' . bin2hex(random_bytes(8));
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
    }
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"package\"; filename=\"package.zip\"\r\nContent-Type: application/zip\r\n\r\n"
        . file_get_contents($zipPath) . "\r\n--$boundary--\r\n";
    $port = 18080 + random_int(0, 4000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $router);
    try {
        usleep(300000);
        $response = (string) file_get_contents('http://127.0.0.1:' . $port . '/admin/api/site/blocks', false, stream_context_create(['http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\nX-CSRF-Token: test-token\r\n",
            'content' => $body,
        ]]));
        $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : (${'http_response_header'} ?? []);
        preg_match('#^HTTP/\S+\s+(\d{3})#', $headers[0] ?? '', $match);

        return [(int) ($match[1] ?? 0), json_decode($response, true)];
    } finally {
        comet_test_stop_process($server);
    }
}

function comet_block_page(SiteRepository $site, array $elements): void
{
    $site->saveLayout('about', 'detail', [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], $elements)])])]);
    $site->saveSettings(array_replace($site->settings(), ['homepage' => ['type' => 'about', 'entry' => 'about']]));
}

function comet_block_errors(callable $callback): array
{
    try {
        $callback();
    } catch (BlockPackageException $e) {
        return array_map([BlockPackageException::class, 'format'], $e->errors());
    }
    throw new RuntimeException('Expected the package to be rejected.');
}

test('block packages register their blocks without changing built-ins or crossing workspaces', function (): void {
    $blocks = BlockRegistry::active();
    $result = $blocks->installFiles(comet_block_files());
    assert_same(['name' => 'acme/marketing', 'title' => 'Acme marketing blocks', 'version' => '1.0.0', 'previous_version' => null,
        'blocks' => ['acme/callout', 'acme/faq', 'acme/feature'], 'changes' => []], $result);
    $spec = LayoutSchema::spec(blocks: $blocks);
    assert_true(in_array('heading', $spec['elements'], true));
    foreach (['block:acme/callout', 'block:acme/faq', 'block:acme/feature'] as $kind) {
        assert_true(in_array($kind, $spec['elements'], true));
        assert_true(in_array($kind, $spec['item_elements'], true));
    }
    $callout = $spec['kinds']['block:acme/callout'];
    assert_same('Callout', $callout['label']);
    assert_same(['type' => 'url', 'label' => 'Link URL', 'max' => 2000, 'default' => '#details', 'localized' => true], $callout['props']['url']);
    assert_true($callout['props']['title']['localized']);
    assert_false(isset($callout['props']['tone']['localized']));
    assert_same([], $callout['children']);
    assert_same('Something worth sharing', LayoutSchema::node('block:acme/callout')['props']['title']);
    $other = new BlockRegistry(new WorkspaceContext('other'));
    assert_same([], $other->all());
    assert_false(in_array('block:acme/callout', LayoutSchema::spec(blocks: $other)['elements'], true));
    $metadata = $blocks->metadata();
    assert_same([], $metadata['errors']);
    assert_same('acme/marketing', $metadata['packages'][0]['name']);
    assert_same(['name' => 'acme/faq', 'title' => 'FAQ item', 'description' => 'A question that expands to its answer. Items with the same group close each other.'], $metadata['packages'][0]['blocks'][1]);
    assert_file_exists_at($blocks->directory() . '/acme/marketing/blocks/callout/template.html');
    assert_file_exists_at($blocks->directory() . '/acme/marketing/assets/check.svg');
    assert_false(BlockRegistry::cssClass('a/b--c') === BlockRegistry::cssClass('a--b/c'));
});

test('block package ZIPs unwrap one top folder, skip OS clutter and reject unsafe entries', function (): void {
    $files = comet_block_files();
    $wrapped = comet_block_zip($files + ['.DS_Store' => 'x', '__MACOSX/._comet-package.json' => 'x'], 'acme-marketing/');
    assert_same(array_keys($files), array_keys(BlockPackage::readZip($wrapped)));
    assert_same('acme/marketing', BlockRegistry::active()->install($wrapped)['name']);

    $before = BlockRegistry::active()->fingerprint();
    foreach ([
        ['../escape.txt' => 'x'],
        ['blocks/callout/view.js' => 'alert(1)'],
        ['blocks/callout/render.php' => '<?php echo 1;'],
        ['notes.txt' => 'x'],
        ['assets/logo.svg' => 'not an svg'],
    ] as $extra) {
        assert_throws(BlockPackageException::class, fn() => BlockRegistry::active()->install(comet_block_zip($files + $extra)));
        assert_same($before, BlockRegistry::active()->fingerprint());
    }
    $notZip = COMET_STORAGE . '/not-a.zip';
    file_put_contents($notZip, 'plain text');
    assert_same(['The upload is not a readable ZIP file.'], comet_block_errors(fn() => BlockPackage::readZip($notZip)));
    assert_false(is_file(BlockRegistry::active()->directory() . '/acme/escape.txt'));
    assert_same(['blocks/callout/view.js: JavaScript is not supported in block packages yet.'], comet_block_errors(fn() => BlockPackage::compile($files + ['blocks/callout/view.js' => ''])));

    $link = comet_block_zip($files);
    $zip = new ZipArchive();
    $zip->open($link);
    $zip->addFromString('assets/link.svg', '/etc/passwd');
    $zip->setExternalAttributesName('assets/link.svg', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();
    assert_same(['assets/link.svg: Symbolic links are not allowed.'], comet_block_errors(fn() => BlockPackage::readZip($link)));
});

test('block package validation reports every problem with its file and line', function (): void {
    $errors = comet_block_errors(fn() => BlockPackage::compile(comet_block_files([
        'comet-package.json' => comet_block_manifest('comet-package.json', ['api_version' => 2, 'scripts' => ['build' => 'x']]),
        'blocks/callout/block.json' => comet_block_manifest('blocks/callout/block.json', ['name' => 'acme/callout']),
        'blocks/faq/template.html' => "<details>\n  <summary style=\"color: red\">{{question}}</summary>\n  {{{answer}}}\n</details>",
        'blocks/feature/style.css' => ".x { background: url(images/bg.png); }",
        'blocks/empty/style.css' => '',
    ])));
    assert_same([
        'comet-package.json: Unknown field "scripts". Allowed: api_version, name, title, description, version, author, license, homepage.',
        'comet-package.json: api_version must be 1.',
        'blocks/callout/block.json: Remove "name": a block is named after its package vendor and folder, for example blocks/callout becomes acme/callout.',
        'blocks/empty/block.json: Every block needs block.json.',
        'blocks/empty/template.html: Every block needs template.html.',
        'blocks/faq/template.html:3: {{{answer}}}: Raw output with {{{name}}} or {{& name}} is not supported; values are always escaped. Use {{name}}.',
        'blocks/feature/style.css:1: The relative URL "images/bg.png" would resolve against each page\'s address. Reference package files as url(assets/…).',
    ], $errors);
    assert_same(['blocks/faq/template.html:2: Attribute "style" is not allowed on <summary>. Use classes and style.css instead of inline styles.'], comet_block_errors(fn() => BlockPackage::compile(comet_block_files([
        'blocks/faq/template.html' => "<details>\n  <summary style=\"color: red\">{{question}}</summary>\n</details>",
    ]))));
    foreach ([
        ['props' => ['tone' => ['type' => 'enum', 'label' => 'Tone', 'options' => ['a'], 'default' => 'b']]],
        ['props' => ['title' => ['type' => 'html', 'label' => 'Title']]],
        ['props' => ['title' => ['type' => 'string', 'label' => 'Title', 'max' => 9000]]],
        ['props' => ['title' => ['type' => 'string']]],
        ['props' => [['type' => 'string', 'label' => 'x']]],
    ] as $change) {
        assert_throws(BlockPackageException::class, fn() => BlockPackage::compile(comet_block_files(['blocks/feature/block.json' => comet_block_manifest('blocks/feature/block.json', $change)])));
    }
    foreach (['name' => 'comet/blocks', 'version' => '1.0', 'title' => ''] as $field => $value) {
        assert_throws(BlockPackageException::class, fn() => BlockPackage::compile(comet_block_files(['comet-package.json' => comet_block_manifest('comet-package.json', [$field => $value])])));
    }
});

test('block templates compile allow-listed HTML and render only escaped values', function (): void {
    $props = [
        'title' => ['type' => 'string', 'max' => 200, 'default' => ''],
        'link' => ['type' => 'url', 'max' => 200, 'default' => '#'],
        'open' => ['type' => 'bool', 'default' => false],
        'tone' => ['type' => 'enum', 'options' => ['info', 'success'], 'default' => 'info'],
    ];
    $compile = static function (string $source) use ($props): array {
        $errors = [];
        $tree = BlockTemplate::compile($source, $props, ['assets/icon.svg' => 'abc.svg'], 'template.html', $errors);
        if ($errors !== []) throw new BlockPackageException($errors);
        return $tree;
    };
    $url = static fn(string $file): string => '/a/' . $file;
    $tree = $compile('{{! note }}<details {{#open}}open{{/open}} class="t-{{tone}}"><summary>{{title}}</summary>{{#tone=success}}<img src="assets/icon.svg" alt="">{{/tone=success}}<a href="{{link}}" target="_blank">Go</a></details>');
    assert_same('<details open class="t-success"><summary>&lt;b&gt;{{tone}}&lt;/b&gt;</summary><img src="/a/abc.svg" alt><a href="https://x.test/?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">Go</a></details>',
        BlockTemplate::render($tree, ['open' => true, 'title' => '<b>{{tone}}</b>', 'tone' => 'success', 'link' => 'https://x.test/?a=1&b=2'], $url));
    assert_same('<details class="t-info"><summary></summary><a href="#" target="_blank" rel="noopener noreferrer">Go</a></details>',
        BlockTemplate::render($tree, ['open' => false, 'title' => '', 'tone' => 'info', 'link' => '#'], $url));
    // Parsing normalizes markup, so stored templates are always well-formed.
    assert_same('<ul><li>a</li><li>b &amp; c</li></ul><svg viewbox="0 0 24 24"><path d="M1 1"></path><circle r="2"></circle></svg>',
        BlockTemplate::render($compile('<ul><li>a<li>b & c</ul><svg viewBox="0 0 24 24"><path d="M1 1"/><circle r="2"/></svg>'), [], $url));
    assert_same('<picture><source srcset="/a/abc.svg 1x"><img src="/a/abc.svg" alt=""></picture><p>after</p>',
        BlockTemplate::render($compile('<picture><source srcset="assets/icon.svg 1x"><img src="assets/icon.svg" alt="{{title}}"></picture><p>after</p>'), ['title' => ''], $url));
    foreach ([
        '{{{title}}}', '{{& title}}', '{{> partial}}', '{{title.x}}', '{{unknown}}', '{{#open}}x{{/tone}}', '{{/open}}', '{{#open}}', '{{title',
        '{{#tone=warning}}x{{/tone}}', '{{#open=yes}}x{{/open}}', '<div {{title}}>x</div>', '<div {{#open}}class{{/open}}>x</div>',
        '<script>alert(1)</script>', '<div onclick="x()">x</div>', '<div style="color: red">x</div>', '<iframe src="https://x.test"></iframe>',
        '<a href="{{title}}">x</a>', '<a href="javascript:alert(1)">x</a>', '<a href="{{link}}#top">x</a>', '<img src="logo.png" alt="">',
        '<img src="assets/missing.svg" alt="">', '<div>{{#open}}<p>a</div>{{/open}}', '<p><section>x</section></p>', '<!-- {{title}} -->',
        '<p>a</p></div><p>b</p>', '<button type="submit">x</button>', '<path d="M1"/>', '<svg><div>x</div></svg>', "{{#open\n}}x{{/open}}",
    ] as $invalid) {
        assert_throws(BlockPackageException::class, fn() => $compile($invalid), 'Expected rejection: ' . $invalid);
    }
    assert_throws(BlockPackageException::class, fn() => $compile(str_repeat('{{#open}}', 17) . str_repeat('{{/open}}', 17)));
});

test('custom blocks normalize props, keep translations and work in columns tabs repeaters and component roots', function (): void {
    $blocks = BlockRegistry::active();
    $blocks->installFiles(comet_block_files());
    $block = LayoutSchema::node('block:acme/callout', ['title' => "  Hello\nworld  ", 'show_link' => 'yes', 'tone' => 'invalid', 'url' => 'javascript:alert(1)', 'unknown' => 'drop'], ['padding_y' => 'lg']);
    $column = LayoutSchema::node('column', [], [], [
        $block,
        LayoutSchema::node('tabs', [], [], [LayoutSchema::node('tab', [], [], [$block])]),
        LayoutSchema::node('field', [], [], [$block]),
        LayoutSchema::node('collection', [], [], [LayoutSchema::node('column', [], [], [$block])]),
    ]);
    $nodes = LayoutSchema::normalizeComponent([$column], 'column', $blocks);
    $saved = $nodes[0]['children'][0];
    assert_same('Hello world', $saved['props']['title']);
    assert_same(true, $saved['props']['show_link']);
    assert_same('info', $saved['props']['tone']);
    assert_same('#details', $saved['props']['url']);
    assert_false(isset($saved['props']['unknown']));
    foreach ([$nodes[0]['children'][1]['children'][0]['children'][0], $nodes[0]['children'][2]['children'][0], $nodes[0]['children'][3]['children'][0]['children'][0]] as $nested) {
        assert_same('block:acme/callout', $nested['kind']);
    }
    assert_same($nodes, LayoutSchema::normalizeComponent($nodes, 'column', $blocks));
    assert_same([], LayoutSchema::normalize([$block], blocks: $blocks));

    $translated = LayoutSchema::normalizeProps('block:acme/callout', ['title' => ['type' => 'localized', 'values' => ['en' => "Hi\nthere", 'de' => 'Hallo']]], $blocks);
    assert_same(['en' => 'Hi there', 'de' => 'Hallo'], $translated['title']['values']);
    $blocks->delete('acme/marketing');
    $kept = LayoutSchema::normalizeProps('block:acme/callout', ['title' => ['type' => 'localized', 'values' => ['en' => 'Hi', 'de' => ['nested']]], 'tone' => 'success'], new BlockRegistry($blocks->workspace));
    assert_same(['title' => ['type' => 'localized', 'values' => ['en' => 'Hi']], 'tone' => 'success'], $kept);
});

test('block pages render package CSS once, asset URLs and identical output in previews', function (): void {
    $site = comet_site_test_setup();
    $site->blocks()->installFiles(comet_block_files());
    $callout = LayoutSchema::node('block:acme/callout', ['title' => 'Hello <world>', 'message' => 'Text & more', 'tone' => 'success', 'url' => 'https://example.test/info']);
    comet_block_page($site, [$callout, $callout, LayoutSchema::node('block:acme/faq', ['open' => true])]);
    $site = new SiteRepository();
    $entry = \CometCMS\Content\ContentRepository::make()->findByIdentifier('about', 'about');
    $ctx = comet_site_test_context($site);
    $html = (new PageRenderer($ctx))->entryPage($site->types()['about'], $entry);
    $check = '/_site/default/blocks/' . hash_file('sha256', COMET_BLOCK_EXAMPLE . '/assets/check.svg') . '.svg';
    $chevron = '/_site/default/blocks/' . hash_file('sha256', COMET_BLOCK_EXAMPLE . '/assets/chevron.svg') . '.svg';
    assert_same(2, substr_count($html, '<h3 class="acme-callout__title">Hello &lt;world&gt;</h3>'));
    assert_same(2, substr_count($html, '<img class="acme-callout__icon" src="' . $check . '"'));
    assert_true(str_contains($html, '<details class="acme-faq" name="faq" open>'));
    assert_same(1, substr_count($html, '<style data-comet-blocks>'));
    assert_same(1, substr_count($html, '--acme-accent: var(--c-primary'));
    assert_same(1, substr_count($html, '.c-block-acme__callout .acme-callout {'));
    assert_true(str_contains($html, 'url("' . $chevron . '")'));
    assert_false(str_contains($html, 'acme-feature'));
    assert_true(str_contains($html, 'href="https://example.test/info"'));
    assert_true(str_contains($html, 'class="c-el c-el-block-acme-callout c-block-acme__callout'));
    $ctx->preview = true;
    $ctx->annotate = true;
    $preview = (new PageRenderer($ctx))->entryPage($site->types()['about'], $entry);
    assert_same(2, substr_count($preview, '<h3 class="acme-callout__title">Hello &lt;world&gt;</h3>'));
    assert_true(str_contains($preview, 'data-kind="block:acme/callout"'));
    assert_true(str_contains($preview, "script-src 'none'"));

    $asset = $site->blocks()->asset(basename($check));
    assert_same(['path' => $site->blocks()->directory() . '/acme/marketing/assets/check.svg', 'type' => 'image/svg+xml'], $asset);
    assert_null($site->blocks()->asset(str_repeat('0', 64) . '.svg'));
});

test('block package updates that break placed blocks need confirmation', function (): void {
    $blocks = BlockRegistry::active();
    $blocks->installFiles(comet_block_files());
    $manifest = comet_block_manifest('blocks/callout/block.json', []);
    unset($manifest['props']['link_label']);
    $manifest['props']['tone']['options'] = ['info'];
    $update = comet_block_files([
        'comet-package.json' => comet_block_manifest('comet-package.json', ['version' => '2.0.0']),
        'blocks/callout/block.json' => $manifest,
        'blocks/callout/template.html' => '<p class="acme-callout acme-callout--{{tone}}">{{title}}</p>',
        'blocks/feature/block.json' => null, 'blocks/feature/template.html' => null, 'blocks/feature/style.css' => null,
    ]);
    try {
        $blocks->installFiles($update);
        throw new RuntimeException('Expected confirmation to be required.');
    } catch (BlockPackageChanges $e) {
        assert_same(['acme/callout: the prop "tone" no longer offers success. Instances using them fall back to the default.',
            'acme/callout: the prop "link_label" is removed. Its saved values are dropped the next time a layout is saved.',
            'acme/feature is removed. Placed instances will show as missing blocks.'], $e->changes);
        assert_same('1.0.0', $e->installedVersion);
    }
    assert_same('1.0.0', BlockRegistry::active()->package('acme/marketing')['version']);
    $result = BlockRegistry::active()->installFiles($update, true);
    assert_same('1.0.0', $result['previous_version']);
    assert_same(3, count($result['changes']));
    assert_null(BlockRegistry::active()->get('block:acme/feature'));
    // Additions alone are not breaking.
    $manifest['props']['subtitle'] = ['type' => 'string', 'label' => 'Subtitle'];
    assert_same([], BlockRegistry::active()->installFiles(array_replace($update, ['blocks/callout/block.json' => json_encode($manifest)]))['changes']);
});

test('block names belong to one package and package folders must match their names', function (): void {
    $blocks = BlockRegistry::active();
    $blocks->installFiles(comet_block_files());
    $other = comet_block_files(['comet-package.json' => comet_block_manifest('comet-package.json', ['name' => 'acme/other'])]);
    assert_same(['blocks/callout/: The block acme/callout is already provided by the package acme/marketing.'], comet_block_errors(fn() => $blocks->installFiles($other)));
    $moved = $blocks->directory() . '/acme/renamed';
    mkdir($moved);
    foreach (comet_block_files() as $path => $bytes) {
        if (!is_dir(dirname($moved . '/' . $path))) mkdir(dirname($moved . '/' . $path), 0775, true);
        file_put_contents($moved . '/' . $path, $bytes);
    }
    $metadata = BlockRegistry::active()->metadata();
    assert_same(1, count($metadata['packages']));
    assert_same('acme/renamed', $metadata['errors'][0]['package']);
    assert_matches('/installed in site\/blocks\/acme\/renamed/', $metadata['errors'][0]['message']);
});

test('the compiled block index is cached until a package file changes', function (): void {
    $blocks = BlockRegistry::active();
    $blocks->installFiles(comet_block_files());
    $cache = $blocks->workspace->path('cache') . '/blocks.json';
    $first = BlockRegistry::active()->all();
    assert_file_exists_at($cache);
    $stored = json_decode((string) file_get_contents($cache), true);
    $stored['blocks']['block:acme/callout']['title'] = 'Served from cache';
    file_put_contents($cache, json_encode($stored));
    assert_same('Served from cache', BlockRegistry::active()->get('block:acme/callout')['title']);
    file_put_contents($blocks->directory() . '/acme/marketing/blocks/callout/block.json', json_encode(comet_block_manifest('blocks/callout/block.json', ['title' => 'Edited directly'])));
    assert_same('Edited directly', BlockRegistry::active()->get('block:acme/callout')['title']);
    assert_same(array_keys($first), array_keys(BlockRegistry::active()->all()));
});

test('missing or malformed packages preserve placed block values and do not disable valid packages', function (): void {
    $blocks = BlockRegistry::active();
    $blocks->installFiles(comet_block_files());
    $column = LayoutSchema::node('column', [], [], [LayoutSchema::node('block:acme/callout', ['title' => 'Keep me'], ['padding_y' => 'lg'])]);
    $saved = LayoutSchema::normalizeComponent([$column], 'column', $blocks);
    file_put_contents($blocks->directory() . '/acme/marketing/blocks/callout/block.json', '{bad');
    $missing = BlockRegistry::active();
    assert_same([], $missing->all());
    assert_same('acme/marketing', $missing->metadata()['errors'][0]['package']);
    assert_same('blocks/callout/block.json', $missing->metadata()['errors'][0]['file']);
    assert_same($saved, LayoutSchema::normalizeComponent($saved, 'column', $missing));
    $ctx = comet_site_test_context();
    assert_same('', (new NodeRenderer($ctx))->render($saved[0]['children']));
    $ctx->annotate = true;
    assert_true(str_contains((new NodeRenderer($ctx))->render($saved[0]['children']), 'Missing block: acme/callout'));
    $missing->installFiles(comet_block_files());
    assert_true(str_contains((new NodeRenderer(comet_site_test_context()))->render($saved[0]['children']), '>Keep me</h3>'));

    $broken = $blocks->directory() . '/other/broken';
    mkdir($broken, 0775, true);
    file_put_contents($broken . '/comet-package.json', '{broken');
    $registry = BlockRegistry::active();
    assert_same(3, count($registry->all()));
    assert_same('other/broken', $registry->metadata()['errors'][0]['package']);
    $template = $blocks->directory() . '/acme/marketing/blocks/callout/template.html';
    unlink($template);
    symlink(COMET_BLOCK_EXAMPLE . '/blocks/callout/template.html', $template);
    assert_same([], BlockRegistry::active()->all());
    // Reinstalling repairs the linked file without following it when the old package is removed.
    $blocks->installFiles(comet_block_files());
    assert_same(3, count(BlockRegistry::active()->all()));
    assert_true(is_file(COMET_BLOCK_EXAMPLE . '/blocks/callout/template.html'));
});

test('direct block edits and uploaded updates invalidate cached public output', function (): void {
    $site = comet_site_test_setup();
    $site->blocks()->installFiles(comet_block_files());
    comet_block_page($site, [LayoutSchema::node('block:acme/callout')]);
    $respond = static fn(): array => (new SiteService(comet_site_test_context(new SiteRepository())))->respondCached('/', []);
    assert_true(str_contains($respond()['body'], 'Something worth sharing'));
    assert_same('HIT', $respond()['cache_status']);
    file_put_contents($site->blocks()->directory() . '/acme/marketing/blocks/callout/template.html', '<p>Updated directly: {{title}}</p>');
    $updated = $respond();
    assert_same('MISS', $updated['cache_status']);
    assert_true(str_contains($updated['body'], 'Updated directly:'));
    $site->blocks()->installFiles(comet_block_files());
    assert_false(str_contains($respond()['body'], 'Updated directly:'));
});

test('deleting a block package preserves layout instances, invalidates public output and stays within its workspace', function (): void {
    $site = comet_site_test_setup();
    $blocks = $site->blocks();
    $blocks->installFiles(comet_block_files());
    (new BlockRegistry(new WorkspaceContext('other')))->installFiles(comet_block_files());
    comet_block_page($site, [LayoutSchema::node('block:acme/callout', ['title' => 'Restore this'])]);
    $saved = $site->layout('about', 'detail');
    $respond = static fn(): array => (new SiteService(comet_site_test_context(new SiteRepository())))->respondCached('/', []);
    assert_true(str_contains($respond()['body'], '>Restore this</h3>'));
    assert_true($blocks->delete('acme/marketing'));
    assert_false(is_dir($blocks->directory() . '/acme'));
    assert_same([], $blocks->all());
    assert_same($saved, (new SiteRepository())->layout('about', 'detail'));
    assert_same('Callout', (new BlockRegistry(new WorkspaceContext('other')))->get('block:acme/callout')['title']);
    assert_false($blocks->delete('acme/marketing'));
    assert_throws(InvalidArgumentException::class, fn() => $blocks->delete('../other'));
    $deleted = $respond();
    assert_same('MISS', $deleted['cache_status']);
    assert_false(str_contains($deleted['body'], 'Restore this'));
    $blocks->installFiles(comet_block_files());
    assert_true(str_contains($respond()['body'], '>Restore this</h3>'));
});

test('block packages survive a site backup and restore', function (): void {
    $blocks = BlockRegistry::active();
    $blocks->installFiles(comet_block_files());
    $backup = (new \CometCMS\Backups\BackupService())->create(['id' => 'admin'], ['site']);
    comet_test_remove_directory($blocks->directory());
    assert_same([], BlockRegistry::active()->all());
    (new \CometCMS\Backups\RestoreService())->restore(COMET_STORAGE . '/backups/' . $backup['name'], true, ['id' => 'admin'], ['site']);
    assert_same('Callout', BlockRegistry::active()->get('block:acme/callout')['title']);
    assert_same(comet_block_files()['assets/check.svg'], file_get_contents($blocks->directory() . '/acme/marketing/assets/check.svg'));
});

test('block package uploads report every problem and ask before breaking placed blocks', function (): void {
    [$status, $body] = comet_block_upload(comet_block_zip(comet_block_files()));
    assert_same(201, $status);
    assert_same('acme/marketing', $body['data']['name']);
    [$status, $body] = comet_block_upload(comet_block_zip(comet_block_files(['blocks/faq/template.html' => '<p>{{missing}}</p>', 'notes.txt' => 'x'])));
    assert_same(422, $status);
    assert_same('invalid_block_package', $body['error']['code']);
    assert_same(['notes.txt', 'blocks/faq/template.html'], array_column($body['error']['details'], 'file'));
    assert_same(1, $body['error']['details'][1]['line']);
    $update = comet_block_zip(comet_block_files(['blocks/feature/block.json' => null, 'blocks/feature/template.html' => null, 'blocks/feature/style.css' => null]));
    $seed = '\\CometCMS\\Site\\Blocks\\BlockRegistry::active()->installFiles(\\CometCMS\\Site\\Blocks\\BlockPackage::readDirectory(' . var_export(COMET_BLOCK_EXAMPLE, true) . '));';
    [$status, $body] = comet_block_upload($update, [], $seed);
    assert_same(409, $status);
    assert_same('block_package_changes', $body['error']['code']);
    assert_same(['acme/feature is removed. Placed instances will show as missing blocks.'], $body['error']['details']['changes']);
    [$status, $body] = comet_block_upload($update, ['accept_changes' => '1'], $seed);
    assert_same(201, $status);
    assert_same(['acme/callout', 'acme/faq'], $body['data']['blocks']);
});

test('block inventory requires site permission and installation and deletion require an administrator and CSRF', function (): void {
    $action = '(new \\CometCMS\\Controllers\\Admin\\SiteController(new \\CometCMS\\Core\\Http()))->';
    $seed = '$users->create("ed", "secret-password", "editor");';
    [$status] = comet_admin_request('ed', $seed, 'POST', '/admin/api/site/blocks', $action . 'installBlock();');
    assert_same(403, $status);
    [$status, $body] = comet_admin_request('admin', '', 'GET', '/admin/api/site/blocks', $action . 'blocks();');
    assert_same(200, $status);
    assert_same(['packages' => [], 'errors' => [], 'can_install' => true], $body['data']);
    [$status] = comet_admin_request('admin', '', 'POST', '/admin/api/site/blocks', 'unset($_SERVER["HTTP_X_CSRF_TOKEN"]);' . $action . 'installBlock();');
    assert_same(419, $status);
    [$status, $body] = comet_admin_request('admin', '', 'POST', '/admin/api/site/blocks', $action . 'installBlock();');
    assert_same(422, $status);
    assert_same('Upload one block package as a .zip file.', $body['error']['message']);

    $delete = $action . 'deleteBlock("acme", "marketing");';
    $uri = '/admin/api/site/blocks/acme/marketing';
    [$status] = comet_admin_request('ed', $seed, 'DELETE', $uri, $delete);
    assert_same(403, $status);
    [$status] = comet_admin_request('admin', '', 'DELETE', $uri, 'unset($_SERVER["HTTP_X_CSRF_TOKEN"]);' . $delete);
    assert_same(419, $status);
    [$status] = comet_admin_request('admin', '', 'DELETE', $uri, $delete);
    assert_same(404, $status);
    $install = '\\CometCMS\\Site\\Blocks\\BlockRegistry::active()->installFiles(\\CometCMS\\Site\\Blocks\\BlockPackage::readDirectory(' . var_export(COMET_BLOCK_EXAMPLE, true) . '));';
    [$status, $body] = comet_admin_request('admin', $install, 'DELETE', $uri, $delete);
    assert_same(200, $status);
    assert_same('acme/marketing', $body['data']['name']);
});
