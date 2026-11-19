<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;

/**
 * `ProductGroupRepository` on the Doctrine ORM: a group is one row of `phil_commerce_product_group`, each member one row
 * of `phil_commerce_product_group_member`, found by its SKU or by its group, which is indexed.
 */
final readonly class DoctrineProductGroupRepository implements ProductGroupRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $id): ?ProductGroup
    {
        return $this->entityManager->find(ProductGroup::class, $id);
    }

    #[Override]
    public function add(ProductGroup $group): void
    {
        $this->entityManager->persist($group);
    }

    #[Override]
    public function findMember(string $sku): ?GroupMember
    {
        return $this->entityManager->find(GroupMember::class, $sku);
    }

    #[Override]
    public function membersOf(string $groupId): array
    {
        /** @var list<GroupMember> */
        return $this->entityManager->getRepository(GroupMember::class)->findBy(['groupId' => $groupId], ['sku' => 'ASC']);
    }

    #[Override]
    public function addMember(GroupMember $member): void
    {
        $this->entityManager->persist($member);
    }
}
