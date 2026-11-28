<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

use Phil\CalendarImport\ICalendar\RejectedEvent;
use Phil\CalendarImport\State\SourceState;

/**
 * What one refresh of a source did: whether it read a calendar, found nothing changed, or failed; the changes it found,
 * the drafts of every event it read, the events it refused, and the state it saved.
 */
final readonly class ImportOutcome
{
    /**
     * @param list<Change>                     $changes  added, then changed, then removed, each in the order of the source
     * @param array<int|string, ImportedDraft> $drafts   every event read, by key — a key of digits only is an integer in PHP —, overrides applied
     * @param list<RejectedEvent>              $rejected
     */
    private function __construct(
        public bool $read,
        public SourceState $state,
        public array $changes = [],
        public array $drafts = [],
        public array $rejected = [],
    ) {
    }

    /**
     * @param list<Change>                     $changes
     * @param array<int|string, ImportedDraft> $drafts
     * @param list<RejectedEvent>              $rejected
     */
    public static function imported(SourceState $state, array $changes, array $drafts, array $rejected): self
    {
        return new self(true, $state, $changes, $drafts, $rejected);
    }

    public static function unchanged(SourceState $state): self
    {
        return new self(false, $state);
    }

    public function isFailure(): bool
    {
        return $this->state->isFailing();
    }

    /**
     * @return list<Change>
     */
    public function changesOf(ChangeKind $kind): array
    {
        return array_values(array_filter($this->changes, static fn (Change $change): bool => $kind === $change->kind));
    }
}
