<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\LibraryBundle\Domain\Contract\CopyRepository;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Title;
use SortDirection;

/**
 * The copies with Doctrine's ORM.
 */
final readonly class DoctrineCopyRepository implements CopyRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Copy
    {
        return $this->entityManager->find(Copy::class, $id);
    }

    #[Override]
    public function byBarcode(string $barcode): ?Copy
    {
        return $this->entityManager->getRepository(Copy::class)->findOneBy(['barcode' => $barcode]);
    }

    #[Override]
    public function ofTitle(Title $title, int $limit): array
    {
        /** @var list<Copy> */
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Copy::class, 'c')
            ->where('c.title = :title')
            ->orderBy('c.barcode', SortDirection::Ascending)
            ->setParameter('title', $title->id)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function save(Copy $copy): void
    {
        $this->entityManager->persist($copy);
        $this->entityManager->flush();
    }
}
