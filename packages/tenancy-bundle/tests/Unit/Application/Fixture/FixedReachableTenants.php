<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application\Fixture;

use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;

final readonly class FixedReachableTenants implements ReachableTenantsProvider
{
    public function __construct(
        private ReachableTenants $reachable,
    ) {
    }

    public function reachable(): ReachableTenants
    {
        return $this->reachable;
    }
}
