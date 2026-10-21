<?php

declare(strict_types=1);

namespace CometCMS\Content;

use CometCMS\Cache\ApiCache;
use CometCMS\Core\Security;
use CometCMS\Core\ValidationException;
use CometCMS\Localization\LanguageSettings;
use CometCMS\Localization\LocalizedValue;
use CometCMS\Site\FormRepository;
use CometCMS\Site\SiteRepository;
use CometCMS\Storage\JsonStore;
use CometCMS\Storage\SettingsStore;
use CometCMS\Workspaces\WorkspaceContext;

final class ContentTypeRepository
{
    private JsonStore $store;
    private SettingsStore $settings;

    private WorkspaceContext $workspace;

    public function __construct(?WorkspaceContext $workspace = null)
    {
        $this->workspace = $workspace ?? WorkspaceContext::active();
        WorkspaceContext::setActive($this->workspace->slug());
        $this->workspace->ensure();
        $this->store = new JsonStore($this->workspace->path('content-types'));
        $this->settings = new SettingsStore();
    }

    public function all(): array
    {
        $types = [];

        foreach ((array) comet_config('content_types', []) as $name => $schema) {
            if (is_array($schema)) {
                $types[$name] = $this->normalize($name, $schema);
            }
        }

        foreach ($this->store->all() as $schema) {
            $name = (string) ($schema['name'] ?? '');

            if ($name !== '') {
                $types[$name] = $this->normalize($name, $schema);
            }
        }

        foreach ((new JsonStore($this->workspace->path('content')))->directories() as $collection) {
            $types[$collection] ??= $this->fallback($collection);
        }

        $order = array_flip($this->contentTypeOrder());

        uasort($types, static function (array $a, array $b) use ($order): int {
            $aOrder = $order[(string) ($a['name'] ?? '')] ?? PHP_INT_MAX;
            $bOrder = $order[(string) ($b['name'] ?? '')] ?? PHP_INT_MAX;

            return $aOrder <=> $bOrder
                ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
                ?: strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_values($types);
    }

    public function find(string $name): array
    {
        Security::assertSafeName($name);
        $stored = $this->store->read($name);

        if ($stored !== null) {
            return $this->normalize($name, $stored);
        }

        $configured = comet_config('content_types.' . $name);

        if (is_array($configured)) {
            return $this->normalize($name, $configured);
        }

        return $this->fallback($name);
    }

    public function exists(string $name): bool
    {
        Security::assertSafeName($name);

        if ($this->store->read($name) !== null) {
            return true;
        }

        if (is_array(comet_config('content_types.' . $name))) {
            return true;
        }

        return is_dir($this->workspace->path('content') . '/' . $name);
    }

    public function save(array $schema): void
    {
        $name = Security::slug((string) ($schema['name'] ?? ''));
        $this->store->synchronized(fn() => $this->saveUnlocked($schema), $name);
    }

    private function saveUnlocked(array $schema): void
    {
        $name = Security::slug((string) ($schema['name'] ?? ''));
        if (!empty($schema['api_enabled'])) {
            $schema['visibility'] = 'public';
        }
        $previous = $this->exists($name) ? $this->find($name) : null;
        if ($previous !== null && isset($schema['revision']) && $schema['revision'] !== $previous['revision']) throw new \CometCMS\Core\ConflictException();
        $schema['api_enabled'] ??= $previous['api_enabled'] ?? false;
        $schema['allow_submissions'] ??= $previous['allow_submissions'] ?? false;
        if (!array_key_exists('submission_settings', $schema)) {
            $schema['submission_settings'] = $previous['submission_settings'] ?? null;
        }
        $schema = $this->normalize($name, $schema);
        if ($schema['allow_submissions']) {
            $fillable = array_filter($schema['fields'], static fn(array $config, string $key): bool => FormRepository::accepts($key, $config, $schema), ARRAY_FILTER_USE_BOTH);
            $unsupported = array_filter(FormRepository::unsupported($schema), static fn(array $config): bool => !empty($config['required']));
            if ($fillable === [] || $unsupported !== []) {
                throw new ValidationException(['allow_submissions' => 'Submissions need at least one content field visitors can fill in, and all required content fields must support forms.']);
            }
        }
        // Language settings are workspace-owned; discard old per-type overrides on save.
        $stored = array_diff_key($schema, array_flip(['revision', 'locales', 'default_locale']));
        $this->store->write($stored, $name);
        $this->ensureInOrder($name);
        ApiCache::fromConfig($this->workspace)->clear();
    }

    public function reorder(array $names): array
    {
        $names = array_values(array_unique(array_map(
            static fn(mixed $name): string => Security::slug((string) $name),
            $names
        )));
        $names = array_values(array_filter($names, static fn(string $name): bool => $name !== ''));

        $current = [];
        foreach ($this->all() as $type) {
            $current[(string) $type['name']] = $type;
        }

        $orderedNames = [];
        foreach ($names as $name) {
            if (isset($current[$name])) {
                $orderedNames[] = $name;
            }
        }

        foreach (array_keys($current) as $name) {
            if (!in_array($name, $orderedNames, true)) {
                $orderedNames[] = $name;
            }
        }

        $this->saveContentTypeOrder($orderedNames);
        ApiCache::fromConfig($this->workspace)->clear();

        return $this->all();
    }

    public function delete(string $name): void
    {
        Security::assertSafeName($name);
        $this->store->delete($name);
        $this->removeFromOrder($name);

        // Also remove the content directory so the type does not reappear
        // as a phantom fallback type in all().
        $this->deleteDirectory($this->workspace->path('content') . '/' . $name);
        $this->deleteDirectory($this->workspace->path('trash') . '/content/' . $name);
        $this->deleteDirectory($this->workspace->path('revisions') . '/content/' . $name);
        (new SiteRepository($this->workspace, $this))->forgetType($name);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (glob($path . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }

        rmdir($path);
    }

    private function normalize(string $name, array $schema): array
    {
        $name = Security::slug((string) ($schema['name'] ?? $name));
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $languages = (new LanguageSettings($this->workspace))->all();

        // Existing forms keep working until their type explicitly disables submissions.
        $legacyForms = [];
        $formsPath = $this->workspace->path('site') . '/forms';
        if (!array_key_exists('allow_submissions', $schema) && is_dir($formsPath)) {
            $legacyForms = array_filter((new JsonStore($formsPath))->all(), static fn(array $form): bool => ($form['type'] ?? '') === $name);
        }
        // Stored schemas from before API opt-in already chose their visibility.
        // New schemas omit it and start with the API disabled.
        $apiEnabled = (bool) ($schema['api_enabled'] ?? array_key_exists('visibility', $schema));

        $fields['title'] ??= ['type' => 'text', 'required' => true];
        $fields['slug'] ??= ['type' => 'slug', 'required' => true, 'unique' => true];
        foreach ($fields as $key => &$config) {
            if (in_array($key, ['values', 'publication', 'revision', 'reset_fields', 'locale', 'translations'], true)) throw new ValidationException(['fields' => 'This field key is reserved: ' . $key]);
            $config = $this->fieldMetadata($config, $languages['locales']);
        }
        unset($config);

        $normalized = [
            'name' => $name,
            'label' => (string) ($schema['label'] ?? ucfirst(str_replace(['-', '_'], ' ', $name))),
            'icon' => $this->normalizeIcon($schema['icon'] ?? 'mdi:file-document-outline'),
            // API exposure and website pages are independent. Keep legacy private
            // protection until the owner explicitly enables the public API.
            'api_enabled' => $apiEnabled,
            'visibility' => ($schema['visibility'] ?? 'public') === 'private' ? 'private' : 'public',
            'allow_submissions' => (bool) ($schema['allow_submissions'] ?? ($legacyForms !== [])),
            'submission_settings' => is_array($schema['submission_settings'] ?? null) ? FormRepository::settings($schema['submission_settings']) : null,
            'slug_field' => (string) ($schema['slug_field'] ?? 'slug'),
            'slug_source' => (string) ($schema['slug_source'] ?? 'title'),
            'locales' => $languages['locales'],
            'default_locale' => $languages['default_locale'],
            'fields' => $fields,
        ];
        return $normalized + ['revision' => LocalizedValue::hash($normalized)];
    }

    private function fieldMetadata(array $config, array $locales): array
    {
        $text = static fn(mixed $value): string => is_scalar($value) ? \CometCMS\Site\Text::limit(trim((string) $value), 2000) : '';
        foreach (['label', 'description', 'placeholder'] as $key) {
            if (array_key_exists($key, $config)) $config[$key] = LocalizedValue::normalize($config[$key], $text, $locales);
        }
        if (($config['type'] ?? '') === 'select' && is_array($config['options'] ?? null) && !array_is_list($config['options'])) {
            foreach ($config['options'] as &$label) $label = LocalizedValue::normalize($label, $text, $locales);
            unset($label);
        }
        foreach ($config['subfields'] ?? [] as $index => $subfield) $config['subfields'][$index] = $this->fieldMetadata($subfield, $locales);
        return $config;
    }

    private function contentTypeOrder(): array
    {
        $settings = $this->settings->all();
        $key = 'content_type_order_' . $this->workspace->slug();
        $order = is_array($settings[$key] ?? null) ? $settings[$key] : [];

        return array_values(array_filter(array_map(
            static fn(mixed $name): string => Security::slug((string) $name),
            $order
        ), static fn(string $name): bool => $name !== ''));
    }

    private function saveContentTypeOrder(array $order): void
    {
        $settings = $this->settings->all();
        $settings['content_type_order_' . $this->workspace->slug()] = array_values($order);
        $this->settings->save($settings);
    }

    private function ensureInOrder(string $name): void
    {
        $order = $this->contentTypeOrder();

        if (!in_array($name, $order, true)) {
            $order[] = $name;
            $this->saveContentTypeOrder($order);
        }
    }

    private function removeFromOrder(string $name): void
    {
        $this->saveContentTypeOrder(array_values(array_filter(
            $this->contentTypeOrder(),
            static fn(string $orderedName): bool => $orderedName !== $name
        )));
    }

    private function normalizeIcon(mixed $icon): string
    {
        $icon = strtolower(trim((string) $icon));
        $icon = preg_replace('/[^a-z0-9:._-]/', '', $icon) ?? '';

        return $icon !== '' ? $icon : 'mdi:file-document-outline';
    }

    private function fallback(string $name): array
    {
        return $this->normalize($name, [
            'name' => $name,
            'icon' => 'mdi:file-document-outline',
            'fields' => [
                'title' => ['type' => 'text', 'required' => true],
                'slug' => ['type' => 'slug', 'required' => true, 'unique' => true],
                'summary' => ['type' => 'textarea'],
            ],
        ]);
    }
}
