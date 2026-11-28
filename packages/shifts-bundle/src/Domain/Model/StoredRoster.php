<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Model;

use Phil\Shifts\Roster\Roster;

/**
 * A roster as its storage keeps it, with the revision a write compares, so that two requests changing the same roster
 * at once never overwrite each other.
 */
final class StoredRoster
{
    public function __construct(
        public readonly Roster $roster,
        public readonly int $revision,
    ) {
    }
}
