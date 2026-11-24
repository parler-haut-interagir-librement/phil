<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * What a personal link opened: the respondent, their poll and its slots, and whether their answers can still change.
 */
final readonly class RespondentAccess
{
    /**
     * @param list<Slot> $slots
     */
    public function __construct(
        public Respondent $respondent,
        public SchedulingPoll $poll,
        public array $slots,
        public bool $editable,
    ) {
    }
}
