<?php

declare(strict_types=1);

namespace CometCMS\Core;

final class ConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This content changed since it was loaded. Your changes have not been saved. Keep the editor open to resolve the conflict.', 409);
    }
}
