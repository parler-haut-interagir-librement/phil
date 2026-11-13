<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application;

use Phil\EpubBundle\Application\Exception\UnknownBookSource;

/**
 * The application's book sources, by name.
 */
final readonly class BookSources
{
    /** @var array<array-key, BookSource> */
    private array $sources;

    /**
     * @param iterable<array-key, BookSource> $sources by name
     */
    public function __construct(iterable $sources = [])
    {
        $byName = [];

        foreach ($sources as $name => $source) {
            $byName[$name] = $source;
        }

        $this->sources = $byName;
    }

    public function get(string $name): BookSource
    {
        return $this->sources[$name] ?? throw UnknownBookSource::named($name, $this->names());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(strval(...), array_keys($this->sources));
    }
}
