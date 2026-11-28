<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use InvalidArgumentException;

/**
 * An identifier, a filter, a change or a setting outside what the library accepts.
 */
final class InvalidInput extends InvalidArgumentException implements BulkActionsException
{
    public static function because(string $field, string $rule): self
    {
        return new self(\sprintf('The %s %s.', $field, $rule));
    }
}
