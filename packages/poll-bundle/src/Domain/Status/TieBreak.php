<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Status;

/**
 * How a tie for the fewest votes was broken before an exclusion.
 */
enum TieBreak: string
{
    /** By the rounds before, read from the latest back: the option that held the fewest where they differed. */
    case EarlierRounds = 'earlier_rounds';

    /** The options never differed: the one declared last was excluded. */
    case DeclaredOrder = 'declared_order';
}
