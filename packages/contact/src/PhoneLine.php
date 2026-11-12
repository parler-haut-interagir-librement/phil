<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Phone\PhoneNumber;

/**
 * A phone number of a card, with what it is for.
 */
final readonly class PhoneLine
{
    public function __construct(
        public PhoneRole $role,
        public PhoneNumber $number,
    ) {
    }
}
