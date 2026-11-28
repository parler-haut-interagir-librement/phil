<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use Phil\RelatedContent\Contract\ItemSource;
use Phil\RelatedContent\Contract\PairCounts;
use Phil\RelatedContent\Contract\RelatedStore;

/**
 * The neighbours of the published items, computed when an item is published, changed or retired, and kept: a page reads
 * them with `related()` and never computes them.
 *
 * When an item changes, the items that showed it are computed again, and so are its new neighbours, which may now show
 * it; when it is retired, it leaves every list and its pair counts are forgotten. At most `RelevanceRules::$maxRecomputes`
 * items are computed at once; the others are returned, for the application to queue.
 *
 * ```php
 * $related = new RelatedContent($source, new Neighbourhood($source, RelevanceRules::forContent()), $store);
 * $related->published('article:42');
 * $related->related('article:42')?->top(4);
 * ```
 */
final readonly class RelatedContent
{
    public function __construct(
        private ItemSource $source,
        private Neighbourhood $neighbourhood,
        private RelatedStore $store,
        private ?PairCounts $pairs = null,
    ) {
    }

    /**
     * The neighbours kept for an item, or null when none were computed.
     */
    public function related(string $id): ?RelatedSet
    {
        return $this->store->find($id);
    }

    /**
     * Computes and keeps the neighbours of one item; an item no longer published loses them.
     */
    public function compute(string $id): ?RelatedSet
    {
        $item = $this->source->item($id);

        if (!$item instanceof Item) {
            $this->store->remove($id);

            return null;
        }

        $set = $this->neighbourhood->compute($item);
        $this->store->save($set);

        return $set;
    }

    /**
     * An item was published, or published again with new content.
     */
    public function published(string $id): Recomputation
    {
        $set = $this->compute($id);

        if (!$set instanceof RelatedSet) {
            return $this->retired($id);
        }

        return $this->recompute([...$this->store->pointingAt($id, RelevanceRules::MAX_RECOMPUTES), ...$set->ids()], $id);
    }

    /**
     * An item was taken offline or deleted.
     */
    public function retired(string $id): Recomputation
    {
        $this->store->remove($id);
        $this->pairs?->forget($id);

        return $this->recompute($this->store->pointingAt($id, RelevanceRules::MAX_RECOMPUTES), $id);
    }

    /**
     * @param list<string> $ids
     */
    private function recompute(array $ids, string $changed): Recomputation
    {
        $ids = array_diff(array_keys(array_fill_keys($ids, true)), [$changed]);
        $ids = array_map(strval(...), $ids);

        $now = \array_slice($ids, 0, $this->neighbourhood->rules->maxRecomputes);

        foreach ($now as $id) {
            $this->compute($id);
        }

        return new Recomputation($now, \array_slice($ids, $this->neighbourhood->rules->maxRecomputes));
    }
}
