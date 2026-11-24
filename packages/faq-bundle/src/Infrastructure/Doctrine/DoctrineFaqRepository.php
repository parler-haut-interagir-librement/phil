<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;

/**
 * The questions in the Doctrine ORM. A vote is counted by one `UPDATE … SET helpful_yes = helpful_yes + 1`, so that two
 * votes at the same moment both count; the totals are then read again from the table, not from the unit of work.
 */
final readonly class DoctrineFaqRepository implements FaqRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function sections(): array
    {
        /** @var list<Section> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')->from(Section::class, 's')
            ->orderBy('s.position')->addOrderBy('s.id')
            ->getQuery()->getResult();
    }

    #[Override]
    public function section(string $id): ?Section
    {
        return $this->entityManager->find(Section::class, $id);
    }

    #[Override]
    public function sectionByKey(string $key): ?Section
    {
        return $this->entityManager->getRepository(Section::class)->findOneBy(['key' => $key]);
    }

    #[Override]
    public function questionsOf(Section $section): array
    {
        /** @var list<Question> */
        return $this->entityManager->createQueryBuilder()
            ->select('q')->from(Question::class, 'q')
            ->where('q.section = :section')->setParameter('section', $section->id)
            ->orderBy('q.position')->addOrderBy('q.id')
            ->getQuery()->getResult();
    }

    #[Override]
    public function questions(): array
    {
        /** @var list<Question> */
        return $this->entityManager->createQueryBuilder()
            ->select('q', 's')->from(Question::class, 'q')->join('q.section', 's')
            ->orderBy('s.position')->addOrderBy('s.id')->addOrderBy('q.position')->addOrderBy('q.id')
            ->getQuery()->getResult();
    }

    #[Override]
    public function question(string $id): ?Question
    {
        return $this->entityManager->find(Question::class, $id);
    }

    #[Override]
    public function questionByAnchor(string $anchor): ?Question
    {
        return $this->entityManager->getRepository(Question::class)->findOneBy(['anchor' => $anchor]);
    }

    #[Override]
    public function sectionTranslations(): array
    {
        /** @var list<SectionTranslation> $translations */
        $translations = $this->entityManager->createQueryBuilder()
            ->select('t')->from(SectionTranslation::class, 't')
            ->getQuery()->getResult();
        $byId = [];

        foreach ($translations as $translation) {
            $byId[$translation->section->id][$translation->locale] = $translation;
        }

        return $byId;
    }

    #[Override]
    public function questionTranslations(bool $publishedOnly): array
    {
        $query = $this->entityManager->createQueryBuilder()->select('t')->from(QuestionTranslation::class, 't');

        if ($publishedOnly) {
            $query->where('t.published = true');
        }

        /** @var list<QuestionTranslation> $translations */
        $translations = $query->getQuery()->getResult();
        $byId = [];

        foreach ($translations as $translation) {
            $byId[$translation->question->id][$translation->locale] = $translation;
        }

        return $byId;
    }

    #[Override]
    public function sectionTranslation(Section $section, string $locale): ?SectionTranslation
    {
        return $this->entityManager->getRepository(SectionTranslation::class)->findOneBy(['section' => $section->id, 'locale' => $locale]);
    }

    #[Override]
    public function questionTranslation(Question $question, string $locale): ?QuestionTranslation
    {
        return $this->entityManager->getRepository(QuestionTranslation::class)->findOneBy(['question' => $question->id, 'locale' => $locale]);
    }

    #[Override]
    public function save(Section|SectionTranslation|Question|QuestionTranslation ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->persist($object);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Section|Question $object): void
    {
        $questions = $object instanceof Section ? $this->questionsOf($object) : [$object];

        foreach ($questions as $question) {
            $this->removeAll(QuestionTranslation::class, 'question', $question->id);
            $this->entityManager->remove($question);
        }

        if ($object instanceof Section) {
            $this->removeAll(SectionTranslation::class, 'section', $object->id);
        }

        $this->entityManager->remove($object);
        $this->entityManager->flush();
    }

    #[Override]
    public function countVote(string $questionId, bool $helpful): ?VoteCount
    {
        $column = $helpful ? 'helpful_yes' : 'helpful_no';
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(\sprintf('UPDATE %s SET %s = %2$s + 1 WHERE id = ?', Question::TABLE, $column), [$questionId]);

        $row = $connection->fetchAssociative(\sprintf('SELECT helpful_yes, helpful_no FROM %s WHERE id = ?', Question::TABLE), [$questionId]);

        if (false === $row) {
            return null;
        }

        return new VoteCount($this->integer($row['helpful_yes']), $this->integer($row['helpful_no']));
    }

    #[Override]
    public function transactional(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $work());
    }

    /**
     * @param class-string<SectionTranslation|QuestionTranslation> $class
     */
    private function removeAll(string $class, string $field, string $id): void
    {
        foreach ($this->entityManager->getRepository($class)->findBy([$field => $id]) as $translation) {
            $this->entityManager->remove($translation);
        }
    }

    /**
     * A count as the driver gives it: a number, or a numeric string with some drivers.
     */
    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
