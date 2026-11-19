<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use InvalidArgumentException;

/**
 * An identifier of a member, a guardian or a request that cannot be kept: empty, too long, holding whitespace or a
 * control character, or holding an `@`, the sign of an e-mail address where a stable identifier belongs.
 */
final class InvalidIdentifier extends InvalidArgumentException implements GuardianConsentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
