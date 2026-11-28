<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

use Phil\RelatedContent\Item;
use Phil\RelatedContent\Similarity;

/**
 * The items a search engine finds similar to this one by their text, when it knows how (« more like this »).
 */
interface SimilarItems
{
    /**
     * @return list<Similarity> at most `$limit` of them, the most similar first
     */
    public function similar(Item $item, int $limit): array;
}
