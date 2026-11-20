<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceCatalogBundle\Application\Contract\ProductFamilies;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use SortDirection;

/**
 * `ProductRepository` and `ProductFamilies` on the Doctrine ORM: a product is one row of
 * `phil_commerce_catalog_product`, a variant one whose `family` names its family.
 */
final readonly class DoctrineProductRepository implements ProductRepository, ProductFamilies
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $sku): ?Product
    {
        return $this->entityManager->find(Product::class, $sku);
    }

    #[Override]
    public function variantsOf(string $family): array
    {
        /** @var list<Product> */
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.family = :family')
            ->orderBy('p.sku', SortDirection::Ascending)
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function add(Product $product): void
    {
        $this->entityManager->persist($product);
    }
}
