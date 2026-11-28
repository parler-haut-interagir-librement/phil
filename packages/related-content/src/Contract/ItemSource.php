<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

use Phil\RelatedContent\Item;

/**
 * The published contents or products, as items: the application's adapter over its content, catalogue and taxonomy.
 */
interface ItemSource
{
    /**
     * The item as readers may see it now, or null when it is not published, retired or unknown.
     */
    public function item(string $id): ?Item;

    /**
     * The published items worth comparing with this one, usually those sharing a term, an author or a series with it,
     * the most recent first. The engine reads at most `RelevanceRules::$maxCandidates` of them and leaves the item
     * itself out.
     *
     * @return iterable<Item>
     */
    public function candidates(Item $item): iterable;
}
