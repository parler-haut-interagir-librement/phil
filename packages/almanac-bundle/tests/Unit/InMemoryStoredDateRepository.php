<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit;

use Override;
use Phil\AlmanacBundle\Domain\Model\StoredDate;
use Phil\AlmanacBundle\Domain\Repository\StoredDateRepository;

/**
 * Stored dates in memory, by key.
 */
final class InMemoryStoredDateRepository implements StoredDateRepository
{
    /** @var array<string, StoredDate> */
    private array $dates = [];

    #[Override]
    public function all(): array
    {
        ksort($this->dates);

        return array_values($this->dates);
    }

    #[Override]
    public function ofKey(string $key): ?StoredDate
    {
        return $this->dates[$key] ?? null;
    }

    #[Override]
    public function save(StoredDate $date): void
    {
        $this->dates[$date->key()] = $date;
    }

    #[Override]
    public function remove(StoredDate $date): void
    {
        unset($this->dates[$date->key()]);
    }
}
