<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The comments in the table `phil_translation_comment`, through the entity manager; each save and removal is flushed.
 */
final readonly class DoctrineCommentRepository implements CommentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forDocument(string $document, LanguageTag $targetLanguage): array
    {
        return $this->entityManager->getRepository(CommentRecord::class)->findBy(['document' => $document, 'targetLanguage' => $targetLanguage->value], ['id' => 'ASC']);
    }

    #[Override]
    public function get(int $id): ?CommentRecord
    {
        return $this->entityManager->find(CommentRecord::class, $id);
    }

    #[Override]
    public function save(CommentRecord $record): void
    {
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(CommentRecord $record): void
    {
        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }
}
