<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Fixture;

use Override;
use Phil\WorkshopsBundle\Domain\Contract\WorkshopRepository;
use Phil\WorkshopsBundle\Domain\Model\Workshop;

/**
 * The workshops in an array.
 */
final class InMemoryWorkshops implements WorkshopRepository
{
    /** @var array<string, Workshop> */
    public array $workshops = [];

    #[Override]
    public function get(string $id): ?Workshop
    {
        return $this->workshops[$id] ?? null;
    }

    #[Override]
    public function save(Workshop $workshop): void
    {
        $this->workshops[$workshop->id] = $workshop;
    }

    #[Override]
    public function remove(Workshop $workshop): void
    {
        unset($this->workshops[$workshop->id]);
    }
}
