<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use Override;
use Phil\BenefitDeliverables\Contract\EntitlementSource;
use Phil\BenefitDeliverables\Entitlement;
use Phil\BenefitDeliverables\Period;

final class InMemoryEntitlements implements EntitlementSource
{
    /** @var array<string, array<string, Entitlement>> by period, then member */
    private array $entitlements = [];

    /** @var list<array{?string, int}> the cursor and limit of each page read */
    public array $pages = [];

    public function owe(Period $period, Entitlement ...$entitlements): self
    {
        foreach ($entitlements as $entitlement) {
            $this->entitlements[$period->name][$entitlement->member] = $entitlement;
        }

        ksort($this->entitlements[$period->name], \SORT_STRING);

        return $this;
    }

    #[Override]
    public function entitlements(Period $period, ?string $after, int $limit): array
    {
        $this->pages[] = [$after, $limit];
        $page = array_filter($this->entitlements[$period->name] ?? [], static fn (Entitlement $entitlement): bool => null === $after || strcmp($entitlement->member, $after) > 0);

        return \array_slice(array_values($page), 0, $limit);
    }

    #[Override]
    public function entitlementsOf(Period $period, array $members): array
    {
        return array_intersect_key($this->entitlements[$period->name] ?? [], array_flip($members));
    }
}
