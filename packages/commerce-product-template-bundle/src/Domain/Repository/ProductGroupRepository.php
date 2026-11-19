<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Repository;

use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;

/**
 * Finds and stores product groups and their members. `Infrastructure\Doctrine\DoctrineProductGroupRepository` is the
 * Doctrine adapter.
 */
interface ProductGroupRepository
{
    public function find(string $id): ?ProductGroup;

    public function add(ProductGroup $group): void;

    /**
     * The member of that SKU, in whatever group.
     */
    public function findMember(string $sku): ?GroupMember;

    /**
     * @return list<GroupMember> the members of the group, active or withdrawn, by SKU
     */
    public function membersOf(string $groupId): array;

    public function addMember(GroupMember $member): void;
}
