<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\View;

use DateTimeImmutable;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\Transition\PlannedTransition;

/**
 * One line of a view at a moment in UTC: an item planned alone, a batch with all its items, or a transition planned
 * elsewhere.
 */
final readonly class Entry
{
    /**
     * @param list<PlannedItem> $items the item, or the items of the batch
     * @param string            $key   what entries of one moment and kind are ordered by: the identifier of the item
     *                                 or the batch, or the resource and the transition
     */
    private function __construct(
        public EntryKind $kind,
        public DateTimeImmutable $at,
        public array $items,
        public ?PublicationBatch $batch,
        public ?PlannedTransition $transition,
        public string $key,
    ) {
    }

    public static function item(PlannedItem $item, DateTimeImmutable $at): self
    {
        return new self(EntryKind::Item, $at, [$item], null, null, $item->id);
    }

    /**
     * @param list<PlannedItem> $items
     */
    public static function batch(PublicationBatch $batch, DateTimeImmutable $at, array $items): self
    {
        return new self(EntryKind::Batch, $at, $items, $batch, null, $batch->id);
    }

    public static function transition(PlannedTransition $transition): self
    {
        return new self(EntryKind::Transition, $transition->at, [], null, $transition, $transition->resource->toString().' '.$transition->transition);
    }

    /**
     * The order of entries: by moment, then batches, items and transitions, then by key.
     */
    public static function compare(self $first, self $second): int
    {
        return [$first->at, $first->kind->rank(), $first->key] <=> [$second->at, $second->kind->rank(), $second->key];
    }
}
