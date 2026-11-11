<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses to build a credential from: an issuer, an achievement, a recipient, a key, a status
 * entry or an identifier that does not hold together.
 */
final class InvalidBadge extends InvalidArgumentException implements OpenBadgesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
