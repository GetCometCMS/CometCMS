<?php

declare(strict_types=1);

namespace CometCMS\Fields;

use CometCMS\Site\LayoutSchema;

/**
 * An entry's own page content: sections designed in the page builder, shown
 * in the page content place of its type's detail layout. Empty means the
 * entry shows the layout's default content.
 *
 * The value is {format_version, nodes} and shared by all languages; texts
 * inside it are translated like a layout's. Content saved in another format
 * is kept as it is until it is saved again, never dropped.
 */
final class PageFieldType extends AbstractFieldType
{
    public function validate(mixed $value, array $config, array $context = []): array
    {
        if ($value !== null && !is_array($value)) {
            return $this->error('Page content must be a list of sections.', 'invalid_page');
        }

        return ['valid' => true];
    }

    public function normalize(mixed $value, array $config, array $context = []): mixed
    {
        if (!is_array($value)) {
            return null;
        }
        if (array_key_exists('nodes', $value) && ($value['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION) {
            return LayoutSchema::hasPageContent($value) ? $value : null;
        }
        $value = LayoutSchema::pageValue(array_key_exists('nodes', $value) ? $value['nodes'] : $value);

        return $value['nodes'] !== [] ? $value : null;
    }

    public function renderInput(string $name, mixed $value, array $config, array $context = []): string
    {
        return '';
    }
}
