<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use RuntimeException;

/**
 * A confirmation token issued for a request whose delay has run out: the guardian is asked again.
 */
final class ExpiredRequest extends RuntimeException implements GuardianConsentException
{
    public static function create(): self
    {
        return new self('The confirmation link has expired: a new request has to be sent.');
    }
}
