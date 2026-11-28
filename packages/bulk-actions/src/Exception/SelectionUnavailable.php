<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use RuntimeException;

/**
 * A selection token that is unknown, already used, expired or held by another person. The message does not say which,
 * so a token cannot be probed.
 */
final class SelectionUnavailable extends RuntimeException implements BulkActionsException
{
    public static function create(): self
    {
        return new self('The selection is no longer available: select the items again.');
    }
}
