<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Phil\ReservationBundle\Domain\Model\BookableResource;

/**
 * The resources with Doctrine.
 */
final readonly class DoctrineResourceRepository implements ResourceRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?BookableResource
    {
        return $this->entityManager->find(BookableResource::class, $id);
    }

    #[Override]
    public function save(BookableResource $resource): void
    {
        $this->entityManager->persist($resource);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(BookableResource $resource): void
    {
        $this->entityManager->remove($resource);
        $this->entityManager->flush();
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(BookableResource::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']);
    }
}
