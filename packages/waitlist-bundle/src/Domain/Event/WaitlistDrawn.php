<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Event;

/**
 * A draw made on a waiting list: its entries are ordered by the published seed. It carries an identifier only: a
 * listener reads the rest.
 */
final readonly class WaitlistDrawn
{
    public function __construct(
        public string $waitlistId,
    ) {
    }
}
