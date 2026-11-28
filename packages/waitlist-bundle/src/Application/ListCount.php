<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application;

/**
 * A waiting list's places now: those its waiting entries ask for, those its offers hold, and those freed and not
 * offered yet.
 */
final readonly class ListCount
{
    public function __construct(
        public int $waiting,
        public int $offered,
        public int $held,
    ) {
    }
}
