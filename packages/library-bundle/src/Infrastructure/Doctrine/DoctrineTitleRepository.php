<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\LibraryBundle\Domain\Contract\TitleRepository;
use Phil\LibraryBundle\Domain\Model\Title;

/**
 * The titles with Doctrine's ORM, which the forms edit.
 */
final readonly class DoctrineTitleRepository implements TitleRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Title
    {
        return $this->entityManager->find(Title::class, $id);
    }

    #[Override]
    public function save(Title $title): void
    {
        $this->entityManager->persist($title);
        $this->entityManager->flush();
    }
}
