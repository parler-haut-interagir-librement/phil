<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * Where a member's consent to a feature stands. Only `NotRequired` and `Granted` let the feature be used: everything
 * else is a refusal, the default.
 */
enum ConsentStatus: string
{
    /** The member is not known as below the age of consent: the library has nothing to say. */
    case NotRequired = 'not-required';

    /** The consent is given: by the guardian below the age of consent, by the member from it. */
    case Granted = 'granted';

    /** A request covering the feature waits for the guardian's answer. */
    case Pending = 'pending';

    /** No consent is given, or it was refused or withdrawn. */
    case Refused = 'refused';

    /** The member has reached the age of consent and has not yet confirmed their own choices. */
    case HandoverRequired = 'handover-required';

    public function allows(): bool
    {
        return self::NotRequired === $this || self::Granted === $this;
    }
}
