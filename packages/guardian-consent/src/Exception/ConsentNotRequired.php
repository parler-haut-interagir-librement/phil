<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Exception;

use RuntimeException;

/**
 * A guardian's consent asked, given or withdrawn for a member who is not, or no longer, below the age of consent.
 */
final class ConsentNotRequired extends RuntimeException implements GuardianConsentException
{
    public static function notAMinor(): self
    {
        return new self('The member is not below the age of consent: no guardian consents for them.');
    }

    public static function handoverReached(): self
    {
        return new self('The member has reached the age of consent: the consent is theirs to give.');
    }

    public static function handoverNotReached(): self
    {
        return new self('The member has not reached the age of consent: their guardian consents for them.');
    }
}
