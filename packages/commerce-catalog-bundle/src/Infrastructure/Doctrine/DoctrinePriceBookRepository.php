<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\Repository\PriceBookRepository;
use SortDirection;

/**
 * `PriceBookRepository` on the Doctrine ORM: a price book is one row of `phil_commerce_catalog_price_book`, its
 * tiers in a JSON column; the active books are read on the index of `active`.
 */
final readonly class DoctrinePriceBookRepository implements PriceBookRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $code): ?PriceBook
    {
        return $this->entityManager->find(PriceBook::class, $code);
    }

    #[Override]
    public function active(): array
    {
        /** @var list<PriceBook> */
        return $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(PriceBook::class, 'b')
            ->where('b.active = :active')
            ->orderBy('b.code', SortDirection::Ascending)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function add(PriceBook $book): void
    {
        $this->entityManager->persist($book);
    }
}
