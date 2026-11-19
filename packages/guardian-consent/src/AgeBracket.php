<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * Where an age falls against a jurisdiction's two thresholds: the age of consent and the age of majority.
 */
enum AgeBracket: string
{
    /** Below the age of consent: the holder of parental authority consents for the person. */
    case BelowConsentAge = 'below-consent-age';

    /** At or above the age of consent but below majority: the person consents for themselves. */
    case MinorAtConsentAge = 'minor-at-consent-age';

    /** At or above the age of majority. */
    case Adult = 'adult';

    public function needsGuardian(): bool
    {
        return self::BelowConsentAge === $this;
    }
}
