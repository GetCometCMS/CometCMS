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
        'name' => 'inquiries',
        'visibility' => 'private',
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
    $form = $forms->create([
        'name' => 'Contact',
        'type' => 'inquiries',
        'success_message' => 'Thanks, we will be in touch.',
    ]);

    return [$site, $forms, $form];
}

function comet_form_test_submit(SiteRepository $site, FormRepository $forms, array $form, array $input, ?RateLimiter $limiter = null): array
{
    return (new FormSubmission($site, $forms, ContentRepository::make($site->workspace()), $limiter))->submit($form, $input, '203.0.113.5');
}

test('forms show every field of their type that visitors can fill in', function (): void {
    [, $forms, $form] = comet_form_test_setup();

    assert_same(['name', 'email', 'message', 'topic', 'consent'], array_column($forms->inputs($form), 'key'), 'Title, slug and media fields are not asked for.');
    assert_same('Your name', $forms->inputs($form)[0]['label']);
    assert_same('draft', $form['status']);
    assert_same('none', $form['captcha']);

    (new ContentTypeRepository())->save(['name' => 'applications', 'fields' => ['cv' => ['type' => 'media', 'required' => true]]]);
    $forms = new FormRepository(new SiteRepository());
    $rejected = static function (array $input) use ($forms): array {
        try {
            $forms->create($input);
        } catch (ValidationException $e) {
            return $e->fields();
        }
        throw new RuntimeException('Expected a validation error.');
    };
    assert_true(isset($rejected(['name' => 'Apply', 'type' => 'applications'])['type']), 'A required field the form cannot show would reject every submission.');
    assert_true(isset($rejected(['name' => 'Single page', 'type' => 'about'])['type']), 'Single pages already have their one entry.');
    assert_true(isset($rejected(['name' => '', 'type' => 'inquiries'])['name']));

    $updated = $forms->update($form['id'], ['name' => 'Kontakt', 'type' => 'inquiries', 'status' => 'published', 'captcha' => 'turnstile']);
    assert_same('published', $updated['status']);
    assert_same('none', $updated['captcha'], 'Captchas are not supported yet.');
    assert_same($form['created_at'], $updated['created_at']);
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
    assert_same([], $entry['attachment'], 'Fields the form does not show stay empty.');
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
    assert_same(0, $xpath->query('.//*[@name="fields[attachment]"]', $element)->length);

    $anchor = $xpath->query('.//input[@name="_anchor"]', $element)->item(0)->getAttribute('value');
    $sent = $document->getElementById($anchor . '-sent');
    assert_true($sent !== null && str_contains($sent->textContent, 'Thanks, we will be in touch.'));
    assert_true($document->getElementById($anchor . '-error') !== null);

    assert_same([['target' => 'layout', 'type' => 'blog', 'variant' => 'detail']], $site->formUsages($form['id']));
    $forms->delete($form['id']);
    assert_same(0, substr_count(comet_site_test_get('/blog/hello')['body'], 'c-form-grid'));
});

test('posting a form stores the entry and sends the visitor back to the page', function (): void {
    $root = comet_site_preview_test_install();
    $formId = trim(comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . ';' . <<<'PHP'
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'inquiries', 'visibility' => 'private', 'fields' => [
    'email' => ['type' => 'text', 'required' => true],
    'message' => ['type' => 'textarea'],
]]);
echo (new \CometCMS\Site\FormRepository(new \CometCMS\Site\SiteRepository()))->create(['name' => 'Contact', 'type' => 'inquiries'])['id'];
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
        preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0] ?? '', $match);
        $location = '';
        foreach ($http_response_header ?? [] as $header) {
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
