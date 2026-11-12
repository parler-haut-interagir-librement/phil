<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TranslationBundle\Domain\Model\MemoryRecord;
use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryStore;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Psr\Clock\ClockInterface;

/**
 * The translation memory in the table `phil_translation_memory`: exact matches by the index of languages and
 * fingerprint, candidates by the index of languages and length, one row per source, the latest translation winning.
 */
final readonly class DoctrineMemoryStore implements MemoryStore
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function exact(LanguageTag $source, LanguageTag $target, Fingerprint $fingerprint): iterable
    {
        $record = $this->record($source, $target, $fingerprint);

        return $record instanceof MemoryRecord ? [$record->unit()] : [];
    }

    #[Override]
    public function candidates(LanguageTag $source, LanguageTag $target, int $minLength, int $maxLength): iterable
    {
        /** @var list<MemoryRecord> $records */
        $records = $this->entityManager->createQueryBuilder()
            ->select('unit')
            ->from(MemoryRecord::class, 'unit')
            ->where('unit.sourceLanguage = :source')
            ->andWhere('unit.targetLanguage = :target')
            ->andWhere('unit.length BETWEEN :min AND :max')
            ->setParameter('source', $source->value)
            ->setParameter('target', $target->value)
            ->setParameter('min', $minLength)
            ->setParameter('max', $maxLength)
            ->getQuery()
            ->getResult();

        foreach ($records as $record) {
            yield $record->unit();
        }
    }

    #[Override]
    public function add(MemoryUnit $unit): void
    {
        $now = new DateTimeImmutable('@'.$this->clock->now()->getTimestamp());
        $record = $this->record($unit->sourceLanguage, $unit->targetLanguage, $unit->fingerprint);

        if (!$record instanceof MemoryRecord) {
            $this->entityManager->persist(MemoryRecord::of($unit, $now));
        } else {
            $record->replace($unit, $now);
        }

        $this->entityManager->flush();
    }

    private function record(LanguageTag $source, LanguageTag $target, Fingerprint $fingerprint): ?MemoryRecord
    {
        return $this->entityManager->getRepository(MemoryRecord::class)->findOneBy(['sourceLanguage' => $source->value, 'targetLanguage' => $target->value, 'fingerprint' => $fingerprint->value]);
    }
}
