<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Exception;

use InvalidArgumentException;

/**
 * A value the library cannot work with: a key, a campaign, a recipient, a moment, an answer, a schedule or an address.
 */
final class InvalidConfiguration extends InvalidArgumentException implements AccessLinksException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
