<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\Enum;

/**
 * What a booth of a floor plan is over the period asked: free to book, taken, closed — the resource takes no booking
 * then, or refuses it for another reason — or unknown, when no period is asked, no resource is named or none answers.
 */
enum BoothState: string
{
    case Free = 'free';
    case Taken = 'taken';
    case Closed = 'closed';
    case Unknown = 'unknown';
}
