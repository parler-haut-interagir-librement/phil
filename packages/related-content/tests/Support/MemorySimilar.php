<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Support;

use Phil\RelatedContent\Contract\SimilarItems;
use Phil\RelatedContent\Item;
use Phil\RelatedContent\Similarity;

final class MemorySimilar implements SimilarItems
{
    public ?int $askedLimit = null;

    /**
     * @var list<Similarity>
     */
    private readonly array $similar;

    public function __construct(Similarity ...$similar)
    {
        $this->similar = array_values($similar);
    }

    public function similar(Item $item, int $limit): array
    {
        $this->askedLimit = $limit;

        return $this->similar;
    }
}
