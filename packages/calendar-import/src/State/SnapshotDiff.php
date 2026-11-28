<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

use Phil\CalendarImport\Field;

/**
 * The keys of the events a read added, changed and removed, from one snapshot to the next, with why each changed and
 * which of its fields an editor may replace changed.
 */
final readonly class SnapshotDiff
{
    /**
     * @param list<string>                    $added
     * @param list<string>                    $changed
     * @param list<string>                    $removed
     * @param array<int|string, ChangeReason> $reasons by key
     * @param array<int|string, list<Field>>  $fields  by key
     */
    public function __construct(
        public array $added = [],
        public array $changed = [],
        public array $removed = [],
        private array $reasons = [],
        private array $fields = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->added && [] === $this->changed && [] === $this->removed;
    }

    /**
     * Why an event changed, or null when it did not.
     */
    public function reason(string $key): ?ChangeReason
    {
        return $this->reasons[$key] ?? null;
    }

    /**
     * The fields a changed event changed.
     *
     * @return list<Field>
     */
    public function fieldsOf(string $key): array
    {
        return $this->fields[$key] ?? [];
    }
}
