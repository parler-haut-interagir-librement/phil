<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

/**
 * The broader terms of a vocabulary: the parents of a term, and their parents, so that two items filed under sibling
 * terms are still found related, at a lower weight.
 */
interface Taxonomy
{
    /**
     * @param list<string> $terms the terms of one index of an item
     *
     * @return list<string> their broader terms, without the terms themselves
     */
    public function broader(string $index, array $terms): array;
}
