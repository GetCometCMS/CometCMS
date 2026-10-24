<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\RateLimiter;
use CometCMS\Core\ValidationException;
use CometCMS\Site\FormRepository;
use CometCMS\Site\FormSubmission;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\SiteRepository;

/** The site test setup plus a private "inquiries" type that receives contact forms. */
function comet_form_test_setup(): array
{
    $site = comet_site_test_setup();
    (new ContentTypeRepository())->save([
        'name' => 'inquiries', 'label' => 'Contact',
        'submission_settings' => ['success_message' => 'Thanks, we will be in touch.'],
        'visibility' => 'private', 'allow_submissions' => true,
        'fields' => [
            'name' => ['type' => 'text', 'label' => 'Your name', 'required' => true, 'layout' => ['width' => '1/2']],
            'email' => ['type' => 'text', 'label' => 'Email', 'required' => true, 'layout' => ['width' => '1/2']],
            'message' => ['type' => 'textarea', 'label' => 'Message', 'description' => 'How can we help?'],
            'topic' => ['type' => 'select', 'options' => ['sales' => 'Sales', 'support' => 'Support']],
            'consent' => ['type' => 'boolean', 'label' => 'I agree', 'required' => true],
            'attachment' => ['type' => 'media'],
        ],
    ]);
    $site = new SiteRepository();
    $forms = new FormRepository($site);
    $form = $forms->all()[0];

    return [$site, $forms, $form];
}

function comet_form_test_submit(SiteRepository $site, FormRepository $forms, array $form, array $input, ?RateLimiter $limiter = null): array
{
    return (new FormSubmission($site, $forms, ContentRepository::make($site->workspace()), $limiter))->submit($form, $input, '203.0.113.5');
}

test('forms show every field of their type that visitors can fill in', function (): void {
    [, $forms, $form] = comet_form_test_setup();

    assert_same(['name', 'email', 'message', 'topic', 'consent', 'attachment'], array_column($forms->inputs($form), 'key'), 'Title and slug are not asked for.');
    assert_same('Your name', $forms->inputs($form)[0]['label']);
    assert_same('draft', $form['status']);
    assert_same('none', $form['captcha']);

    (new ContentTypeRepository())->save(['name' => 'applications', 'allow_submissions' => true, 'fields' => ['cv' => ['type' => 'media', 'required' => true]]]);
    $forms = new FormRepository(new SiteRepository());
    assert_same(['applications', 'inquiries'], array_column($forms->all(), 'type'));
    $types = new ContentTypeRepository();
    $types->save([...$types->find('inquiries'), 'submission_settings' => ['status' => 'published', 'captcha' => 'turnstile']]);
    $updated = (new FormRepository(new SiteRepository()))->find($form['id']);
    assert_same('published', $updated['status']);
    assert_same('none', $updated['captcha']);

});

test('a submission becomes a draft entry with only the form\'s fields', function (): void {
    [$site, $forms, $form] = comet_form_test_setup();

    $result = comet_form_test_submit($site, $forms, $form, ['fields' => [
        'name' => '  Ada  ',
        'email' => 'ada@example.com',
        'message' => "Hello\r\nthere",
        'topic' => 'support',
        'consent' => '1',
        'attachment' => 'secret.pdf',
        'status' => 'published',
    ], 'status' => 'published', 'author_id' => 'admin']);

    assert_same(FormSubmission::SENT, $result['status']);
    $entry = ContentRepository::make()->find('inquiries', (string) $result['entry']['id']);
    assert_same('draft', $entry['status']);
    assert_same('Ada', $entry['name']);
    assert_same("Hello\nthere", $entry['message']);
    assert_true($entry['consent']);
    assert_same([], $entry['attachment'], 'Posted filename references cannot attach existing media.');
    assert_null($entry['author_id']);
    assert_true(str_starts_with($entry['title'], 'Contact – '), 'Forms without a title field get one.');
});

test('submissions are checked against the content type and the honeypot', function (): void {
    [$site, $forms, $form] = comet_form_test_setup();
    $valid = ['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => '1'];

    $missing = comet_form_test_submit($site, $forms, $form, ['fields' => ['name' => 'Ada', 'consent' => '1']]);
    assert_same(FormSubmission::ERROR, $missing['status']);
    assert_true(isset($missing['errors']['email']));

    $unchecked = comet_form_test_submit($site, $forms, $form, ['fields' => ['name' => 'Ada', 'email' => 'ada@example.com']]);
    assert_true(isset($unchecked['errors']['consent']), 'A required checkbox has to be ticked.');

    $badOption = comet_form_test_submit($site, $forms, $form, ['fields' => $valid + ['topic' => 'nope']]);
    assert_true(isset($badOption['errors']['topic']));

    $wrongShape = comet_form_test_submit($site, $forms, $form, ['fields' => $valid + ['message' => ['nested']]]);
    assert_true(isset($wrongShape['errors']['message']));

    $bot = comet_form_test_submit($site, $forms, $form, ['fields' => $valid, FormSubmission::HONEYPOT => 'https://spam.example']);
    assert_same(FormSubmission::SENT, $bot['status'], 'Bots are not told they were caught.');
    assert_false(isset($bot['entry']));
    assert_same([], ContentRepository::make()->all('inquiries'));
});

test('submissions are rate limited per visitor', function (): void {
    [$site, $forms, $form] = comet_form_test_setup();
    $limiter = new RateLimiter($site->workspace()->path('cache') . '/form-limits');
    $input = ['fields' => ['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => '1']];

    for ($i = 0; $i < 5; $i++) {
        assert_same(FormSubmission::SENT, comet_form_test_submit($site, $forms, $form, $input, $limiter)['status']);
    }
    assert_same(FormSubmission::ERROR, comet_form_test_submit($site, $forms, $form, $input, $limiter)['status']);
    assert_same(5, count(ContentRepository::make()->all('inquiries')));
});

test('a placed form renders labelled inputs from the content type and is found where it is used', function (): void {
    [$site, $forms, $form] = comet_form_test_setup();
    $site->saveLayout('blog', 'detail', [comet_component_test_section([
        LayoutSchema::node('form', ['form' => $form['id']]),
        LayoutSchema::node('form', ['form' => 'fmissing']),
    ])]);

    $html = comet_site_test_get('/blog/hello')['body'];
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    $forms_ = $xpath->query('//form[contains(@class, "c-form")]');
    assert_same(1, $forms_->length, 'A missing form renders nothing on the live site.');
    $element = $forms_->item(0);
    assert_same('post', $element->getAttribute('method'));
    assert_same('/_site/forms/default/' . $form['id'], $element->getAttribute('action'));
    assert_same('/blog/hello', $xpath->query('.//input[@name="_return"]', $element)->item(0)->getAttribute('value'));

    $email = $xpath->query('.//input[@name="fields[email]"]', $element)->item(0);
    assert_same('email', $email->getAttribute('type'));
    assert_true($email->hasAttribute('required'));
    assert_true(str_contains($email->parentNode->getAttribute('class'), 'c-form-w-1-2'), 'Widths come from the field builder.');
    assert_same('Your name', trim($xpath->query('.//label[@for="' . $xpath->query('.//input[@name="fields[name]"]', $element)->item(0)->getAttribute('id') . '"]', $element)->item(0)->firstChild->textContent));
    assert_same(1, $xpath->query('.//textarea[@name="fields[message]"]', $element)->length);
    assert_same(3, $xpath->query('.//select[@name="fields[topic]"]/option', $element)->length);
    assert_same(1, $xpath->query('.//input[@type="checkbox"][@name="fields[consent]"]', $element)->length);
    assert_same(1, $xpath->query('.//input[@name="' . FormSubmission::HONEYPOT . '"]', $element)->length);
    assert_same('multipart/form-data', $element->getAttribute('enctype'));
    assert_same(1, $xpath->query('.//input[@type="file"][@name="fields[attachment]"]', $element)->length);
    assert_same('10485760', $xpath->query('.//input[@name="MAX_FILE_SIZE"]', $element)->item(0)->getAttribute('value'));
    assert_true(str_contains($element->textContent, '10 MiB'));

    $anchor = $xpath->query('.//input[@name="_anchor"]', $element)->item(0)->getAttribute('value');
    $sent = $document->getElementById($anchor . '-sent');
    assert_true($sent !== null && str_contains($sent->textContent, 'Thanks, we will be in touch.'));
    assert_true($document->getElementById($anchor . '-error') !== null);

    assert_same([['target' => 'layout', 'type' => 'blog', 'variant' => 'detail', 'area' => 'default']], $site->formUsages($form['id']));
    $types = new ContentTypeRepository();
    $types->save([...$types->find('inquiries'), 'allow_submissions' => false]);
    assert_same(0, substr_count(comet_site_test_get('/blog/hello')['body'], 'c-form-grid'));
});

test('posting a form stores the entry and sends the visitor back to the page', function (): void {
    $root = comet_site_preview_test_install();
    $formId = trim(comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . ';' . <<<'PHP'
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'inquiries', 'visibility' => 'private', 'allow_submissions' => true, 'fields' => [
    'email' => ['type' => 'text', 'required' => true],
    'message' => ['type' => 'textarea'],
]]);
echo (new \CometCMS\Site\FormRepository(new \CometCMS\Site\SiteRepository()))->all()[0]['id'];
PHP]));
    $port = 22080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $root . '/router.php');
    $post = static function (string $path, array $body, string $accept = 'text/html') use ($port): array {
        $response = file_get_contents('http://127.0.0.1:' . $port . $path, false, stream_context_create(['http' => [
            'ignore_errors' => true,
            'follow_location' => 0,
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: " . $accept . "\r\n",
            'content' => http_build_query($body),
        ]]));
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : (${'http_response_header'} ?? []);
        preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0] ?? '', $match);
        $location = '';
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }

        return [(int) ($match[1] ?? 0), $location, (string) $response];
    };

    try {
        usleep(300000);
        $action = '/cms/_site/forms/default/' . $formId;
        [$status, $location] = $post($action, ['_return' => '/cms/contact', '_anchor' => 'form-n1', 'fields' => ['email' => 'ada@example.com', 'message' => 'Hi']]);
        assert_same(303, $status);
        assert_same('/cms/contact#form-n1-sent', $location);

        [, $location] = $post($action, ['_return' => '//evil.example/x', '_anchor' => 'form-n1', 'fields' => ['message' => 'No email']]);
        assert_same('/cms/#form-n1-error', $location, 'Only paths on this site are followed.');

        [$status, , $json] = $post($action, ['fields' => ['email' => 'grace@example.com']], 'application/json');
        assert_same(200, $status);
        assert_same('sent', json_decode($json, true)['data']['status']);

        assert_same(404, $post('/cms/_site/forms/default/fmissing', [])[0]);

        $stored = comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . '; echo json_encode(array_column(\CometCMS\Content\ContentRepository::make()->all("inquiries"), "email"));']);
        $emails = json_decode($stored, true);
        sort($emails);
        assert_same(['ada@example.com', 'grace@example.com'], $emails);
    } finally {
        comet_test_stop_process($server);
    }
});

test('content type submission settings create a usable form without a separate form record', function (): void {
    $types = new ContentTypeRepository();
    $types->save([
        'name' => 'signups', 'label' => 'Sign ups', 'allow_submissions' => true,
        'fields' => ['email' => ['type' => 'text', 'required' => true]],
        'submission_settings' => ['status' => 'published', 'submit_label' => 'Join', 'success_message' => 'Welcome!', 'error_message' => 'Try again.', 'captcha' => 'turnstile'],
    ]);
    $site = new SiteRepository();
    $forms = new FormRepository($site);
    assert_same(['signups'], array_keys($forms->types()));
    assert_same(1, count($forms->all()));
    $form = $forms->all()[0];
    assert_same($form, $forms->find($form['id']));
    assert_same('Sign ups', $form['name']);
    assert_same('Join', $form['submit_label']);
    assert_same('Welcome!', $form['success_message']);
    assert_same('none', $form['captcha']);
    $result = comet_form_test_submit($site, $forms, $form, ['fields' => ['email' => 'ada@example.com']]);
    assert_same(FormSubmission::SENT, $result['status']);
    assert_same('published', $result['entry']['status']);
    assert_same([], (new \CometCMS\Storage\JsonStore(comet_test_workspace_path() . '/site/forms'))->all());

    $types->save([...$types->find('signups'), 'allow_submissions' => false]);
    $site = new SiteRepository();
    $forms = new FormRepository($site);
    assert_same([], $forms->all());
    assert_null($forms->find($form['id']));
    assert_same(FormSubmission::ERROR, comet_form_test_submit($site, $forms, $form, ['fields' => ['email' => 'another@example.com']])['status']);
    assert_same(1, count(ContentRepository::make()->all('signups')), 'Disabling submissions retains existing entries.');

    $types->save([...$types->find('signups'), 'allow_submissions' => true]);
    $reenabled = (new FormRepository(new SiteRepository()))->all()[0];
    assert_same($form['id'], $reenabled['id'], 'Existing placements work again when submissions are enabled.');
    assert_same('Join', $reenabled['submit_label']);
});

test('existing forms retain their placements and adopt settings saved with the type', function (): void {
    [$site, $forms, $form] = comet_form_test_setup();
    $types = new ContentTypeRepository();
    $types->save([...$types->find('inquiries'), 'submission_settings' => ['submit_label' => 'Send request', 'success_message' => 'Received', 'status' => 'published']]);
    $forms = new FormRepository(new SiteRepository());
    assert_same(1, count($forms->all()));
    assert_same($form['id'], $forms->all()[0]['id']);
    assert_same('Send request', $forms->find($form['id'])['submit_label']);
    assert_same('published', $forms->find($form['id'])['status']);

    $types->save([...$types->find('inquiries'), 'allow_submissions' => false]);
    $forms = new FormRepository(new SiteRepository());
    assert_same([], $forms->all());
    assert_null($forms->find($form['id']));

    $types->save(['name' => 'inquiries', 'fields' => $types->find('inquiries')['fields']]);
    assert_false($types->find('inquiries')['allow_submissions'], 'An omitted toggle must not enable legacy forms again.');
    assert_same('Send request', $types->find('inquiries')['submission_settings']['submit_label']);
});

test('retired standalone forms neither enable submissions nor appear in the builder', function (): void {
    (new \CometCMS\Storage\JsonStore(comet_test_workspace_path() . '/content-types'))->write([
        'name' => 'legacy', 'fields' => ['email' => ['type' => 'text']],
    ], 'legacy');
    (new \CometCMS\Storage\JsonStore(comet_test_workspace_path() . '/site/forms'))->write([
        'id' => 'old-form', 'type' => 'legacy', 'name' => 'Contact', 'success_message' => 'Thank you',
    ], 'old-form');
    assert_false((new ContentTypeRepository())->find('legacy')['allow_submissions']);
    assert_same([], (new FormRepository(new SiteRepository()))->all());
});

test('form upload configuration is capped by the media limit and rejects malformed and forged files', function (): void {
    global $cometConfig;
    [$site, $forms, $form] = comet_form_test_setup();
    $originalSite = $cometConfig['site'];
    $originalMedia = $cometConfig['media'];
    try {
        $cometConfig['site']['forms']['max_upload_bytes'] = 200;
        $cometConfig['media']['max_upload_bytes'] = 100;
        assert_same(100, \CometCMS\Site\FormUploads::maxBytes());
        $input = ['fields' => ['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => '1']];
        $submit = new FormSubmission($site, $forms, ContentRepository::make($site->workspace()));
        $fake = ['fields' => ['name' => ['attachment' => 'test.txt'], 'tmp_name' => ['attachment' => __FILE__], 'error' => ['attachment' => UPLOAD_ERR_OK], 'size' => ['attachment' => 10], 'type' => ['attachment' => 'text/plain']]];
        assert_same('Invalid uploaded file.', $submit->submit($form, $input, 'visitor', '', $fake)['errors']['attachment']);
        unset($fake['fields']['size']['attachment']);
        assert_same('Invalid upload data.', $submit->submit($form, $input, 'visitor', '', $fake)['errors']['attachment']);
        $fake['fields'] = 'wrong';
        assert_same(FormSubmission::ERROR, $submit->submit($form, $input, 'visitor', '', $fake)['status']);
        assert_same(FormSubmission::SENT, $submit->submit($form, $input + [FormSubmission::HONEYPOT => 'bot'], 'visitor', '', $fake)['status']);
        $limiter = new RateLimiter($site->workspace()->path('cache') . '/upload-limits');
        $cometConfig['site']['forms']['rate_limit_attempts'] = 1;
        $limited = new FormSubmission($site, $forms, ContentRepository::make($site->workspace()), $limiter);
        $limited->submit($form, $input, 'visitor');
        assert_true(isset($limited->submit($form, $input, 'visitor', '', $fake)['errors']['form']));
        assert_same([], (new \CometCMS\Media\MediaRepository($site->workspace()))->files());
    } finally {
        $cometConfig['site'] = $originalSite;
        $cometConfig['media'] = $originalMedia;
    }
});

test('multipart form uploads are private, validated, isolated and rolled back on failure', function (): void {
    $root = comet_site_preview_test_install();
    $configPath = $root . '/config/config.php';
    file_put_contents($configPath, str_replace("'max_upload_bytes' => 10 * 1024 * 1024,", "'max_upload_bytes' => 1024,\n            'rate_limit_attempts' => 100,", file_get_contents($configPath)));
    $bootstrap = 'require ' . var_export($root . '/app/bootstrap.php', true) . ';';
    $bootstrap .= '\\CometCMS\\Workspaces\\WorkspaceContext::setActive("form-uploads"); (new \\CometCMS\\Workspaces\\WorkspaceContext("form-uploads"))->ensure();';
    $formId = trim(comet_test_run_php(['-r', $bootstrap . <<<'PHP'
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'uploads', 'allow_submissions' => true, 'visibility' => 'private', 'fields' => [
    'email' => ['type' => 'text', 'required' => true],
    'attachment' => ['type' => 'media', 'required' => true],
    'extras' => ['type' => 'media', 'multiple' => true],
]]);
echo (new \CometCMS\Site\FormRepository(new \CometCMS\Site\SiteRepository()))->all()[0]['id'];
PHP]));
    $port = 24080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $root . '/router.php');
    $post = static function (array $fields, array $files = []) use ($port, $formId): array {
        $boundary = 'comet-' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $key => $value) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $key . "\"\r\n\r\n" . $value . "\r\n";
        }
        foreach ($files as [$key, $name, $contents]) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $key . '"; filename="' . $name . "\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $contents . "\r\n";
        }
        $body .= '--' . $boundary . "--\r\n";
        $response = file_get_contents('http://127.0.0.1:' . $port . '/cms/_site/forms/form-uploads/' . $formId, false, stream_context_create(['http' => [
            'ignore_errors' => true, 'method' => 'POST',
            'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\nAccept: application/json\r\n",
            'content' => $body,
        ]]));
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : (${'http_response_header'} ?? []);
        preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0] ?? '', $match);
        return [(int) ($match[1] ?? 0), json_decode((string) $response, true)];
    };
    $state = static fn(): array => json_decode(comet_test_run_php(['-r', $bootstrap . <<<'PHP'
$media = new \CometCMS\Media\MediaRepository();
echo json_encode(['files' => $media->files(), 'entries' => \CometCMS\Content\ContentRepository::make()->all('uploads')]);
PHP]), true);
    try {
        usleep(300000);
        $valid = ['fields[email]' => 'ada@example.com'];
        $file = ['fields[attachment]', 'notes.txt', 'A useful attachment.'];
        [$status, $result] = $post($valid, [$file, ['fields[extras][]', 'one.txt', 'First attachment'], ['fields[extras][]', 'two.txt', 'Second attachment'], ['fields[unknown]', 'ignored.txt', 'Ignored']]);
        assert_same(200, $status, 'Valid multipart upload: ' . json_encode($result));
        $stored = $state();
        assert_same(3, count($stored['files']));
        assert_same(1, count($stored['entries']));
        assert_same(2, count($stored['entries'][0]['extras']));
        foreach ($stored['files'] as $item) {
            assert_same('private', $item['visibility']);
        }
        $name = $stored['entries'][0]['attachment'][0];
        assert_file_exists_at($root . '/storage/workspaces/form-uploads/media/' . $name);
        assert_false(is_file($root . '/storage/workspaces/default/media/' . $name));
        assert_false(is_file($root . '/storage/media/' . $name));
        file_get_contents('http://127.0.0.1:' . $port . '/cms/media/form-uploads/' . rawurlencode($name), false, stream_context_create(['http' => ['ignore_errors' => true]]));
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : (${'http_response_header'} ?? []);
        assert_true(str_contains($responseHeaders[0] ?? '', '401'), 'Private attachments must require authentication.');

        $cases = [
            [$valid, [], 'attachment'],
            [$valid + ['fields[attachment][]' => $name], [], 'attachment'],
            [$valid, [['fields[attachment][]', 'a.txt', 'First'], ['fields[attachment][]', 'b.txt', 'Second']], 'attachment'],
            [$valid, [['fields[attachment]', 'large.txt', str_repeat('x', 1025)]], 'attachment'],
            [$valid, [['fields[attachment]', 'a.txt', str_repeat('a', 600)], ['fields[extras][]', 'b.txt', str_repeat('b', 600)]], 'extras'],
            [$valid, [['fields[attachment]', 'bad.html', '<html><body>Disallowed</body></html>']], 'attachment'],
            [$valid, [['fields[attachment]', 'script.php', 'plain text disguised as executable']], 'attachment'],
            [$valid, [['fields[attachment]', 'empty.txt', '']], 'attachment'],
            [[], [$file], 'email'],
            [$valid, [$file, ['fields[extras][]', 'bad.html', '<html>Disallowed</html>']], 'extras'],
            [$valid + ['MAX_FILE_SIZE' => '1'], [$file], 'attachment'],
        ];
        foreach ($cases as [$fields, $files, $error]) {
            [$status, $result] = $post($fields, $files);
            assert_same(422, $status);
            assert_true(isset($result['error']['fields'][$error]), 'Expected error for ' . $error);
            assert_same(3, count($state()['files']), 'Failed submissions must leave no uploaded files.');
        }
        [$status, $result] = $post($valid + [FormSubmission::HONEYPOT => 'bot'], [$file]);
        assert_same(200, $status, 'Honeypot submission: ' . json_encode($result));
        assert_same(3, count($state()['files']));
        assert_same(422, $post($valid + ['padding' => str_repeat('x', 264000)], [$file])[0]);
        assert_same(3, count($state()['files']));
        // A file exactly at the configured limit is accepted.
        // Repeated characters without line breaks can be detected as binary on Linux.
        $boundaryText = str_pad("A plain text attachment.\n", 1024, "More plain text.\n");
        assert_same(1024, strlen($boundaryText));
        [$status, $result] = $post($valid, [['fields[attachment]', 'boundary.txt', $boundaryText]]);
        assert_same(200, $status, 'Upload at the size limit: ' . json_encode($result));
        assert_same(4, count($state()['files']));
        // Exhaust the same visitor/form limiter before sending another genuine upload.
        comet_test_run_php(['-r', $bootstrap . '$limiter = new \\CometCMS\\Core\\RateLimiter((new \\CometCMS\\Site\\SiteRepository())->workspace()->path("cache") . "/form-limits"); for ($i = 0; $i < 100; $i++) { $limiter->hit(' . var_export('form:' . $formId, true) . ', "127.0.0.1", 100, 600); }']);
        [$status, $result] = $post($valid, [$file]);
        assert_same(422, $status);
        assert_true(isset($result['error']['fields']['form']));
        assert_same(4, count($state()['files']));
    } finally {
        comet_test_stop_process($server);
    }
});

test('required multiple media inputs render accessible labels and the configured upload budget', function (): void {
    global $cometConfig;
    comet_site_test_setup();
    (new ContentTypeRepository())->save(['name' => 'applications', 'allow_submissions' => true, 'fields' => [
        'documents' => ['type' => 'media', 'required' => true, 'multiple' => true, 'label' => 'Your documents', 'description' => 'Attach your application.', 'layout' => ['width' => '1/2']],
    ]]);
    $site = new SiteRepository();
    $form = (new FormRepository($site))->all()[0];
    $site->saveLayout('blog', 'detail', [comet_component_test_section([LayoutSchema::node('form', ['form' => $form['id']])])]);
    $original = $cometConfig['site'];
    try {
        $cometConfig['site']['forms']['max_upload_bytes'] = 5 * 1048576;
        $document = new DOMDocument();
        @$document->loadHTML(comet_site_test_get('/blog/hello')['body']);
        $xpath = new DOMXPath($document);
        $file = $xpath->query('//input[@type="file"]')->item(0);
        assert_same('fields[documents][]', $file->getAttribute('name'));
        assert_true($file->hasAttribute('required'));
        assert_true($file->hasAttribute('multiple'));
        assert_true(str_contains($file->parentNode->getAttribute('class'), 'c-form-w-1-2'));
        assert_true(str_contains($xpath->query('//label[@for="' . $file->getAttribute('id') . '"]')->item(0)->textContent, 'Your documents'));
        $help = $document->getElementById($file->getAttribute('aria-describedby'));
        assert_true(str_contains($help->textContent, 'Attach your application.'));
        assert_true(str_contains($help->textContent, '5 MiB'));
        assert_same('5242880', $xpath->query('//input[@name="MAX_FILE_SIZE"]')->item(0)->getAttribute('value'));
    } finally {
        $cometConfig['site'] = $original;
    }
});
