<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use InvalidArgumentException;

/**
 * A setting of the library out of bounds: an age, a delay, a key, a list of jurisdictions.
 */
final class InvalidConfiguration extends InvalidArgumentException implements GuardianConsentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
