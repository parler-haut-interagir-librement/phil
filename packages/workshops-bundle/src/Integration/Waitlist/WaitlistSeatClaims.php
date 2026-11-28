<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Integration\Waitlist;

use Override;
use Phil\WaitlistBundle\Application\ListCount;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WorkshopsBundle\Application\Contract\SeatClaims;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;

/**
 * The free seats a session's waiting list claims, read from phil/waitlist-bundle: the places offered and not answered
 * yet, and, while somebody waits, the places those waiting ask for or the places the list holds for them, whichever is
 * more. Places the list holds while nobody waits claim nothing: a new registration takes them, and the next balance
 * gives them back to the session.
 */
final readonly class WaitlistSeatClaims implements SeatClaims
{
    public function __construct(
        private Waitlists $waitlists,
    ) {
    }

    #[Override]
    public function claimed(WorkshopSession $session): int
    {
        $count = $this->count($session);

        if (!$count instanceof ListCount) {
            return 0;
        }

        return $count->offered + ($count->waiting > 0 ? max($count->held, $count->waiting) : 0);
    }

    #[Override]
    public function offered(WorkshopSession $session): int
    {
        $count = $this->count($session);

        return $count instanceof ListCount ? $count->offered : 0;
    }

    private function count(WorkshopSession $session): ?ListCount
    {
        $waitlist = $this->waitlists->ofSubject(SessionWaitlists::SUBJECT, $session->id);

        return $waitlist instanceof Waitlist ? $this->waitlists->count($waitlist->id) : null;
    }
}
