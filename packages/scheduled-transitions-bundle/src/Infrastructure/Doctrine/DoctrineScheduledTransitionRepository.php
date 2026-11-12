<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;
use Phil\ScheduledTransitionsBundle\Domain\Repository\ScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Domain\Transition\TransitionStatus;

/**
 * The scheduled transitions with Doctrine. Times are stored in UTC: the moment of a run is asked for in UTC too.
 *
 * A claim is an `UPDATE` that only one run can win. An outcome is saved by one plain `UPDATE` on the connection, which
 * also lets go of the claim, whether or not a failed flush has closed the entity manager; the transition is then
 * detached, so that no later flush of the run compares it again: a run of a batch costs one statement per transition,
 * not one flush over every transition loaded.
 */
final readonly class DoctrineScheduledTransitionRepository implements ScheduledTransitionRepository
{
    private const string TABLE = 'phil_scheduled_transition';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(ScheduledTransition ...$transitions): void
    {
        foreach ($transitions as $transition) {
            $this->entityManager->persist($transition);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function pendingFor(ResourceReference $resource): array
    {
        return $this->entityManager->getRepository(ScheduledTransition::class)->findBy(['resourceKind' => $resource->key, 'resourceId' => $resource->id, 'status' => TransitionStatus::Pending], ['dueAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<ScheduledTransition> */
        return $this->entityManager->createQueryBuilder()
            ->select('transition')
            ->from(ScheduledTransition::class, 'transition')
            ->where('transition.status = :pending')
            ->andWhere('transition.dueAt <= :now')
            ->andWhere('transition.claimedUntil IS NULL OR transition.claimedUntil < :now')
            ->setParameter('pending', TransitionStatus::Pending->value)
            ->setParameter('now', $this->utc($now))
            ->orderBy('transition.dueAt', 'ASC')
            ->addOrderBy('transition.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function claim(ScheduledTransition $transition, DateTimeImmutable $now, DateTimeImmutable $until): bool
    {
        return 1 === $this->entityManager->getConnection()->executeStatement(
            \sprintf('UPDATE %s SET claimed_until = ? WHERE id = ? AND status = ? AND (claimed_until IS NULL OR claimed_until < ?)', self::TABLE),
            [$this->utc($until), $transition->id, TransitionStatus::Pending->value, $this->utc($now)],
        );
    }

    #[Override]
    public function record(ScheduledTransition $transition): void
    {
        $appliedAt = $transition->appliedAt();
        $this->entityManager->getConnection()->executeStatement(
            \sprintf('UPDATE %s SET status = ?, attempts = ?, last_error = ?, applied_at = ?, claimed_until = NULL WHERE id = ?', self::TABLE),
            [$transition->status()->value, $transition->attempts(), $transition->lastError(), $appliedAt instanceof DateTimeImmutable ? $this->utc($appliedAt) : null, $transition->id],
        );

        // The row now holds the outcome: left in the unit of work, the transition would be compared again, and written
        // again, by every later flush of the run.
        $this->entityManager->detach($transition);
    }

    #[Override]
    public function isUsable(): bool
    {
        return $this->entityManager->isOpen();
    }

    private function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
