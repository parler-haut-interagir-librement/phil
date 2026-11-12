<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The segments in the table `phil_translation_segment`, through the entity manager; each save and removal is flushed
 * once.
 */
final readonly class DoctrineSegmentRepository implements SegmentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forDocument(string $document, LanguageTag $targetLanguage): array
    {
        return $this->entityManager->getRepository(SegmentRecord::class)->findBy(['document' => $document, 'targetLanguage' => $targetLanguage->value], ['position' => 'ASC']);
    }

    #[Override]
    public function save(SegmentRecord ...$records): void
    {
        foreach ($records as $record) {
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(SegmentRecord ...$records): void
    {
        foreach ($records as $record) {
            $this->entityManager->remove($record);
        }

        $this->entityManager->flush();
    }
}
