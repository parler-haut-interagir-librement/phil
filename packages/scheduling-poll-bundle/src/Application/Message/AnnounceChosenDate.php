<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Message;

/**
 * The date chosen for a scheduling poll is to be announced: the handler queues one `SendChosenDate` per respondent who
 * answered. It carries the poll's identifier only.
 */
final readonly class AnnounceChosenDate
{
    public function __construct(
        public string $poll,
    ) {
    }
}
