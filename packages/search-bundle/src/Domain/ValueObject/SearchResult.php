<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

/**
 * A page of results: the identifiers of the documents found, best first, how many there are in all, and the values of
 * each facet asked with how many documents have them — most first, then by value, 100 at most.
 *
 * The values of a facet are counted among the documents the query finds once the filter of that same facet is left
 * out: a visitor who chose one value still sees how many the others would find.
 */
final readonly class SearchResult
{
    public const int MAX_FACET_VALUES = 100;

    /**
     * @var array<string, list<FacetValue>>
     */
    public array $facets;

    /**
     * @param list<string>                         $ids
     * @param array<string, array<array-key, int>> $facets each facet, its values and their counts, in any order
     */
    public function __construct(
        public array $ids,
        public int $total,
        array $facets = [],
    ) {
        $sorted = [];

        foreach ($facets as $field => $counts) {
            $values = [];

            foreach ($counts as $value => $count) {
                if ($count > 0) {
                    $values[] = new FacetValue((string) $value, $count);
                }
            }

            usort($values, static fn (FacetValue $a, FacetValue $b): int => [$b->count, $a->value] <=> [$a->count, $b->value]);
            $sorted[$field] = \array_slice($values, 0, self::MAX_FACET_VALUES);
        }

        $this->facets = $sorted;
    }

    /**
     * The values of a facet, most found first; none for a facet that was not asked.
     *
     * @return list<FacetValue>
     */
    public function facet(string $field): array
    {
        return $this->facets[$field] ?? [];
    }
}
