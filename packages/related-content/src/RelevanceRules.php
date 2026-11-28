<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use Phil\RelatedContent\Contract\Index;
use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Index\DateProximity;
use Phil\RelatedContent\Index\KeywordIndex;

/**
 * How neighbours are scored and how many are kept. Below the threshold nothing is proposed: an item without a real
 * neighbour shows no block rather than a weak one.
 *
 * The score of a candidate adds the weighted scores of the indices, `similarWeight` × its similarity, and, for pair
 * counts of at least `minimumPairCount`, the signal's weight × its count divided by the highest count of the item.
 *
 * ```php
 * new RelevanceRules([new KeywordIndex('topics', 100), new DateProximity(20)], threshold: 80, limit: 6);
 * ```
 */
final readonly class RelevanceRules
{
    public const int MAX_INDICES = 32;

    public const int MAX_LIMIT = 100;

    public const int MAX_CANDIDATES = 10_000;

    public const int MAX_RECOMPUTES = 10_000;

    public const int MAX_PAIR_COUNT = 1_000_000;

    /**
     * @param list<Index> $indices
     * @param bool        $includeNewer     whether a candidate dated after the item may be proposed
     * @param int         $maxRecomputes    the items computed again at once when one changes; the rest is returned to
     *                                      be queued
     * @param int         $minimumPairCount the count under which a pair is ignored, so that a single person's order
     *                                      or views never show
     *
     * @throws InvalidRule when a value lies outside its bounds
     */
    public function __construct(
        public array $indices = [],
        public float $threshold = 80.0,
        public int $limit = 10,
        public int $maxCandidates = 500,
        public bool $includeNewer = true,
        public float $similarWeight = 100.0,
        public float $boughtTogetherWeight = 100.0,
        public float $viewedNextWeight = 50.0,
        public int $minimumPairCount = 3,
        public int $maxRecomputes = 100,
    ) {
        $this->range('indices', \count($indices), 0, self::MAX_INDICES);
        $this->weight('threshold', $threshold);
        $this->range('limit', $limit, 1, self::MAX_LIMIT);
        $this->range('maxCandidates', $maxCandidates, 1, self::MAX_CANDIDATES);
        $this->weight('similarWeight', $similarWeight);
        $this->weight('boughtTogetherWeight', $boughtTogetherWeight);
        $this->weight('viewedNextWeight', $viewedNextWeight);
        $this->range('minimumPairCount', $minimumPairCount, 1, self::MAX_PAIR_COUNT);
        $this->range('maxRecomputes', $maxRecomputes, 1, self::MAX_RECOMPUTES);
    }

    /**
     * Rules for articles and pages: keywords `topics` (with their broader terms at a quarter), `authors` and `series`,
     * and the date within a year.
     */
    public static function forContent(): self
    {
        return new self([
            new KeywordIndex('topics', 100.0, 0.25),
            new KeywordIndex('authors', 40.0),
            new KeywordIndex('series', 200.0),
            new DateProximity(20.0, 365),
        ]);
    }

    private function range(string $rule, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw InvalidRule::outOfRange($rule, $value, $min, $max);
        }
    }

    private function weight(string $rule, float $value): void
    {
        if (!is_finite($value) || $value < 0.0) {
            throw InvalidRule::outOfRange($rule, $value, 0, \PHP_FLOAT_MAX);
        }
    }
}
