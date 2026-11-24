<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Status;

/**
 * The orders a list of reviews is read in. Every order lists every published review: none filters any out.
 */
enum ReviewOrder: string
{
    case Helpful = 'helpful';
    case Newest = 'newest';
    case Highest = 'highest';
    case Lowest = 'lowest';
}
