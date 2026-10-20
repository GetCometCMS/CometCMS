<?php

declare(strict_types=1);

namespace CometCMS\Fields;

use CometCMS\Core\Security;
use CometCMS\Content\ContentRepository;

final class RelationFieldType extends AbstractFieldType
{
    public function validate(mixed $value, array $config, array $context = []): array
    {
        $multiple = (bool) ($config['multiple'] ?? false);

        if ($multiple && !$this->isEmpty($value) && !is_array($value)) {
            return $this->error('Relation value must be an array.', 'invalid_relation');
        }

        if (!$multiple && is_array($value)) {
            return $this->error('Relation value must be a single id.', 'invalid_relation');
        }

        $required = parent::validate($value, $config, $context);

        if (($required['valid'] ?? false) === false || $this->isEmpty($value)) {
            return $required;
        }

        $target = (string) ($config['target'] ?? '');

        if ($target === '') {
            return $this->error('Relation target is missing.', 'missing_relation_target');
        }

        $values = $multiple ? $this->values($value) : $this->singleValues($value);
        $repository = ContentRepository::make();

        foreach ($values as $id) {
            if ($id === '' || $repository->findByIdentifier($target, $id) === null) {
                return $this->error('Related entry does not exist: ' . $id, 'missing_relation');
            }
        }

        return ['valid' => true];
    }

    public function normalize(mixed $value, array $config, array $context = []): mixed
    {
        $repository = ContentRepository::make();
        $normalize = fn(string $id): string => $repository->stableId($repository->findByIdentifier($config['target'], $id) ?? ['id' => $id]);
        if ($config['multiple'] ?? false) return array_values(array_unique(array_map($normalize, $this->values($value))));
        $id = $this->singleValue($value);
        return $id !== null ? $normalize($id) : null;
    }

    public function renderInput(string $name, mixed $value, array $config, array $context = []): string
    {
        $target = (string) ($config['target'] ?? '');
        $multiple = (bool) ($config['multiple'] ?? false);
        $selected = $multiple ? (array) $value : [(string) $value];
        $entries = $target !== '' ? ContentRepository::make()->all($target) : [];
        $html = '<select name="' . Security::e($name) . ($multiple ? '[]' : '') . '"' . ($multiple ? ' multiple size="6"' : '') . '><option value=""></option>';

        foreach ($entries as $entry) {
            $id = (string) ($entry['uid'] ?? $entry['id'] ?? '');
            $html .= '<option value="' . Security::e($id) . '"' . (in_array($id, $selected, true) ? ' selected' : '') . '>' . Security::e($entry['title'] ?? $id) . '</option>';
        }

        return $html . '</select>';
    }

    private function singleValue(mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $id = $this->idValue($value);

        return $id !== '' ? $id : null;
    }

    private function singleValues(mixed $value): array
    {
        $id = $this->singleValue($value);

        return $id !== null ? [$id] : [];
    }

    private function values(mixed $value): array
    {
        return array_values(array_filter(array_map(
            fn(mixed $item): string => $this->idValue($item),
            is_array($value) ? $value : []
        ), static fn(string $item): bool => $item !== ''));
    }

    private function idValue(mixed $value): string
    {
        $raw = trim((string) $value);

        return preg_match('/^[A-Za-z0-9_-]+$/', $raw) ? $raw : '';
    }
}
