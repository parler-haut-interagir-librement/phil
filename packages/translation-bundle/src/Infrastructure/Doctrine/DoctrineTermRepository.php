<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Domain\Repository\TermRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The glossary terms in the table `phil_translation_term`, through the entity manager; each save and removal is
 * flushed.
 */
final readonly class DoctrineTermRepository implements TermRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forLanguages(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        return $this->entityManager->getRepository(TermRecord::class)->findBy(['sourceLanguage' => $sourceLanguage->value, 'targetLanguage' => $targetLanguage->value]);
    }

    #[Override]
    public function get(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, string $term): ?TermRecord
    {
        return $this->entityManager->find(TermRecord::class, ['sourceLanguage' => $sourceLanguage->value, 'targetLanguage' => $targetLanguage->value, 'term' => $term]);
    }

    #[Override]
    public function save(TermRecord $record): void
    {
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(TermRecord $record): void
    {
        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }
}
