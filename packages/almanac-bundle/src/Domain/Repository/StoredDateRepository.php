<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Domain\Repository;

use Phil\AlmanacBundle\Domain\Model\StoredDate;

/**
 * The computed dates kept in the database.
 */
interface StoredDateRepository
{
    /**
     * @return list<StoredDate> by key
     */
    public function all(): array;

    public function ofKey(string $key): ?StoredDate;

    public function save(StoredDate $date): void;

    public function remove(StoredDate $date): void;
}
