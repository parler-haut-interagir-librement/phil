<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

use Phil\RelatedContent\Item;

/**
 * One weighted clue that two items are related: shared keywords, close dates. Its score is already weighted, zero when
 * the clue says nothing, and the same both ways, so that the neighbours of a new item are the items that gain it.
 */
interface Index
{
    public function score(Item $item, Item $candidate): float;
}
