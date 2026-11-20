<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceCatalogBundle\Application\Contract\CommunityCopyLedger;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyClaim;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyFunding;
use Phil\CommerceCatalogBundle\Domain\Model\Product;

/**
 * `CommunityCopyLedger` on the Doctrine ORM: one row per product and order in
 * `phil_commerce_catalog_community_copy_funding`, one per product and claimant in
 * `phil_commerce_catalog_community_copy_claim`; sums and counts are read in SQL, and a claim locks the product's row.
 */
final readonly class DoctrineCommunityCopyLedger implements CommunityCopyLedger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function funded(string $sku): int
    {
        $sum = $this->entityManager->createQuery(\sprintf('SELECT SUM(f.amount) FROM %s f WHERE f.sku = :sku', CommunityCopyFunding::class))
            ->setParameter('sku', $sku)
            ->getSingleScalarResult();

        return is_numeric($sum) ? (int) $sum : 0;
    }

    #[Override]
    public function claimed(string $sku): int
    {
        $count = $this->entityManager->createQuery(\sprintf('SELECT COUNT(c.claimant) FROM %s c WHERE c.sku = :sku', CommunityCopyClaim::class))
            ->setParameter('sku', $sku)
            ->getSingleScalarResult();

        return is_numeric($count) ? (int) $count : 0;
    }

    #[Override]
    public function isFundedBy(string $sku, string $orderNumber): bool
    {
        return $this->entityManager->find(CommunityCopyFunding::class, ['sku' => $sku, 'orderNumber' => $orderNumber]) instanceof CommunityCopyFunding;
    }

    #[Override]
    public function hasClaimed(string $sku, string $claimant): bool
    {
        return $this->entityManager->find(CommunityCopyClaim::class, ['sku' => $sku, 'claimant' => $claimant]) instanceof CommunityCopyClaim;
    }

    #[Override]
    public function addFunding(CommunityCopyFunding $funding): void
    {
        $this->entityManager->persist($funding);
    }

    #[Override]
    public function addClaim(CommunityCopyClaim $claim): void
    {
        $this->entityManager->persist($claim);
    }

    #[Override]
    public function lock(string $sku): void
    {
        $this->entityManager->find(Product::class, $sku, LockMode::PESSIMISTIC_WRITE);
    }
}
