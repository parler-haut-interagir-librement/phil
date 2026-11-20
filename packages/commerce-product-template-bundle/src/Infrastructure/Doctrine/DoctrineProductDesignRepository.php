<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;

/**
 * `ProductDesignRepository` on the Doctrine ORM: a design is one row of `phil_commerce_product_design`.
 */
final readonly class DoctrineProductDesignRepository implements ProductDesignRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $code): ?ProductDesign
    {
        return $this->entityManager->find(ProductDesign::class, $code);
    }

    #[Override]
    public function add(ProductDesign $design): void
    {
        $this->entityManager->persist($design);
    }
}
