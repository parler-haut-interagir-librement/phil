<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Journal;

/**
 * What the application did with a suggestion: nothing yet, kept it as proposed, kept it after the person changed it,
 * or set it aside.
 */
enum Decision: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';
}
