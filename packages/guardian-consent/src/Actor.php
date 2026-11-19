<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * Who decided: the guardian, before the age of consent, or the member, from it.
 */
enum Actor: string
{
    case Guardian = 'guardian';

    case Member = 'member';
}
