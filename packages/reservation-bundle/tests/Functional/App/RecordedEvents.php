<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Functional\App;

use Phil\ReservationBundle\Domain\Event\ReservationConfirmed;
use Phil\ReservationBundle\Domain\Event\ReservationHeld;

/**
 * A listener of the application: it keeps the reservations held and confirmed for the test to read.
 */
final class RecordedEvents
{
    /** @var list<ReservationHeld|ReservationConfirmed> */
    public array $events = [];

    public function held(ReservationHeld $event): void
    {
        $this->events[] = $event;
    }

    public function confirmed(ReservationConfirmed $event): void
    {
        $this->events[] = $event;
    }
}
