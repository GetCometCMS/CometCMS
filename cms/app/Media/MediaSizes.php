<?php

declare(strict_types=1);

namespace CometCMS\Media;

use CometCMS\Core\ValidationException;
use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;

/**
 * The named image sizes every upload gets, as the longest side in pixels.
 * Images keep their proportions; an original smaller than a size is used as is.
 */
final class MediaSizes
{
    public const NAMES = ['thumbnail', 'medium', 'large'];

    public const MIN = 64;

    public const MAX = 4096;

    private const DEFAULTS = ['thumbnail' => 400, 'medium' => 960, 'large' => 1920];

    private JsonStore $store;

    public function __construct(WorkspaceContext $workspace)
    {
        $this->store = new JsonStore($workspace->root());
    }

    /** @return array{thumbnail: int, medium: int, large: int} */
    public function all(): array
    {
        $configured = comet_config('media.sizes', []);
        $stored = $this->store->read('media-sizes') ?? [];
        $sizes = [];
        foreach (self::NAMES as $name) {
            $value = $stored[$name] ?? (is_array($configured) ? ($configured[$name] ?? null) : null) ?? self::DEFAULTS[$name];
            $sizes[$name] = max(self::MIN, min(self::MAX, (int) $value));
        }

        return $sizes;
    }

    /** Each size must be larger than the one before, so a chosen size is an upper limit. */
    public function save(array $input): array
    {
        $sizes = [];
        $errors = [];
        $previous = 0;
        foreach (self::NAMES as $name) {
            $value = filter_var($input[$name] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < self::MIN || $value > self::MAX) {
                $errors[$name] = 'Choose a size between ' . self::MIN . ' and ' . self::MAX . ' pixels.';
                continue;
            }
            if ($value <= $previous) {
                $errors[$name] = 'Each size must be larger than the one before.';
            }
            $sizes[$name] = $previous = $value;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->store->write($sizes, 'media-sizes');

        return $sizes;
    }
}
