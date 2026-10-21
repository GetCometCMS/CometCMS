<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;
use CometCMS\Localization\LocalizedValue;
use CometCMS\Localization\LanguageSettings;
use CometCMS\Core\ValidationException;
use CometCMS\Storage\JsonStore;

/**
 * Submission-enabled content types are forms. Settings live in their schema;
 * legacy forms in site/forms/{id}.json retain their IDs and placements.
 *
 * A form belongs to one content type: every submission becomes a new entry
 * of that type, and the form shows every field of that type a visitor can
 * fill in, in the type's order. Labels, help texts, required flags, options
 * and widths all come from the type's field builder, so the form and the
 * entries it creates always match. Title and slug are not asked for: entries
 * are titled with the form's name and the time they arrived.
 * Layouts place a form with a "form" element.
 *
 * Spam protection: a honeypot field and a per-visitor rate limit. The
 * `captcha` setting is reserved for Cloudflare Turnstile and Google
 * reCAPTCHA; only "none" is accepted until they are supported (both need
 * a script, so the site's Content-Security-Policy changes with them).
 */
final class FormRepository
{
    /** Field types a visitor can fill in with a plain HTML input. */
    public const FIELD_TYPES = ['text', 'textarea', 'markdown', 'number', 'range', 'boolean', 'select', 'date', 'datetime', 'color', 'media'];

    public const STATUSES = ['draft', 'published'];

    public const CAPTCHAS = ['none'];

    public const MAX_FORMS = 100;

    private JsonStore $store;

    public function __construct(private readonly SiteRepository $site)
    {
        $this->store = new JsonStore($site->workspace()->path('site') . '/forms');
    }

    /** @return list<array> forms sorted by name */
    public function all(): array
    {
        $forms = [];

        foreach ($this->store->all() as $stored) {
            $form = $this->read($stored);
            if ($form !== null) {
                $forms[] = $form;
            }
        }
        // A submission-enabled type is itself a form, ready for the builder.
        // Retain existing form IDs so previously placed forms keep working.
        foreach ($this->types() as $name => $type) {
            if (!in_array($name, array_column($forms, 'type'), true)) {
                $forms[] = $this->typeForm($type);
            }
        }
        usort($forms, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $forms;
    }

    public function find(string $id): ?array
    {
        if ($id === '' || LayoutSchema::key($id) !== $id) {
            return null;
        }

        $stored = $this->read($this->store->read($id));
        if ($stored !== null) {
            return $stored;
        }
        foreach ($this->types() as $type) {
            $form = $this->typeForm($type);
            if ($form['id'] === $id) {
                return $form;
            }
        }
        return null;
    }

    public function create(array $input): array
    {
        if (count($this->store->all()) >= self::MAX_FORMS) {
            throw new ValidationException(['name' => 'A website can have at most ' . self::MAX_FORMS . ' forms.']);
        }

        $form = $this->validate($input);
        $form['id'] = 'f' . bin2hex(random_bytes(5));
        $form['created_at'] = Security::now();

        return $this->write($form);
    }

    public function update(string $id, array $input): array
    {
        $current = $this->require($id);
        $this->assertLegacyForm($id);
        $form = $this->validate($input);
        $form['id'] = $current['id'];
        $form['created_at'] = $current['created_at'];

        return $this->write($form);
    }

    public function delete(string $id): array
    {
        $form = $this->require($id);
        $this->assertLegacyForm($id);
        $this->store->delete($id);
        $this->site->clearCache();

        return $form;
    }

    public function require(string $id): array
    {
        $form = $this->find($id);
        if ($form === null) {
            throw new \RuntimeException('Form not found.');
        }

        return $form;
    }

    /**
     * The inputs a form shows, in order: every field of its content type
     * that visitors can fill in.
     *
     * @return list<array{key: string, config: array, label: string}>
     */
    public function inputs(array $form, string $locale = ''): array
    {
        $languages = (new LanguageSettings($this->site->workspace()))->all();
        $type = LocalizedValue::tree($this->site->types()[$form['type']] ?? null, $locale ?: $languages['default_locale'], $languages['default_locale']);
        $inputs = [];

        foreach (is_array($type['fields'] ?? null) ? $type['fields'] : [] as $key => $config) {
            $key = (string) $key;
            if (is_array($config) && self::accepts($key, $config, $type)) {
                $inputs[] = ['key' => $key, 'config' => $config, 'label' => self::fieldLabel($key, $config)];
            }
        }

        return $inputs;
    }

    /** Fields of $type a form cannot ask for, keyed by field key; required ones break every submission. */
    public static function unsupported(array $type): array
    {
        $fields = [];

        foreach (is_array($type['fields'] ?? null) ? $type['fields'] : [] as $key => $config) {
            $key = (string) $key;
            if (is_array($config) && !self::isSystemField($key, $type) && !self::accepts($key, $config, $type)) {
                $fields[$key] = $config;
            }
        }

        return $fields;
    }

    /** Whether a field of $type can be filled in by visitors. */
    public static function accepts(string $key, array $config, ?array $type = null): bool
    {
        return !self::isSystemField($key, $type) && in_array((string) ($config['type'] ?? 'text'), self::FIELD_TYPES, true);
    }

    /** Title and slug are filled in by the form itself. */
    private static function isSystemField(string $key, ?array $type): bool
    {
        return in_array($key, ['title', 'slug', (string) ($type['slug_field'] ?? 'slug')], true);
    }

    public static function fieldLabel(string $key, array $config): string
    {
        $label = trim((string) ($config['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        return $key === 'title' ? 'Title' : ucfirst(str_replace(['_', '-'], ' ', $key));
    }

    /** Content types a form can submit to. */
    public function types(): array
    {
        return array_filter($this->site->types(), static fn(array $type): bool => !empty($type['allow_submissions']));
    }

    private function validate(array $input): array
    {
        $errors = [];
        $name = self::line($input['name'] ?? '', 120);
        if ($name === '') {
            $errors['name'] = 'Give the form a name.';
        }

        $typeName = LayoutSchema::key($input['type'] ?? '');
        $type = $this->types()[$typeName] ?? null;
        if ($type === null) {
            $errors['type'] = 'Choose the content type that receives the submissions.';
        } else {
            foreach (self::unsupported($type) as $key => $config) {
                if (!empty($config['required'])) {
                    $errors['type'] = 'The required field “' . self::fieldLabel((string) $key, $config) . '” cannot be filled in on a form. Make it optional or choose another content type.';
                    break;
                }
            }
            if (!isset($errors['type']) && $this->inputs(['type' => $typeName]) === []) {
                $errors['type'] = 'This content type has no fields a form can show.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $status = (string) ($input['status'] ?? 'draft');
        $captcha = (string) ($input['captcha'] ?? 'none');

        return [
            'name' => $name,
            'type' => $typeName,
            'submit_label' => LocalizedValue::normalize($input['submit_label'] ?? '', static fn(mixed $v): string => self::line($v, 80), (new LanguageSettings())->all()['locales']),
            'success_message' => LocalizedValue::normalize($input['success_message'] ?? '', static fn(mixed $v): string => self::text($v, 1000), (new LanguageSettings())->all()['locales']),
            'error_message' => LocalizedValue::normalize($input['error_message'] ?? '', static fn(mixed $v): string => self::text($v, 1000), (new LanguageSettings())->all()['locales']),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'draft',
            'captcha' => in_array($captcha, self::CAPTCHAS, true) ? $captcha : 'none',
        ];
    }

    private function read(?array $stored): ?array
    {
        $id = LayoutSchema::key($stored['id'] ?? '');
        if (!is_array($stored) || $id === '') {
            return null;
        }

        $type = $this->types()[LayoutSchema::key($stored['type'] ?? '')] ?? null;
        if ($type === null) {
            return null;
        }
        if (is_array($type['submission_settings'] ?? null)) {
            $stored = array_replace($stored, $type['submission_settings']);
        }

        return [
            'id' => $id,
            'name' => self::line($stored['name'] ?? '', 120) ?: $id,
            'type' => LayoutSchema::key($stored['type'] ?? ''),
            'submit_label' => LocalizedValue::normalize($stored['submit_label'] ?? '', static fn(mixed $v): string => self::line($v, 80), (new LanguageSettings())->all()['locales']),
            'success_message' => LocalizedValue::normalize($stored['success_message'] ?? '', static fn(mixed $v): string => self::text($v, 1000), (new LanguageSettings())->all()['locales']),
            'error_message' => LocalizedValue::normalize($stored['error_message'] ?? '', static fn(mixed $v): string => self::text($v, 1000), (new LanguageSettings())->all()['locales']),
            'status' => in_array($stored['status'] ?? '', self::STATUSES, true) ? (string) $stored['status'] : 'draft',
            'captcha' => in_array($stored['captcha'] ?? '', self::CAPTCHAS, true) ? (string) $stored['captcha'] : 'none',
            'created_at' => (string) ($stored['created_at'] ?? ''),
            'updated_at' => (string) ($stored['updated_at'] ?? ''),
        ];
    }

    private function assertLegacyForm(string $id): void
    {
        if ($this->store->read($id) === null) {
            throw new ValidationException(['type' => 'Manage this form through its content type’s submission settings.']);
        }
    }

    /** Shared per-type submission settings used by the schema editor and renderer. */
    public static function settings(array $input): array
    {
        return [
            'submit_label' => LocalizedValue::normalize($input['submit_label'] ?? '', static fn(mixed $v): string => self::line($v, 80), (new LanguageSettings())->all()['locales']),
            'success_message' => LocalizedValue::normalize($input['success_message'] ?? '', static fn(mixed $v): string => self::text($v, 1000), (new LanguageSettings())->all()['locales']),
            'error_message' => LocalizedValue::normalize($input['error_message'] ?? '', static fn(mixed $v): string => self::text($v, 1000), (new LanguageSettings())->all()['locales']),
            'status' => in_array($input['status'] ?? '', self::STATUSES, true) ? $input['status'] : 'draft',
            'captcha' => in_array($input['captcha'] ?? '', self::CAPTCHAS, true) ? $input['captcha'] : 'none',
        ];
    }

    private function typeForm(array $type): array
    {
        return [
            'id' => 'type-' . substr(hash('sha256', $type['name']), 0, 24),
            'name' => $type['label'],
            'type' => $type['name'],
            ...self::settings($type['submission_settings'] ?? []),
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    private function write(array $form): array
    {
        $form['updated_at'] = Security::now();
        $this->store->write($form, $form['id']);
        $this->site->clearCache();

        return $this->read($form) ?? $form;
    }

    private static function line(mixed $value, int $max): string
    {
        return is_scalar($value) ? trim(Text::limit(str_replace(["\r", "\n"], ' ', (string) $value), $max)) : '';
    }

    private static function text(mixed $value, int $max): string
    {
        return is_scalar($value) ? trim(Text::limit(str_replace("\r\n", "\n", (string) $value), $max)) : '';
    }
}
