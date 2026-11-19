<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Fixtures;

use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Phil\ReservationBundle\Domain\Model\BookableResource;

/**
 * The resources of phil/reservation-bundle in an array.
 */
final class Resources implements ResourceRepository
{
    /** @var array<string, BookableResource> */
    private array $resources = [];

    public function get(string $id): ?BookableResource
    {
        return $this->resources[$id] ?? null;
    }

    public function save(BookableResource $resource): void
    {
        $this->resources[$resource->id] = $resource;
    }

    public function remove(BookableResource $resource): void
    {
        unset($this->resources[$resource->id]);
    }

    public function all(): array
    {
        return array_values($this->resources);
    }
}
