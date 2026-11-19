<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Phil\ReservationBundle\Domain\Model\BookableResource;

final class InMemoryResources implements ResourceRepository
{
    /** @var array<string, BookableResource> */
    public array $resources = [];

    #[Override]
    public function get(string $id): ?BookableResource
    {
        return $this->resources[$id] ?? null;
    }

    #[Override]
    public function save(BookableResource $resource): void
    {
        $this->resources[$resource->id] = $resource;
    }

    #[Override]
    public function remove(BookableResource $resource): void
    {
        unset($this->resources[$resource->id]);
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->resources);
    }
}
