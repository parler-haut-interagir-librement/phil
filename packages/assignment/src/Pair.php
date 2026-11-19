<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Internal\Identifier;

/**
 * An applicant and a slot: a pair the organiser forbids — the applicant is never placed there —, or forces — the
 * applicant is placed there before anyone else, ranked or not.
 */
final readonly class Pair
{
    public string $applicant;

    public string $slot;

    public function __construct(string $applicant, string $slot)
    {
        $this->applicant = Identifier::check($applicant, 'applicant');
        $this->slot = Identifier::check($slot, 'slot');
    }
}
