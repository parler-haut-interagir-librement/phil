<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Fixture;

use Override;
use Phil\WorkshopsBundle\Application\Contract\SeatClaims;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;

/**
 * A waiting list that claims a set number of seats and has offered another, and records each question with whether it
 * came under the session's lock.
 */
final class FixedClaims implements SeatClaims
{
    /** @var list<string> each question, the session's identifier and whether it was locked */
    public array $asked = [];

    public function __construct(
        private readonly InMemoryRegistrations $registrations,
        public int $claimed = 0,
        public int $offered = 0,
    ) {
    }

    #[Override]
    public function claimed(WorkshopSession $session): int
    {
        $this->asked[] = 'claimed '.$session->id.$this->lock();

        return $this->claimed;
    }

    #[Override]
    public function offered(WorkshopSession $session): int
    {
        $this->asked[] = 'offered '.$session->id.$this->lock();

        return $this->offered;
    }

    private function lock(): string
    {
        return $this->registrations->inside ? ' locked' : ' unlocked';
    }
}
