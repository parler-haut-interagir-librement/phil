<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;

final class InMemoryGroups implements ProductGroupRepository
{
    /** @var array<string, ProductGroup> */
    private array $groups = [];

    /** @var array<string, GroupMember> */
    private array $members = [];

    #[Override]
    public function find(string $id): ?ProductGroup
    {
        return $this->groups[$id] ?? null;
    }

    #[Override]
    public function add(ProductGroup $group): void
    {
        $this->groups[$group->id] = $group;
    }

    #[Override]
    public function findMember(string $sku): ?GroupMember
    {
        return $this->members[$sku] ?? null;
    }

    #[Override]
    public function membersOf(string $groupId): array
    {
        $members = array_values(array_filter($this->members, static fn (GroupMember $member): bool => $member->groupId === $groupId));
        usort($members, static fn (GroupMember $first, GroupMember $second): int => strcmp($first->sku, $second->sku));

        return $members;
    }

    #[Override]
    public function addMember(GroupMember $member): void
    {
        $this->members[$member->sku] = $member;
    }
}
