<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use RuntimeException;

/**
 * A withdrawal by someone who never decided on the member's consents, and whom no link names as their guardian.
 */
final class NotAGuardian extends RuntimeException implements GuardianConsentException
{
    public static function create(): self
    {
        return new self('This guardian has no consent of the member to withdraw.');
    }
}
