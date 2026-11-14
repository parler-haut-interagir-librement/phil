<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Repository;

use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;
use Phil\OnboardingBundle\Domain\Model\Progress;

/**
 * Where the progress of the users lives, one record per user and flow.
 */
interface ProgressRepository
{
    public function find(string $user, string $flow): ?Progress;

    /**
     * Every progress of a user, in no particular order.
     *
     * @return list<Progress>
     */
    public function ofUser(string $user): array;

    /**
     * Keeps a new progress, or the changes made to one read from the store.
     *
     * @throws ProgressNotSaved when another request kept a progress of the same user and flow first
     */
    public function save(Progress $progress): void;

    /**
     * Removes every progress of a user, to erase their account.
     */
    public function removeAllOf(string $user): void;
}
