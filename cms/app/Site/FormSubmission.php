<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Content\ContentRepository;
use CometCMS\Core\RateLimiter;
use CometCMS\Core\ValidationException;
use CometCMS\Logging\Logger;

/**
 * Turns a posted website form into a new entry of the form's content type.
 *
 * Only the fields the form shows are read from the request; status, author
 * and everything else come from the form's settings. The result is one of
 * the outcomes the rendered form can show (see NodeRenderer::form()).
 */
final class FormSubmission
{
    public const SENT = 'sent';
    public const ERROR = 'error';

    /** Longest value accepted for one input, in characters. */
    private const MAX_VALUE_LENGTH = 20000;

    private const INVALID = "\0invalid";

    /** Name of the honeypot input: hidden from people, tempting for bots. */
    public const HONEYPOT = 'website_url';

    public function __construct(
        private readonly SiteRepository $site,
        private readonly FormRepository $forms,
        private readonly ContentRepository $content,
        private readonly ?RateLimiter $limiter = null,
        private readonly ?FormCaptcha $captcha = null,
    ) {}

    public static function make(SiteRepository $site): self
    {
        $workspace = $site->workspace();

        return new self($site, new FormRepository($site), ContentRepository::make($workspace), new RateLimiter($workspace->path('cache') . '/form-limits'));
    }

    /**
     * @param array $input  the request body ($_POST)
     * @param array $files  uploaded files ($_FILES)
     * @return array{status: string, entry?: array, errors?: array}
     */
    public function submit(array $form, array $input, string $visitor, string $locale = '', array $files = []): array
    {
        // Bots that fill in every input are told it worked, and nothing is stored.
        $honeypot = $input[self::HONEYPOT] ?? '';
        if (!is_string($honeypot) || trim($honeypot) !== '') {
            return ['status' => self::SENT];
        }

        if ($this->limiter !== null && $this->limiter->hit('form:' . $form['id'], $visitor, max(1, (int) comet_config('site.forms.rate_limit_attempts', 5)), max(60, (int) comet_config('site.forms.rate_limit_window_seconds', 600)))) {
            return ['status' => self::ERROR, 'errors' => ['form' => 'Too many submissions. Please try again later.']];
        }

        $type = $this->site->types()[$form['type']] ?? null;
        $inputs = $this->forms->inputs($form);
        if ($type === null || empty($type['allow_submissions']) || $inputs === []) {
            return ['status' => self::ERROR, 'errors' => ['form' => 'This form is not available.']];
        }

        if (!($this->captcha ?? new FormCaptcha($this->site))->verify($form, $input, $visitor)) {
            return ['status' => self::ERROR, 'errors' => ['captcha' => 'Spam verification failed. Please reload the form and try again.']];
        }

        $values = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $payload = [];
        $errors = [];
        foreach ($inputs as $field) {
            if (($field['config']['type'] ?? '') === 'media') {
                continue;
            }
            $value = $this->value($values[$field['key']] ?? null, $field['config']);
            if ($value === self::INVALID) {
                $errors[$field['key']] = 'Invalid value.';
                continue;
            }
            // A required checkbox (such as a privacy consent) has to be ticked.
            if ($value === false && ($field['config']['type'] ?? '') === 'boolean' && !empty($field['config']['required'])) {
                $errors[$field['key']] = 'This field is required.';
                continue;
            }
            $payload[$field['key']] = $value;
        }

        if ($errors !== []) {
            return ['status' => self::ERROR, 'errors' => $errors];
        }

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $payload['title'] = $form['name'] . ' – ' . gmdate('Y-m-d H:i:s');
        }
        $payload['status'] = $form['status'];
        // A submission is a new record; the form's display language is independent of stored values.
        $payload['locale'] = $type['default_locale'];

        $uploads = new FormUploads($this->site->workspace());
        $saved = false;
        try {
            $payload = array_replace($payload, $uploads->store($inputs, $files));
            $entry = $this->content->save($form['type'], $payload, ['id' => null, 'username' => 'Website form: ' . $form['name']]);
            $saved = true;
        } catch (ValidationException $e) {
            return ['status' => self::ERROR, 'errors' => $e->fields()];
        } finally {
            if (!$saved) {
                $uploads->rollback();
            }
        }

        (new Logger())->info('site.form_submitted', ['form' => $form['id'], 'type' => $form['type'], 'slug' => (string) ($entry['id'] ?? '')]);

        return ['status' => self::SENT, 'entry' => $entry];
    }

    /** The posted value in the shape the field type expects; INVALID when it has the wrong shape. */
    private function value(mixed $value, array $config): mixed
    {
        $type = (string) ($config['type'] ?? 'text');

        if ($type === 'boolean') {
            return is_scalar($value) && in_array((string) $value, ['1', 'on', 'true', 'yes'], true);
        }

        if ($type === 'select' && !empty($config['multiple'])) {
            if ($value === null || $value === '') {
                return [];
            }
            if (!is_array($value) || count($value) > 100) {
                return self::INVALID;
            }
            foreach ($value as $item) {
                if (!is_scalar($item)) {
                    return self::INVALID;
                }
            }

            return array_values(array_map(static fn(mixed $item): string => trim((string) $item), $value));
        }

        if ($value === null) {
            return '';
        }
        if (!is_scalar($value) || mb_strlen((string) $value) > self::MAX_VALUE_LENGTH) {
            return self::INVALID;
        }

        return in_array($type, ['textarea', 'markdown'], true) ? str_replace("\r\n", "\n", (string) $value) : trim((string) $value);
    }
}
