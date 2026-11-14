<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\GuestRequest;

use Override;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * A person without an account is the address they verified, as `GuestRequests` normalizes it: its domain in lowercase,
 * the part before the @ as written.
 */
final readonly class EmailGuestSubjects implements GuestSubjects
{
    #[Override]
    public function subjectOf(string $email): DataSubject
    {
        return DataSubject::fromString($email);
    }
}
