<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * What a navigation request led to: an activity to launch, the end of the session, nothing to launch until the learner
 * chooses, or a refusal.
 */
enum Outcome: string
{
    case Delivered = 'delivered';
    case SessionEnded = 'sessionEnded';
    case Waiting = 'waiting';
    case Refused = 'refused';
}
