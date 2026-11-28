<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use Phil\RelatedContent\Contract\Taxonomy;

/**
 * Adds to an item the broader terms of its keywords, index by index, so that `KeywordIndex` finds two items filed under
 * sibling terms related at its `broaderFactor`. An `ItemSource` calls it on the items it builds.
 *
 * ```php
 * $item = new TaxonomyExpander($taxonomy, ['topics'])->expand($item);
 * ```
 */
final readonly class TaxonomyExpander
{
    /**
     * @param list<string> $indices the indices whose keywords are terms of a vocabulary
     */
    public function __construct(
        private Taxonomy $taxonomy,
        private array $indices,
    ) {
    }

    public function expand(Item $item): Item
    {
        foreach ($this->indices as $index) {
            $terms = $item->keywords[$index] ?? [];

            if ([] !== $terms) {
                $item = $item->withBroader($index, \array_slice($this->taxonomy->broader($index, $terms), 0, Item::MAX_KEYWORDS));
            }
        }

        return $item;
    }
}
