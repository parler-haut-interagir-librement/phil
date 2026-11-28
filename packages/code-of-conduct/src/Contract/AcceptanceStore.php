<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Contract;

use Phil\CodeOfConduct\Acceptance;

/**
 * The acceptances of the members, every one kept as a proof.
 */
interface AcceptanceStore
{
    /**
     * The acceptance of the highest version by the member in the scope, `null` when they accepted none.
     */
    public function latest(string $scope, string $member): ?Acceptance;

    public function add(Acceptance $acceptance): void;
}
