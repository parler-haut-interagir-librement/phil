<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * What a proof records of a feature.
 */
enum Decision: string
{
    case Granted = 'granted';

    case Refused = 'refused';

    /** A consent granted before, withdrawn. */
    case Revoked = 'revoked';
}
