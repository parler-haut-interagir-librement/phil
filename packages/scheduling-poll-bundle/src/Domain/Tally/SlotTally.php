<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Tally;

use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * The answers one proposed date gathered, and its place: 1 for the best, shared by the dates tied with it — the same
 * yes answers and the same if-need-be answers —, the next one coming after as many places as dates before it.
 */
final readonly class SlotTally
{
    public function __construct(
        public Slot $slot,
        public int $yes,
        public int $ifNeedBe,
        public int $no,
        public int $place,
    ) {
    }

    /**
     * Whether anyone can come: a yes or an if-need-be answer.
     */
    public function hasAvailability(): bool
    {
        return $this->yes + $this->ifNeedBe > 0;
    }
}
