<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Exception;

use RuntimeException;

/**
 * An image a credential cannot be baked into or read from: not a PNG or an SVG, malformed, too large, or holding
 * more than one credential.
 */
final class InvalidImage extends RuntimeException implements OpenBadgesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
