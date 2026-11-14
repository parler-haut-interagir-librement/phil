<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Security;

use Override;
use Phil\TenancyBundle\Application\Contract\AccountSharing;
use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The accounts shared by `phil_tenancy.authentication.shared_tenants` — groups of tenants whose accounts are signed
 * in on every tenant of the group —, by the shop network, whose linked shops share their customer accounts, and by
 * `shared_sessions`, which signs a session opened in no tenant — a central back office — in on every tenant. Every
 * account of a tenant is treated alike; an application that shares some accounts only implements the port.
 */
final readonly class ConfiguredAccountSharing implements AccountSharing
{
    /** @var list<list<TenantId>> */
    private array $groups;

    private ShopNetwork $shops;

    /**
     * @param list<list<string>> $groups         the references of each group of tenants that share their accounts
     * @param bool               $sharedSessions whether a session opened in no tenant is signed in on every tenant;
     *                                           refused by default
     */
    public function __construct(
        array $groups = [],
        ?ShopNetwork $shops = null,
        private bool $sharedSessions = false,
    ) {
        $this->shops = $shops ?? ShopNetwork::none();
        $this->groups = array_map(
            static fn (array $group): array => array_map(TenantId::fromString(...), $group),
            $groups,
        );
    }

    #[Override]
    public function isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited): bool
    {
        if (!$signedInOn instanceof TenantId) {
            return $this->sharedSessions;
        }

        if (!$visited instanceof TenantId) {
            return false;
        }

        if ($this->shops->sharesAccounts($signedInOn, $visited)) {
            return true;
        }

        return array_any($this->groups, static fn (array $group): bool => self::holds($group, $signedInOn) && self::holds($group, $visited));
    }

    /**
     * @param list<TenantId> $group
     */
    private static function holds(array $group, TenantId $tenant): bool
    {
        return array_any($group, static fn (TenantId $member): bool => $member->equals($tenant));
    }
}
