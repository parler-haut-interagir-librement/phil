<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use RuntimeException;

/**
 * No jurisdiction of the configuration has the code.
 */
final class UnknownJurisdiction extends RuntimeException implements GuardianConsentException
{
    public static function withCode(string $code): self
    {
        return new self(\sprintf('No jurisdiction is configured under the code "%s".', $code));
    }
}
