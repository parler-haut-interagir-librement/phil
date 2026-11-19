<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\Membership;
use Phil\CommerceWholesaleBundle\Domain\Repository\CustomerGroupRepository;

final class InMemoryGroups implements CustomerGroupRepository
{
    /** @var array<string, CustomerGroup> */
    private array $groups = [];

    /** @var array<string, Membership> */
    public array $memberships = [];

    public function __construct(CustomerGroup ...$groups)
    {
        foreach ($groups as $group) {
            $this->addGroup($group);
        }
    }

    #[Override]
    public function findGroup(string $code): ?CustomerGroup
    {
        return $this->groups[$code] ?? null;
    }

    #[Override]
    public function addGroup(CustomerGroup $group): void
    {
        $this->groups[$group->code] = $group;
    }

    #[Override]
    public function membershipOf(string $buyer): ?Membership
    {
        return $this->memberships[$buyer] ?? null;
    }

    #[Override]
    public function addMembership(Membership $membership): void
    {
        $this->memberships[$membership->buyer] = $membership;
    }

    #[Override]
    public function removeMembership(Membership $membership): void
    {
        unset($this->memberships[$membership->buyer]);
    }
}
