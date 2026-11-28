<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;

/**
 * The rotations of the console key in the table `phil_sites_console_key_rotation`.
 */
final readonly class DoctrineKeyRotationRepository implements KeyRotationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?KeyRotation
    {
        return $this->entityManager->find(KeyRotation::class, $id);
    }

    #[Override]
    public function save(KeyRotation $rotation): void
    {
        $this->entityManager->persist($rotation);
        $this->entityManager->flush();
    }

    #[Override]
    public function unfinished(): ?KeyRotation
    {
        return $this->entityManager->getRepository(KeyRotation::class)->findOneBy(['phase' => [RotationPhase::Staging, RotationPhase::Retiring]], ['id' => 'DESC']);
    }

    #[Override]
    public function latestTo(string $nextKey): ?KeyRotation
    {
        return $this->entityManager->getRepository(KeyRotation::class)->findOneBy(['nextKey' => $nextKey], ['id' => 'DESC']);
    }
}
