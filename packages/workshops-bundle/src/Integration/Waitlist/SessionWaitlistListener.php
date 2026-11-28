<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Integration\Waitlist;

use Phil\WaitlistBundle\Domain\Event\EntryLeft;
use Phil\WorkshopsBundle\Domain\Event\RegistrationCancelled;
use Phil\WorkshopsBundle\Domain\Event\SessionCancelled;
use Phil\WorkshopsBundle\Domain\Event\SessionResized;

/**
 * Keeps each session's waiting list in step with its seats: a registration cancelled, a capacity changed or an entry
 * that left balances the list, and a session cancelled closes it.
 */
final readonly class SessionWaitlistListener
{
    public function __construct(
        private SessionWaitlists $waitlists,
    ) {
    }

    public function __invoke(RegistrationCancelled|SessionResized|SessionCancelled|EntryLeft $event): void
    {
        if ($event instanceof EntryLeft) {
            $this->waitlists->balanceList($event->waitlistId);

            return;
        }

        if ($event instanceof SessionCancelled) {
            $this->waitlists->close($event->sessionId);

            return;
        }

        $this->waitlists->balance($event->sessionId);
    }
}
