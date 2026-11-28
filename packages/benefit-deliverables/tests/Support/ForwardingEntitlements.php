<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use Override;
use Phil\BenefitDeliverables\Contract\EntitlementSource;
use Phil\BenefitDeliverables\Period;

/**
 * A source that forwards to another, for a test to change one of its answers.
 */
abstract class ForwardingEntitlements implements EntitlementSource
{
    public function __construct(private readonly EntitlementSource $inner)
    {
    }

    #[Override]
    public function entitlements(Period $period, ?string $after, int $limit): array
    {
        return $this->inner->entitlements($period, $after, $limit);
    }

    #[Override]
    public function entitlementsOf(Period $period, array $members): array
    {
        return $this->inner->entitlementsOf($period, $members);
    }
}
