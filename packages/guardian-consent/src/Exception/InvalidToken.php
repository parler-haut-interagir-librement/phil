<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use RuntimeException;

/**
 * A confirmation token that opens nothing: malformed, unknown, already used, or not the one issued. The message is
 * the same in every case, so that it tells nothing of which one it was.
 */
final class InvalidToken extends RuntimeException implements GuardianConsentException
{
    public static function rejected(): self
    {
        return new self('The confirmation link is not valid.');
    }
}
