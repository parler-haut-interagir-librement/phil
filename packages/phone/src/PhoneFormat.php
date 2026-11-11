<?php

declare(strict_types=1);

namespace Phil\Phone;

/**
 * The ways a number is written.
 */
enum PhoneFormat: string
{
    /** `+33612345678`: storing, comparing, sending to services. */
    case E164 = 'e164';
    /** `+33 6 12 34 56 78`: showing to people in any country. */
    case International = 'international';
    /** `06 12 34 56 78`: showing to people in the number's country. */
    case National = 'national';
    /** `tel:+33-6-12-34-56-78`: a link. */
    case Rfc3966 = 'rfc3966';
}
