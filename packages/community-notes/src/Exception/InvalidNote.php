<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Exception;

use InvalidArgumentException;

/**
 * A note that cannot be kept: an identifier or a text out of bounds.
 */
final class InvalidNote extends InvalidArgumentException implements CommunityNotesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
