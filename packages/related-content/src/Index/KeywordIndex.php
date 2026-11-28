<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Index;

use Phil\RelatedContent\Contract\Index;
use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Item;

/**
 * Shared keywords of one index — the terms of a vocabulary, the authors, the series —: the weight once per keyword both
 * items hold, and `broaderFactor` × the weight once per broader term they share besides.
 *
 * ```php
 * new KeywordIndex('topics', 100, broaderFactor: 0.25);
 * new KeywordIndex('series', 300);
 * ```
 */
final readonly class KeywordIndex implements Index
{
    /**
     * @throws InvalidRule when the name is empty, the weight negative or not finite, or the factor outside 0 to 1
     */
    public function __construct(
        public string $name,
        public float $weight,
        public float $broaderFactor = 0.0,
    ) {
        if ('' === $name) {
            throw InvalidRule::emptyName();
        }

        if (!is_finite($weight) || $weight < 0.0) {
            throw InvalidRule::outOfRange($name.' weight', $weight, 0, \PHP_FLOAT_MAX);
        }

        if ($broaderFactor < 0.0 || $broaderFactor > 1.0) {
            throw InvalidRule::outOfRange($name.' broaderFactor', $broaderFactor, 0, 1);
        }
    }

    public function score(Item $item, Item $candidate): float
    {
        $shared = \count(array_intersect_key($item->keywordSet($this->name), $candidate->keywordSet($this->name)));

        if (0.0 === $this->broaderFactor) {
            return $this->weight * $shared;
        }

        $broader = \count(array_intersect_key($item->expandedSet($this->name), $candidate->expandedSet($this->name))) - $shared;

        return $this->weight * ($shared + $this->broaderFactor * $broader);
    }
}
