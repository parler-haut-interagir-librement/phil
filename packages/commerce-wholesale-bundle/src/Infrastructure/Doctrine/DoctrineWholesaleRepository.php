<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\Membership;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Domain\Model\Receivable;
use Phil\CommerceWholesaleBundle\Domain\Repository\CreditRepository;
use Phil\CommerceWholesaleBundle\Domain\Repository\CustomerGroupRepository;
use Phil\CommerceWholesaleBundle\Domain\Repository\QuantityRuleRepository;

/**
 * `CustomerGroupRepository` on the Doctrine ORM: groups in `phil_commerce_wholesale_group`, memberships in
 * `phil_commerce_wholesale_membership`, quantity rules in `phil_commerce_wholesale_quantity_rule`.
 */
final readonly class DoctrineWholesaleRepository implements CustomerGroupRepository, CreditRepository, QuantityRuleRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findGroup(string $code): ?CustomerGroup
    {
        return $this->entityManager->find(CustomerGroup::class, $code);
    }

    #[Override]
    public function addGroup(CustomerGroup $group): void
    {
        $this->entityManager->persist($group);
    }

    #[Override]
    public function membershipOf(string $buyer): ?Membership
    {
        return $this->entityManager->find(Membership::class, $buyer);
    }

    #[Override]
    public function addMembership(Membership $membership): void
    {
        $this->entityManager->persist($membership);
    }

    #[Override]
    public function removeMembership(Membership $membership): void
    {
        $this->entityManager->remove($membership);
    }

    #[Override]
    public function findAccount(string $buyer): ?CreditAccount
    {
        return $this->entityManager->find(CreditAccount::class, $buyer);
    }

    #[Override]
    public function addAccount(CreditAccount $account): void
    {
        $this->entityManager->persist($account);
    }

    #[Override]
    public function findReceivable(string $id): ?Receivable
    {
        return $this->entityManager->find(Receivable::class, $id);
    }

    #[Override]
    public function receivableOfPayment(string $payment): ?Receivable
    {
        return $this->entityManager->getRepository(Receivable::class)->findOneBy(['payment' => $payment]);
    }

    #[Override]
    public function addReceivable(Receivable $receivable): void
    {
        $this->entityManager->persist($receivable);
    }

    #[Override]
    public function receivablesOf(string $buyer): array
    {
        return $this->entityManager->getRepository(Receivable::class)->findBy(['buyer' => $buyer], ['issuedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function findRule(string $sku, ?string $customerGroup): ?QuantityRule
    {
        return $this->entityManager->find(QuantityRule::class, ['sku' => $sku, 'group' => $customerGroup ?? QuantityRule::EVERY_GROUP]);
    }

    #[Override]
    public function rulesOf(string $sku): array
    {
        return $this->entityManager->getRepository(QuantityRule::class)->findBy(['sku' => $sku]);
    }

    #[Override]
    public function addRule(QuantityRule $rule): void
    {
        $this->entityManager->persist($rule);
    }

    #[Override]
    public function removeRule(QuantityRule $rule): void
    {
        $this->entityManager->remove($rule);
    }
}
