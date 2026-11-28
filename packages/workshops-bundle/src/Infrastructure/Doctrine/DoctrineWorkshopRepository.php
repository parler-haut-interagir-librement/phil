<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\WorkshopsBundle\Domain\Contract\WorkshopRepository;
use Phil\WorkshopsBundle\Domain\Model\Workshop;

/**
 * The workshops with Doctrine. Removing one removes its sessions and their registrations by the database's cascade.
 */
final readonly class DoctrineWorkshopRepository implements WorkshopRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Workshop
    {
        return $this->entityManager->find(Workshop::class, $id);
    }

    #[Override]
    public function save(Workshop $workshop): void
    {
        $this->entityManager->persist($workshop);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Workshop $workshop): void
    {
        $this->entityManager->remove($workshop);
        $this->entityManager->flush();
    }
}
