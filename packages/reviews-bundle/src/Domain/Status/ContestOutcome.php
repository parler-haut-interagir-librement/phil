<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Status;

/**
 * How the contest of a withdrawal ended: `Upheld`, the review is published again; `Rejected`, the withdrawal stands.
 */
enum ContestOutcome: string
{
    case Upheld = 'upheld';
    case Rejected = 'rejected';
}
