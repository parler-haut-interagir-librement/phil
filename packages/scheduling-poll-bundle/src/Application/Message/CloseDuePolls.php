<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Message;

/**
 * The scheduling polls past their deadline are to be closed on their best date: the message of the bundle's schedule.
 */
final readonly class CloseDuePolls
{
}
