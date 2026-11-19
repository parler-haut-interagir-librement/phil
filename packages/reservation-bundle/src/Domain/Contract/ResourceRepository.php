<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Contract;

use Phil\ReservationBundle\Domain\Model\BookableResource;

/**
 * Where the resources are kept.
 */
interface ResourceRepository
{
    public function get(string $id): ?BookableResource;

    public function save(BookableResource $resource): void;

    /**
     * Removes it with its reservations.
     */
    public function remove(BookableResource $resource): void;

    /**
     * @return list<BookableResource> by name
     */
    public function all(): array;
}
