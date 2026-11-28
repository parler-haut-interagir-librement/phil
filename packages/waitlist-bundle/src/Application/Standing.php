<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application;

use DateTimeImmutable;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;

/**
 * Where an entry stands now: its status, its position while it waits — 1 for the next in line —, and the deadline of
 * the offer it holds.
 */
final readonly class Standing
{
    public function __construct(
        public string $entryId,
        public EntryStatus $status,
        public ?int $position,
        public ?DateTimeImmutable $offerExpiresAt,
    ) {
    }
}
