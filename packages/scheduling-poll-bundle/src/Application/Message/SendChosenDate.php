<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Message;

/**
 * The date chosen is to be sent to one respondent, with its iCalendar file. It carries identifiers only: the worker
 * reads the address and builds the mail; no address, name or token travels through the queue.
 */
final readonly class SendChosenDate
{
    public function __construct(
        public string $poll,
        public string $respondent,
    ) {
    }
}
