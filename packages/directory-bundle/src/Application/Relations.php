<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\DirectoryBundle\Domain\Exception\ListingRefused;

/**
 * The relations of the directory, by key, in the order of the configuration.
 */
final readonly class Relations
{
    /** @var array<string, Relation> */
    private array $relations;

    /**
     * @param list<Relation> $relations
     */
    public function __construct(array $relations = [])
    {
        $byKey = [];

        foreach ($relations as $relation) {
            $byKey[$relation->key] = $relation;
        }

        $this->relations = $byKey;
    }

    public function has(string $key): bool
    {
        return isset($this->relations[$key]);
    }

    public function get(string $key): Relation
    {
        return $this->relations[$key] ?? throw ListingRefused::because(\sprintf('The directory has no relation "%s".', $key));
    }

    /**
     * The relations written on the listings of a genre.
     *
     * @return list<Relation>
     */
    public function from(string $genre): array
    {
        return array_values(array_filter($this->relations, static fn (Relation $relation): bool => $relation->from === $genre));
    }

    /**
     * The relations that point at the listings of a genre.
     *
     * @return list<Relation>
     */
    public function to(string $genre): array
    {
        return array_values(array_filter($this->relations, static fn (Relation $relation): bool => $relation->to === $genre));
    }
}
