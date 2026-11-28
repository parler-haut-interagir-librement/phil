<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Contract;

use Phil\BenefitDeliverables\Entitlement;
use Phil\BenefitDeliverables\Period;

/**
 * Who is owed what for a period, read from where the tiers live: subscriptions, memberships, donations.
 *
 * The application implements it over its own model; the package never reads the tiers themselves, and depends on no
 * module of subscriptions. A member owed nothing for the period is left out.
 */
interface EntitlementSource
{
    /**
     * @param string|null $after the member the page starts after, `null` for the first page
     * @param int         $limit 1 to 1000
     *
     * @return list<Entitlement> at most `$limit`, ordered by member, each member after `$after` in byte order
     */
    public function entitlements(Period $period, ?string $after, int $limit): array;

    /**
     * @param list<string> $members at most 1000
     *
     * @return array<string, Entitlement> by member, those owed something for the period only
     */
    public function entitlementsOf(Period $period, array $members): array;
}
