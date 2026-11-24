<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain;

/**
 * Who closed a scheduling poll: its organizer, choosing a date, or its deadline, the best date being chosen.
 */
enum ClosedBy: string
{
    case Organizer = 'organizer';

    case Deadline = 'deadline';
}
