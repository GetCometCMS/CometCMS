<?php

declare(strict_types=1);

namespace CometCMS\Core;

final class ConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This content changed since it was loaded. Reload before saving; your draft has been preserved.', 409);
    }
}
