<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * Who a verified address is, for the modules' exporters and erasers: a person without an account is known by what they
 * left — a subscription, a message, a ticket —, and the application says which identifier its modules index that on.
 * The bundle's default is the address itself.
 */
interface GuestSubjects
{
    public function subjectOf(string $email): DataSubject;
}
