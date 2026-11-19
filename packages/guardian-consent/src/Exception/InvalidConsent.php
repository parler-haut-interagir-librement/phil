<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use InvalidArgumentException;

/**
 * A value of a consent out of bounds: a feature name, a version of the text, a date, an address, a list of features.
 */
final class InvalidConsent extends InvalidArgumentException implements GuardianConsentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
