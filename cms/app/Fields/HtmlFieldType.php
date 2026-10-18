<?php

declare(strict_types=1);

namespace CometCMS\Fields;

use CometCMS\Core\HtmlSanitizer;
use CometCMS\Core\Security;

final class HtmlFieldType extends TextareaFieldType
{
    public function normalize(mixed $value, array $config, array $context = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return HtmlSanitizer::sanitize((string) $value);
    }

    public function renderInput(string $name, mixed $value, array $config, array $context = []): string
    {
        return '<textarea name="' . Security::e($name) . '" rows="8">' . Security::e((string) $value) . '</textarea>';
    }
}
