<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Exception;

use InvalidArgumentException;

/**
 * A subject, a tuple, a model or a call was given a value against the rules.
 */
final class InvalidRelation extends InvalidArgumentException implements ResourcePermissionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
