<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Contract;

use Phil\ImportWizard\Plan\NaturalKey;

/**
 * Which records already exist, by natural key: the planner asks it whether a row updates or creates, and whether a
 * reference to a record outside the file points to something. It is asked in chunks of at most 500 keys.
 */
interface KeyLookup
{
    /**
     * @param non-empty-list<NaturalKey> $keys
     *
     * @return list<NaturalKey> the keys among them of the records of that type that exist
     */
    public function existing(string $type, array $keys): array;
}
