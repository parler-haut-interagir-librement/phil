<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Application\Contract;

use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;

/**
 * The free seats of a session that something other than a new registration has a claim on: the places a waiting list
 * offered and that are not answered yet, and those its waiting participants ask for. `Workshops` asks it under the
 * session's lock, inside its transaction, and gives a new registration only the seats nobody claims.
 *
 * The integration of phil/waitlist-bundle implements it; without an implementation, nobody claims a free seat.
 */
interface SeatClaims
{
    /**
     * The free seats a new registration does not take: those offered, and those the waiting participants ask for.
     */
    public function claimed(WorkshopSession $session): int;

    /**
     * The seats offered and not answered yet: promised, so that the session's capacity never goes below the seats
     * taken and offered.
     */
    public function offered(WorkshopSession $session): int;
}
