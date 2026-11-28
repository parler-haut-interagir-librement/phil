<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

/**
 * How similar an item is to another by its text, from 0 to 1, as a search engine finds it.
 */
final readonly class Similarity
{
    public function __construct(
        public string $id,
        public float $score,
    ) {
    }
}
