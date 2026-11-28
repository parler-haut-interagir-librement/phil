<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

use DateTimeImmutable;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\NothingToRetry;
use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionKind;

/**
 * A confirmed bulk action and what became of each of its items. Its chunks are executed one message at a time; the run
 * store keeps it between them, through toArray() and fromArray().
 */
final class BulkRun
{
    /**
     * @param array<array-key, array{0: ItemOutcome, 1: string|null}> $items the outcome and reason of each item, in order; a numeric identifier is an integer key
     */
    private function __construct(
        public readonly RunId $id,
        public readonly string $target,
        public readonly string $action,
        public private(set) string $actor,
        public readonly ChangeSet $changes,
        public readonly Selection $selection,
        public readonly DateTimeImmutable $confirmedAt,
        public private(set) RunStatus $status,
        private array $items,
        public private(set) ?string $cursor,
        public private(set) bool $exhausted,
        public private(set) bool $truncated,
        public private(set) int $attempt,
        public private(set) ?DateTimeImmutable $endedAt,
        public private(set) ?string $stopReason,
    ) {
    }

    /**
     * A run of a confirmed selection: the identifiers of an explicit selection are all pending, a filter's are read
     * chunk by chunk when it executes.
     */
    public static function confirm(RunId $id, string $target, string $action, string $actor, ChangeSet $changes, Selection $selection, DateTimeImmutable $now): self
    {
        $items = [];

        foreach ($selection->identifiers as $item) {
            $items[$item] = [ItemOutcome::Pending, null];
        }

        return new self(
            $id,
            Check::name('target', $target),
            Check::name('action', $action),
            Check::identifier('actor', $actor),
            $changes,
            $selection,
            $now,
            RunStatus::Pending,
            $items,
            null,
            SelectionKind::Identifiers === $selection->kind,
            false,
            1,
            null,
            null,
        );
    }

    /**
     * The next items to handle, in the order of the selection.
     *
     * @return list<string>
     */
    public function pending(int $limit): array
    {
        $pending = [];

        foreach ($this->items as $item => [$outcome]) {
            if (\count($pending) >= $limit) {
                break;
            }

            if (ItemOutcome::Pending === $outcome) {
                $pending[] = (string) $item;
            }
        }

        return $pending;
    }

    /**
     * The filter to read the next items from, or null when the run has nothing more to read.
     */
    public function nextFilter(): ?FilterCriteria
    {
        return $this->exhausted ? null : $this->selection->filter;
    }

    /**
     * Adds the items a filter matched after the cursor, up to the run's limit; the ones beyond it are left out and the
     * run says it was truncated. A read that adds nothing new ends the filter, so that a repository giving back known
     * items cannot keep a run going.
     *
     * @param list<string> $items     the identifiers read, in the repository's order
     * @param bool         $exhausted whether the filter has no item after these
     */
    public function discover(array $items, bool $exhausted, int $limit): void
    {
        $added = false;

        foreach ($items as $item) {
            if (isset($this->items[$item])) {
                continue;
            }

            if (\count($this->items) >= $limit) {
                $this->truncated = true;
                $exhausted = true;

                break;
            }

            $this->items[$item] = [ItemOutcome::Pending, null];
            $added = true;
        }

        if ([] !== $items) {
            $this->cursor = array_last($items);
        }

        $this->exhausted = $exhausted || !$added;
    }

    public function record(string $item, ItemOutcome $outcome, ?string $reason = null): void
    {
        $this->items[$item] = [$outcome, $reason];
        $this->status = RunStatus::Running;
    }

    /**
     * Ends the run once nothing is pending and the filter, if any, has nothing more.
     *
     * @return bool whether the run is over now
     */
    public function finishIfDone(DateTimeImmutable $now): bool
    {
        if ($this->status->isOver()) {
            return true;
        }

        if (!$this->exhausted || [] !== $this->pending(1)) {
            return false;
        }

        $this->status = RunStatus::Finished;
        $this->endedAt = $now;

        return true;
    }

    /**
     * Ends the run early: every pending item takes the outcome given, with the reason.
     */
    public function stop(ItemOutcome $outcome, string $reason, DateTimeImmutable $now): void
    {
        foreach ($this->items as $item => [$current]) {
            if (ItemOutcome::Pending === $current) {
                $this->items[$item] = [$outcome, $reason];
            }
        }

        $this->status = RunStatus::Stopped;
        $this->exhausted = true;
        $this->endedAt = $now;
        $this->stopReason = $reason;
    }

    /**
     * Makes the failed items pending again, and the conflicts when asked: the person retrying runs them, and a conflict
     * retried applies to the item as it is now.
     *
     * @return int how many items are pending again
     */
    public function retry(string $actor, bool $conflicts): int
    {
        if (!$this->status->isOver()) {
            throw NothingToRetry::stillRunning($this->id->value);
        }

        $count = 0;

        foreach ($this->items as $item => [$outcome]) {
            if ($outcome->isRetryable($conflicts)) {
                $this->items[$item] = [ItemOutcome::Pending, null];
                ++$count;
            }
        }

        if (0 === $count) {
            throw NothingToRetry::noFailure($this->id->value);
        }

        $this->actor = Check::identifier('actor', $actor);
        $this->status = RunStatus::Pending;
        $this->endedAt = null;
        $this->stopReason = null;
        ++$this->attempt;

        return $count;
    }

    /**
     * The version the person saw for an item: only a first attempt checks it, a retry applies to the item as it is.
     */
    public function expectedVersion(string $item): ?string
    {
        return 1 === $this->attempt ? $this->selection->versionOf($item) : null;
    }

    public function size(): int
    {
        return \count($this->items);
    }

    public function report(): Report
    {
        $results = [];

        foreach ($this->items as $item => [$outcome, $reason]) {
            $results[] = new ItemResult((string) $item, $outcome, $reason);
        }

        return new Report($this->id, $this->status, $results, $this->truncated);
    }

    /**
     * @return array<string, mixed> scalars and arrays only, ready for JSON
     */
    public function toArray(): array
    {
        $items = [];

        foreach ($this->items as $item => [$outcome, $reason]) {
            $items[] = [(string) $item, $outcome->value, $reason];
        }

        return [
            'id' => $this->id->value,
            'target' => $this->target,
            'action' => $this->action,
            'actor' => $this->actor,
            'changes' => $this->changes->values,
            'selection' => $this->selection->toArray(),
            'confirmed_at' => $this->confirmedAt->format(\DATE_ATOM),
            'status' => $this->status->value,
            'items' => $items,
            'cursor' => $this->cursor,
            'exhausted' => $this->exhausted,
            'truncated' => $this->truncated,
            'attempt' => $this->attempt,
            'ended_at' => $this->endedAt?->format(\DATE_ATOM),
            'stop_reason' => $this->stopReason,
        ];
    }

    /**
     * @param array<array-key, mixed> $data what toArray() gave, read back from storage
     */
    public static function fromArray(array $data): self
    {
        $read = new StoredRun($data);

        return new self(
            RunId::fromString($read->string('id')),
            Check::name('target', $read->string('target')),
            Check::name('action', $read->string('action')),
            Check::identifier('actor', $read->string('actor')),
            ChangeSet::of($read->map('changes')),
            Selection::fromArray($read->map('selection')),
            $read->date('confirmed_at') ?? throw InvalidInput::because('stored run', 'must have its confirmed_at'),
            RunStatus::tryFrom($read->string('status')) ?? throw InvalidInput::because('stored run', 'must have a known status'),
            $read->items(),
            $read->optionalString('cursor'),
            $read->bool('exhausted'),
            $read->bool('truncated'),
            Check::between('attempt', $read->int('attempt'), 1, \PHP_INT_MAX),
            $read->date('ended_at'),
            $read->optionalString('stop_reason'),
        );
    }
}
