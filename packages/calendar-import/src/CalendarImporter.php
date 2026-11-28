<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

use Phil\CalendarImport\Exception\SourceUnavailable;
use Phil\CalendarImport\ICalendar\ReadResult;
use Phil\CalendarImport\Override\OverrideStore;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\EventSource;
use Phil\CalendarImport\State\RefreshPolicy;
use Phil\CalendarImport\State\Snapshot;
use Phil\CalendarImport\State\SourceFailure;
use Phil\CalendarImport\State\SourceState;
use Phil\CalendarImport\State\SourceStateStore;
use Psr\Clock\ClockInterface;

/**
 * Reads a source again and says what changed since its last read.
 *
 * A read gives a snapshot — the version of each event, by key — compared with the last one: events added, changed and
 * removed. The editors' overrides are applied to every draft, and a field an editor replaced that the source changed
 * too is reported. A source that fails keeps its last snapshot, and its failure is dated; an event refused this time
 * that was read before keeps its last version, rather than being removed.
 *
 * The importer keeps nothing itself: states and overrides are behind their ports.
 */
final readonly class CalendarImporter
{
    public function __construct(
        private EventSource $source,
        private SourceStateStore $states,
        private OverrideStore $overrides,
        private ClockInterface $clock,
        private RefreshPolicy $policy = new RefreshPolicy(),
    ) {
    }

    public function refresh(CalendarSource $source): ImportOutcome
    {
        $now = $this->clock->now();
        $state = $this->states->load($source->id) ?? new SourceState($source->id);

        try {
            $fetched = $this->source->fetch($source, $state->validators);
        } catch (SourceUnavailable $sourceUnavailable) {
            $state = $state->failed(new SourceFailure($sourceUnavailable->kind, $sourceUnavailable->getMessage(), $now, $sourceUnavailable->status));
            $this->states->save($state);

            return ImportOutcome::unchanged($state);
        }

        $result = $fetched->result;

        if (!$result instanceof ReadResult) {
            $state = $state->notModified($fetched->validators, $now);
            $this->states->save($state);

            return ImportOutcome::unchanged($state);
        }

        $versions = [];

        foreach ($result->events as $event) {
            $versions[$event->key()] = $event->version();
        }

        // An event refused this time keeps the version it was last read with: a broken line is not a removal.
        foreach ($result->rejected as $rejected) {
            $key = $rejected->key;
            $kept = null === $key ? null : $state->snapshot->versions[$key] ?? null;

            if ($kept instanceof EventVersion) {
                $versions[$key] ??= $kept;
            }
        }

        $snapshot = new Snapshot($now, $versions);
        $diff = $snapshot->compare($state->snapshot);
        $overrides = [];

        foreach ($this->overrides->forSource($source->id) as $override) {
            $overrides[$override->key] = $override;
        }

        $drafts = [];

        foreach ($result->events as $event) {
            $drafts[$event->key()] = ImportedDraft::of($source->id, $event, $overrides[$event->key()] ?? null);
        }

        $changes = [];

        foreach ($diff->added as $key) {
            $changes[] = new Change(ChangeKind::Added, $key, $drafts[$key]);
        }

        foreach ($diff->changed as $key) {
            $replaced = ($overrides[$key] ?? null)?->fields() ?? [];
            $conflicts = array_values(array_filter($diff->fieldsOf($key), static fn (Field $field): bool => \in_array($field, $replaced, true)));
            $changes[] = new Change(ChangeKind::Changed, $key, $drafts[$key], $diff->reason($key), $conflicts);
        }

        foreach ($diff->removed as $key) {
            $changes[] = new Change(ChangeKind::Removed, $key);
        }

        $state = $state->succeeded($snapshot, $fetched->validators, $now);
        $this->states->save($state);

        return ImportOutcome::imported($state, $changes, $drafts, $result->rejected);
    }

    /**
     * Reads again the sources that are due by the refresh policy, and leaves the others.
     *
     * @param iterable<CalendarSource> $sources
     *
     * @return list<ImportOutcome> one per source read
     */
    public function refreshDue(iterable $sources): array
    {
        $outcomes = [];
        $now = $this->clock->now();

        foreach ($sources as $source) {
            if ($this->policy->isDue($this->states->load($source->id), $now)) {
                $outcomes[] = $this->refresh($source);
            }
        }

        return $outcomes;
    }
}
