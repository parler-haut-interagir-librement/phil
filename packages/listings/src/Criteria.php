<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;

/**
 * The criteria of a listing, or the criteria a search wants, one per name, at most {@see self::MAX_COUNT}.
 *
 * A listing's criteria meet a search's when every wanted criterion meets the listing's criterion of the same name;
 * a listing without that criterion does not meet it. A search that wants nothing meets every listing.
 */
final readonly class Criteria
{
    public const int MAX_COUNT = 32;

    /**
     * @param array<string, Criterion> $byName
     */
    private function __construct(
        private array $byName,
    ) {
    }

    /**
     * @param list<Criterion> $criteria
     *
     * @throws InvalidListing
     */
    public static function of(array $criteria = []): self
    {
        if (\count($criteria) > self::MAX_COUNT) {
            throw InvalidListing::because(\sprintf('A listing has at most %d criteria.', self::MAX_COUNT));
        }

        $byName = [];

        foreach ($criteria as $criterion) {
            if (isset($byName[$criterion->name])) {
                throw InvalidListing::because(\sprintf('The criterion "%s" is given twice.', $criterion->name));
            }

            $byName[$criterion->name] = $criterion;
        }

        return new self($byName);
    }

    public function meets(self $wanted): bool
    {
        return array_all($wanted->byName, fn (Criterion $criterion, string $name): bool => isset($this->byName[$name]) && $this->byName[$name]->meets($criterion));
    }

    public function get(string $name): ?Criterion
    {
        return $this->byName[$name] ?? null;
    }

    /**
     * @return list<Criterion>
     */
    public function all(): array
    {
        return array_values($this->byName);
    }
}
