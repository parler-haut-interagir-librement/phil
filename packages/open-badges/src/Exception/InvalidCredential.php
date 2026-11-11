<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Exception;

use RuntimeException;

/**
 * A credential that does not verify: malformed, signed with another algorithm or key, altered, not yet valid,
 * expired, or using JSON-LD the library does not process.
 */
final class InvalidCredential extends RuntimeException implements OpenBadgesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
