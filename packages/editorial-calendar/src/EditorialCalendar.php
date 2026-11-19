<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

use DateTimeImmutable;
use Phil\EditorialCalendar\Exception\BatchFailed;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Exception\InvalidTransition;
use Phil\EditorialCalendar\Exception\UnknownItem;

/**
 * The planned items and the publication batches of an editorial calendar. Immutable: each change gives a new calendar,
 * so that a batch applied in part is never seen — it is applied whole, or the calendar stays as it was.
 *
 * An item belongs to one batch at most, and is then published with it: at the batch's moment, or by hand when the
 * batch has none.
 */
final readonly class EditorialCalendar
{
    /**
     * The batch of each item that is in one, by item identifier.
     *
     * @var array<string, PublicationBatch>
     */
    private array $batchOf;

    /**
     * @param array<string, PlannedItem>      $items
     * @param array<string, PublicationBatch> $batches
     */
    private function __construct(
        private array $items,
        private array $batches,
    ) {
        $batchOf = [];

        foreach ($batches as $batch) {
            foreach ($batch->items as $item) {
                $batchOf[$item] = $batch;
            }
        }

        $this->batchOf = $batchOf;
    }

    /**
     * @param iterable<PlannedItem>      $items
     * @param iterable<PublicationBatch> $batches
     *
     * @throws InvalidItem when an identifier is repeated or an item is in two batches
     * @throws UnknownItem when a batch holds an item the calendar does not
     */
    public static function of(iterable $items = [], iterable $batches = []): self
    {
        $byId = [];

        foreach ($items as $item) {
            if (isset($byId[$item->id])) {
                throw InvalidItem::because(\sprintf('The item "%s" is in the calendar twice.', $item->id));
            }

            $byId[$item->id] = $item;
        }

        $byBatch = [];
        $owners = [];

        foreach ($batches as $batch) {
            if (isset($byBatch[$batch->id])) {
                throw InvalidItem::because(\sprintf('The batch "%s" is in the calendar twice.', $batch->id));
            }

            foreach ($batch->items as $item) {
                if (!isset($byId[$item])) {
                    throw UnknownItem::item($item);
                }

                if (isset($owners[$item])) {
                    throw InvalidItem::because(\sprintf('The item "%s" is already in the batch "%s".', $item, $owners[$item]));
                }

                $owners[$item] = $batch->id;
            }

            $byBatch[$batch->id] = $batch;
        }

        return new self($byId, $byBatch);
    }

    /**
     * @return list<PlannedItem>
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    /**
     * @throws UnknownItem
     */
    public function item(string $id): PlannedItem
    {
        return $this->items[$id] ?? throw UnknownItem::item($id);
    }

    /**
     * @return list<PublicationBatch>
     */
    public function batches(): array
    {
        return array_values($this->batches);
    }

    /**
     * @throws UnknownItem
     */
    public function batch(string $id): PublicationBatch
    {
        return $this->batches[$id] ?? throw UnknownItem::batch($id);
    }

    public function batchOf(string $item): ?PublicationBatch
    {
        return $this->batchOf[$item] ?? null;
    }

    /**
     * The calendar with this item added, or replacing the item of the same identifier.
     */
    public function with(PlannedItem $item): self
    {
        $items = $this->items;
        $items[$item->id] = $item;

        return new self($items, $this->batches);
    }

    /**
     * The calendar without this item; its batch loses it, and goes when it held nothing else.
     *
     * @throws UnknownItem
     */
    public function without(string $id): self
    {
        $this->item($id);
        $items = $this->items;
        unset($items[$id]);
        $batches = [];

        foreach ($this->batches as $batch) {
            $kept = array_values(array_filter($batch->items, static fn (string $item): bool => $item !== $id));

            if ([] !== $kept) {
                $batches[$batch->id] = $batch->holds($id) ? new PublicationBatch($batch->id, $batch->title, $kept, $batch->at) : $batch;
            }
        }

        return new self($items, $batches);
    }

    /**
     * The calendar with this batch added, or replacing the batch of the same identifier.
     *
     * @throws UnknownItem when the batch holds an item the calendar does not
     * @throws InvalidItem when one of its items is in another batch
     */
    public function withBatch(PublicationBatch $batch): self
    {
        foreach ($batch->items as $item) {
            $this->item($item);
            $other = $this->batchOf($item);

            if ($other instanceof PublicationBatch && $other->id !== $batch->id) {
                throw InvalidItem::because(\sprintf('The item "%s" is already in the batch "%s".', $item, $other->id));
            }
        }

        $batches = $this->batches;
        $batches[$batch->id] = $batch;

        return new self($this->items, $batches);
    }

    /**
     * @throws UnknownItem
     */
    public function withoutBatch(string $id): self
    {
        $this->batch($id);
        $batches = $this->batches;
        unset($batches[$id]);

        return new self($this->items, $batches);
    }

    /**
     * The moment an item is planned for: its batch's, when it is in one, else its own. `null` when it has none.
     */
    public function moment(PlannedItem $item): ?DateTimeImmutable
    {
        $batch = $this->batchOf($item->id);

        return $batch instanceof PublicationBatch ? $batch->at : $item->publishAt;
    }

    /**
     * The calendar with an item in another state. An item scheduled without a moment given takes its planned one; an
     * item of a batch is published only with its batch.
     *
     * @throws UnknownItem
     * @throws InvalidTransition
     */
    public function transition(string $id, ItemState $state, ?DateTimeImmutable $at = null): self
    {
        $item = $this->item($id);
        $batch = $this->batchOf($id);

        if (ItemState::Published === $state && $batch instanceof PublicationBatch) {
            throw InvalidTransition::inBatch($id, $batch->id);
        }

        return $this->with($item->transitionTo($state, $at ?? (ItemState::Scheduled === $state ? $this->moment($item) : null)));
    }

    /**
     * The calendar with every item of the batch published at `$now`, in one go: when one of them cannot be published,
     * none is, and the calendar stays as it was.
     *
     * @throws UnknownItem
     * @throws BatchFailed naming the item that refused
     */
    public function applyBatch(string $id, DateTimeImmutable $now): self
    {
        $batch = $this->batch($id);
        $items = $this->items;

        foreach ($batch->items as $item) {
            try {
                $items[$item] = $this->items[$item]->transitionTo(ItemState::Published, $now);
            } catch (InvalidTransition $refused) {
                throw new BatchFailed($id, $item, $refused);
            }
        }

        return new self($items, $this->batches);
    }

    /**
     * The batches whose moment has come at `$now` and that still hold an item to publish, in the order of their moments.
     *
     * @return list<PublicationBatch>
     */
    public function dueBatches(DateTimeImmutable $now): array
    {
        $due = array_filter($this->batches, fn (PublicationBatch $batch): bool => $batch->at instanceof DateTimeImmutable && $batch->at <= $now && $this->pending($batch));
        usort($due, static fn (PublicationBatch $first, PublicationBatch $second): int => [$first->at, $first->id] <=> [$second->at, $second->id]);

        return $due;
    }

    /**
     * The batches applied by hand that still hold an item to publish.
     *
     * @return list<PublicationBatch>
     */
    public function manualBatches(): array
    {
        return array_values(array_filter($this->batches, fn (PublicationBatch $batch): bool => $batch->isManual() && $this->pending($batch)));
    }

    /**
     * The items still to publish that have no moment and no batch: ideas and drafts not yet placed in time, among those
     * the filter accepts.
     *
     * @return list<PlannedItem>
     */
    public function unscheduled(?Filter $filter = null): array
    {
        $filter ??= Filter::all();

        return array_values(array_filter($this->items, fn (PlannedItem $item): bool => $filter->accepts($item) && $item->state->isPending() && !$item->publishAt instanceof DateTimeImmutable && !$this->batchOf($item->id) instanceof PublicationBatch));
    }

    private function pending(PublicationBatch $batch): bool
    {
        return array_any($batch->items, fn (string $item) => $this->items[$item]->state->isPending());
    }
}
