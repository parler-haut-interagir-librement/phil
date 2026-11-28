<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Contract\ItemRepository;
use Phil\BulkActions\Selection\FilterCriteria;

/**
 * Products in rows, with an identity map as an ORM keeps one: flush() writes the loaded products and raises the
 * version of those that changed, clear() forgets them.
 */
final class MemoryProducts implements ItemRepository
{
    /** @var array<array-key, array{name: string, status: string, price: int|null, version: int}> */
    public array $rows = [];

    /** @var array<string, Product> */
    private array $loaded = [];

    /** @var array<string, object> objects find() gives instead of the rows, by identifier */
    public array $impostors = [];

    /** @var list<list<string>> the identifiers of each call to find() */
    public array $finds = [];

    /** Whether supports() accepts the filter "status", as long as the repository knows it. */
    public bool $acceptsFilters = true;

    /** Whether matching() ignores its cursor, as a faulty repository would. */
    public bool $ignoreCursor = false;

    public function add(Product ...$products): void
    {
        foreach ($products as $product) {
            $this->rows[$product->id] = ['name' => $product->name, 'status' => $product->status, 'price' => $product->price, 'version' => $product->version];
        }
    }

    public function seed(int $count, string $status = 'draft'): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->add(new Product(\sprintf('p%04d', $i), 'Product '.$i, $status));
        }
    }

    public function find(array $identifiers): array
    {
        $this->finds[] = $identifiers;
        $found = [];

        foreach ($identifiers as $identifier) {
            if (isset($this->impostors[$identifier])) {
                $found[$identifier] = $this->impostors[$identifier];

                continue;
            }

            $row = $this->rows[$identifier] ?? null;

            if (null !== $row) {
                $found[$identifier] = $this->loaded[$identifier] ??= new Product($identifier, $row['name'], $row['status'], $row['price'], $row['version']);
            }
        }

        return $found;
    }

    public function supports(FilterCriteria $filter): bool
    {
        return $this->acceptsFilters && 'status' === $filter->name && \is_string($filter->parameters['status'] ?? null);
    }

    public function count(FilterCriteria $filter, array $excluded): int
    {
        return \count($this->all($filter, $excluded));
    }

    public function matching(FilterCriteria $filter, array $excluded, ?string $after, int $limit): array
    {
        $matching = [];

        foreach ($this->all($filter, $excluded) as $identifier) {
            if (null === $after || $this->ignoreCursor || strcmp($identifier, $after) > 0) {
                $matching[] = $identifier;
            }
        }

        return \array_slice($matching, 0, $limit);
    }

    public function identify(object $item): string
    {
        \assert($item instanceof Product);

        return $item->id;
    }

    public function version(object $item): ?string
    {
        return $item instanceof Product ? (string) $item->version : null;
    }

    public function setStatus(string $identifier, string $status): void
    {
        $row = $this->rows[$identifier];
        $row['status'] = $status;
        $this->rows[$identifier] = $row;
    }

    public function flush(): void
    {
        foreach ($this->loaded as $identifier => $product) {
            $row = $this->rows[$identifier] ?? null;

            if (null === $row) {
                continue;
            }

            $changed = $row['name'] !== $product->name || $row['status'] !== $product->status || $row['price'] !== $product->price;
            $version = $changed ? $row['version'] + 1 : $row['version'];
            $this->rows[$identifier] = ['name' => $product->name, 'status' => $product->status, 'price' => $product->price, 'version' => $version];
            $product->version = $version;
        }
    }

    public function clear(): void
    {
        $this->loaded = [];
    }

    /**
     * Changes a row behind the identity map's back, as another person would.
     */
    public function touch(string $identifier): void
    {
        $row = $this->rows[$identifier];
        $row['name'] .= ' (edited)';
        ++$row['version'];
        $this->rows[$identifier] = $row;
        unset($this->loaded[$identifier]);
    }

    /**
     * @param list<string> $excluded
     *
     * @return list<string>
     */
    private function all(FilterCriteria $filter, array $excluded): array
    {
        $status = $filter->parameters['status'] ?? null;
        $all = [];

        foreach ($this->rows as $identifier => $row) {
            if ($row['status'] === $status && !\in_array((string) $identifier, $excluded, true)) {
                $all[] = (string) $identifier;
            }
        }

        sort($all, \SORT_STRING);

        return $all;
    }
}
