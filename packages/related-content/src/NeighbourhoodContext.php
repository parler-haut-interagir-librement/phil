<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use Phil\RelatedContent\Contract\ItemSource;

/**
 * The working state of one computation: what is already proposed, the items read, and the scores by reason.
 *
 * @internal
 */
final class NeighbourhoodContext
{
    /**
     * @var list<Recommendation>
     */
    private array $manual = [];

    /**
     * @var array<string, true>
     */
    private array $taken;

    /**
     * @var array<string, Item|false>
     */
    private array $items = [];

    /**
     * @var array<string, array{string, non-empty-array<string, float>}> by candidate: its identifier, and its scores by
     *                                                                   reason
     */
    private array $scores = [];

    public function __construct(
        private readonly ItemSource $source,
        private readonly Item $item,
    ) {
        $this->taken = [$item->id => true];
    }

    /**
     * Proposes a neighbour a person chose, once, when it is published in the item's language.
     */
    public function propose(string $id, Reason $reason): void
    {
        if (isset($this->taken[$id]) || !$this->find($id) instanceof Item) {
            return;
        }

        $this->taken[$id] = true;
        $this->manual[] = new Recommendation($id, null, [$reason]);
    }

    /**
     * Adds to the score of a candidate already read.
     */
    public function add(Item $candidate, Reason $reason, float $score): void
    {
        if (isset($this->taken[$candidate->id]) || $score <= 0.0 || !$this->item->sharesLocaleWith($candidate)) {
            return;
        }

        $this->items[$candidate->id] = $candidate;
        $parts = $this->scores[$candidate->id][1] ?? [];
        $parts[$reason->value] = ($parts[$reason->value] ?? 0.0) + $score;
        $this->scores[$candidate->id] = [$candidate->id, $parts];
    }

    /**
     * Adds to the score of a candidate known by its identifier only, read from the source when it was not yet.
     */
    public function addById(string $id, Reason $reason, float $score): void
    {
        if ($score <= 0.0 || isset($this->taken[$id])) {
            return;
        }

        $candidate = $this->find($id);

        if ($candidate instanceof Item) {
            $this->add($candidate, $reason, $score);
        }
    }

    /**
     * Drops the scored candidates under the threshold.
     */
    public function keepAbove(float $threshold): void
    {
        $this->scores = array_filter($this->scores, static fn (array $entry): bool => array_sum($entry[1]) >= $threshold);
    }

    /**
     * The manual neighbours in their order, then the scored ones, highest first and by identifier on a tie, each with its
     * reasons strongest first.
     *
     * @return list<Recommendation>
     */
    public function recommendations(): array
    {
        $scored = [];

        foreach ($this->scores as [$id, $parts]) {
            arsort($parts);
            $scored[] = new Recommendation($id, round(array_sum($parts), 6), array_map(Reason::from(...), array_keys($parts)));
        }

        usort($scored, static fn (Recommendation $a, Recommendation $b): int => [$b->score, $a->id] <=> [$a->score, $b->id]);

        return [...$this->manual, ...$scored];
    }

    private function find(string $id): ?Item
    {
        if (!\array_key_exists($id, $this->items)) {
            $found = $this->source->item($id);
            $this->items[$id] = $found instanceof Item && $found->id === $id && $this->item->sharesLocaleWith($found) ? $found : false;
        }

        $found = $this->items[$id];

        return false === $found ? null : $found;
    }
}
