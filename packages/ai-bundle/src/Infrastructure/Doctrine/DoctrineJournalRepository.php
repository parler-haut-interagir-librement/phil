<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\JournalTotals;
use Phil\AiBundle\Domain\Journal\Outcome;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Subject;

/**
 * The journal in the table `phil_ai_journal`, through the entity manager; each save and erasure is flushed. The totals
 * are one grouped query: the database sums, whatever the number of entries.
 */
final readonly class DoctrineJournalRepository implements JournalRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?JournalEntry
    {
        return $this->entityManager->find(JournalEntry::class, $id);
    }

    #[Override]
    public function save(JournalEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    #[Override]
    public function ofSubject(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): array
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('subject', $subject->value))
            ->andWhere(Criteria::expr()->gte('startedAt', $from))
            ->andWhere(Criteria::expr()->lt('startedAt', $before))
            ->orderBy(['startedAt' => Order::Ascending, 'id' => Order::Ascending]);

        return [...$this->entityManager->getRepository(JournalEntry::class)->matching($criteria)];
    }

    #[Override]
    public function totals(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): JournalTotals
    {
        /** @var list<array{outcome: Outcome, decision: Decision, currency: string, tasks: int|numeric-string, input: int|numeric-string|null, output: int|numeric-string|null, cost: int|numeric-string|null}> $groups */
        $groups = $this->entityManager->createQueryBuilder()
            ->select('e.outcome AS outcome', 'e.decision AS decision', 'e.currency AS currency', 'COUNT(e.id) AS tasks', 'SUM(e.inputTokens) AS input', 'SUM(e.outputTokens) AS output', 'SUM(e.costMicros) AS cost')
            ->from(JournalEntry::class, 'e')
            ->where('e.subject = :subject', 'e.startedAt >= :from', 'e.startedAt < :before')
            ->groupBy('e.outcome', 'e.decision', 'e.currency')
            ->setParameter('subject', $subject->value)
            ->setParameter('from', $from)
            ->setParameter('before', $before)
            ->getQuery()
            ->getArrayResult();

        return JournalTotals::fromGroups(array_map(static fn (array $group): array => ['outcome' => $group['outcome']->value, 'decision' => $group['decision']->value] + $group, $groups));
    }

    #[Override]
    public function eraseSubject(Subject $subject): int
    {
        $entries = $this->entityManager->getRepository(JournalEntry::class)->findBy(['subject' => $subject->value]);

        foreach ($entries as $entry) {
            $this->entityManager->remove($entry);
        }

        $this->entityManager->flush();

        return \count($entries);
    }
}
