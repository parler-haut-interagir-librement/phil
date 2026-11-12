<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\DirectoryBundle\Domain\Exception\ListingRefused;

/**
 * The genres of the directory, by key, in the order of the configuration.
 */
final readonly class Genres
{
    /** @var array<string, Genre> */
    private array $genres;

    /**
     * @param list<Genre> $genres
     */
    public function __construct(array $genres)
    {
        $byKey = [];

        foreach ($genres as $genre) {
            $byKey[$genre->key] = $genre;
        }

        $this->genres = $byKey;
    }

    public function get(string $key): Genre
    {
        return $this->genres[$key] ?? throw ListingRefused::because(\sprintf('The directory has no genre "%s".', $key));
    }

    /**
     * @return list<Genre>
     */
    public function all(): array
    {
        return array_values($this->genres);
    }
}
