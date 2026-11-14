<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;

/**
 * Purges one store of what has outlived its retention period.
 *
 * The adapter belongs to the application: which table, which partition, which bucket prefix, and whether the
 * purge is a `DELETE`, a partition drop or an object deletion.
 */
interface RetentionPurger
{
    /**
     * The store this purger answers for, as the retention schedule names it.
     */
    public function store(): string;

    /**
     * @return int how many records were purged
     */
    public function purge(PurgeOrder $order): int;
}
