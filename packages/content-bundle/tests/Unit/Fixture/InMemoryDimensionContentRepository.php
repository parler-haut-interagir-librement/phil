<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use LogicException;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * Rows in memory, written the way the Doctrine adapter writes them: `add()` and `remove()` wait for `flush()`,
 * lookups read only what was flushed, and a flush writes the additions before the removals and refuses two
 * rows of one dimension under one root, as the unique index does. A caller that forgets a flush is caught here
 * as it would be on a database.
 */
final class InMemoryDimensionContentRepository implements DimensionContentRepository
{
    /**
     * @var list<DimensionContent>
     */
    private array $rows = [];

    /**
     * @var list<DimensionContent>
     */
    private array $added = [];

    /**
     * @var list<DimensionContent>
     */
    private array $removed = [];

    /**
     * @var list<string>
     */
    public array $lookups = [];

    /**
     * Stores rows at once, as a fixture.
     */
    public function store(DimensionContent ...$rows): void
    {
        foreach ($rows as $row) {
            $this->rows[] = $row;
        }
    }

    public function add(DimensionContent $row): void
    {
        $this->added[] = $row;
    }

    public function remove(DimensionContent $row): void
    {
        $this->removed[] = $row;
    }

    public function flush(): void
    {
        $rows = [...$this->rows, ...$this->added];
        $this->added = [];
        $seen = [];

        foreach ($rows as $row) {
            $key = spl_object_id($row->root()).'/'.$row->dimension()->toString();

            if (isset($seen[$key])) {
                throw new LogicException(\sprintf('Two rows of the dimension "%s" under one root.', $row->dimension()->toString()));
            }

            $seen[$key] = true;
        }

        $removed = $this->removed;
        $this->removed = [];
        $this->rows = array_values(array_filter($rows, static fn (DimensionContent $row): bool => !\in_array($row, $removed, true)));
    }

    public function find(ContentRoot $root, DimensionKey $dimension): ?DimensionContent
    {
        $this->lookups[] = $dimension->toString();

        foreach ($this->rows as $row) {
            if ($row->root() === $root && $row->dimension()->equals($dimension)) {
                return $row;
            }
        }

        return null;
    }

    public function findAll(ContentRoot $root, Stage $stage, Version $version): array
    {
        $rows = array_values(array_filter(
            $this->rows,
            static fn (DimensionContent $row): bool => $row->root() === $root
                && $row->dimension()->stage === $stage
                && $row->dimension()->version->equals($version),
        ));

        usort($rows, static fn (DimensionContent $a, DimensionContent $b): int => strcmp(
            $a->dimension()->locale->value ?? '',
            $b->dimension()->locale->value ?? '',
        ));

        return $rows;
    }

    public function latestVersion(ContentRoot $root, Stage $stage): ?Version
    {
        $latest = null;

        foreach ($this->rows as $row) {
            $version = $row->dimension()->version;

            if ($row->root() === $root && $row->dimension()->stage === $stage && (null === $latest || $version->isAfter($latest))) {
                $latest = $version;
            }
        }

        return $latest;
    }

    public function latestLocalizedVersion(ContentRoot $root, Stage $stage, Locale $locale): ?Version
    {
        $latest = null;

        foreach ($this->rows as $row) {
            $dimension = $row->dimension();

            if ($row->root() === $root && $dimension->stage === $stage && $dimension->locale?->value === $locale->value && (null === $latest || $dimension->version->isAfter($latest))) {
                $latest = $dimension->version;
            }
        }

        return $latest;
    }

    public function findVersions(ContentRoot $root, Stage $stage, Locale $locale): array
    {
        $rows = array_values(array_filter(
            $this->rows,
            static fn (DimensionContent $row): bool => $row->root() === $root && $row->dimension()->stage === $stage && $row->dimension()->locale?->value === $locale->value,
        ));
        usort($rows, static fn (DimensionContent $a, DimensionContent $b): int => $b->dimension()->version->number <=> $a->dimension()->version->number);

        return $rows;
    }

    /**
     * Every flushed row, in the order stored.
     *
     * @return list<DimensionContent>
     */
    public function flushed(): array
    {
        return $this->rows;
    }

    /**
     * Every flushed row, as `fr/draft/1` keys, sorted: what a test compares the storage with.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_map(static fn (DimensionContent $row): string => $row->dimension()->toString(), $this->rows);
        sort($keys);

        return $keys;
    }
}
