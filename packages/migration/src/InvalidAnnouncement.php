<?php

declare(strict_types=1);

namespace Phil\Migration;

use InvalidArgumentException;

/**
 * A note, a piece of news or a channel that cannot be announced as given.
 */
final class InvalidAnnouncement extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
