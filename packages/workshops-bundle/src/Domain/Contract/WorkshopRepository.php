<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Contract;

use Phil\WorkshopsBundle\Domain\Model\Workshop;

/**
 * Where the workshops are kept.
 */
interface WorkshopRepository
{
    public function get(string $id): ?Workshop;

    public function save(Workshop $workshop): void;

    /**
     * Removes the workshop with its sessions and their registrations.
     */
    public function remove(Workshop $workshop): void;
}
