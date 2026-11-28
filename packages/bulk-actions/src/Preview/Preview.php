<?php

declare(strict_types=1);

namespace Phil\BulkActions\Preview;

use DateTimeImmutable;
use Phil\BulkActions\Change\ChangeSet;

/**
 * What the confirmation shows before anything changes: how many items, a sample with their old and new values, and
 * whether the action is destructive.
 */
final readonly class Preview
{
    /**
     * @param int              $count  the items selected now, before the voters
     * @param list<SampleItem> $sample the first items, at most the sample size
     */
    public function __construct(
        public string $target,
        public string $action,
        public int $count,
        public array $sample,
        public ChangeSet $changes,
        public bool $destructive,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * How many sampled items the voter refuses: those will be reported as denied.
     */
    public function deniedInSample(): int
    {
        return \count(array_filter($this->sample, static fn (SampleItem $item): bool => !$item->granted));
    }
}
