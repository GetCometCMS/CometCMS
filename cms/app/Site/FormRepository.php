<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Localization\LocalizedValue;
use CometCMS\Localization\LanguageSettings;

/**
 * Submission-enabled content types are forms. Settings live in their schema.
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

    public function __construct(private readonly SiteRepository $site)
    {
    }

    /** @return list<array> forms sorted by name */
    public function all(): array
    {
        $forms = array_map(fn(array $type): array => $this->typeForm($type), array_values($this->types()));
        usort($forms, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $forms;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $form) {
            if ($form['id'] === $id) {
                return $form;
            }
        }
        return null;
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

    private static function line(mixed $value, int $max): string
    {
        return is_scalar($value) ? trim(Text::limit(str_replace(["\r", "\n"], ' ', (string) $value), $max)) : '';
    }

    private static function text(mixed $value, int $max): string
    {
        return is_scalar($value) ? trim(Text::limit(str_replace("\r\n", "\n", (string) $value), $max)) : '';
    }
}
