<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Events and their hosts with Doctrine. Times are stored in UTC: a period is asked for in UTC too.
 */
final readonly class DoctrineEventRepository implements EventRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Event $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Event $event): void
    {
        $this->entityManager->remove($event);
        $this->entityManager->flush();
    }

    #[Override]
    public function get(string $id): ?Event
    {
        return $this->entityManager->find(Event::class, $id);
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(Event::class)->findBy([], ['startsAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function hostsOf(Event $event): array
    {
        return $this->entityManager->getRepository(EventHost::class)->findBy(['event' => $event], ['kind' => 'ASC', 'reference' => 'ASC']);
    }

    #[Override]
    public function hostsOfEach(array $events): array
    {
        $hosts = array_fill_keys(array_map(static fn (Event $event): string => $event->id, $events), []);

        // No query for no event.
        if ([] !== $events) {
            foreach ($this->entityManager->getRepository(EventHost::class)->findBy(['event' => $events], ['kind' => 'ASC', 'reference' => 'ASC']) as $host) {
                $hosts[$host->event->id][] = $host;
            }
        }

        return $hosts;
    }

    #[Override]
    public function saveHost(EventHost $host): void
    {
        $this->entityManager->persist($host);
        $this->entityManager->flush();
    }

    #[Override]
    public function removeHost(EventHost $host): void
    {
        $this->entityManager->remove($host);
        $this->entityManager->flush();
    }

    #[Override]
    public function proposed(int $limit = 100): array
    {
        /** @var list<Event> */
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.proposalState = :proposed')
            ->orderBy('e.startsAt')
            ->setParameter('proposed', Event::PROPOSED)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function overlapping(DateTimeInterface $from, DateTimeInterface $through, ?string $hostKind = null, ?string $hostReference = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('e', 'v')
            ->from(Event::class, 'e')
            ->leftJoin('e.venue', 'v')
            ->where('e.startsAt <= :through')
            ->andWhere('e.seriesEndsAt IS NULL OR e.seriesEndsAt > :from')
            ->andWhere('e.proposalState = :published')
            ->orderBy('e.startsAt')
            ->setParameter('published', Event::PUBLISHED)
            ->setParameter('through', $this->utc($through))
            ->setParameter('from', $this->utc($from));

        if (null !== $hostKind && null !== $hostReference) {
            $query->andWhere(\sprintf('EXISTS (SELECT h.id FROM %s h WHERE h.event = e AND h.kind = :kind AND h.reference = :reference)', EventHost::class))
                ->setParameter('kind', $hostKind)
                ->setParameter('reference', $hostReference);
        }

        /** @var list<Event> */
        return $query->getQuery()->getResult();
    }

    /**
     * The moment's wall clock in UTC, without time zone: as the columns keep times.
     */
    private function utc(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    #[Override]
    public function proposalsOf(Member $member, int $limit): array
    {
        /** @var list<Event> */
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.proposedBy = :member')
            ->orderBy('e.startsAt')
            ->setParameter('member', $member->toString())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function forgetProposer(Member $member): int
    {
        return (int) $this->entityManager->getConnection()->update('phil_calendar_event', ['proposed_by' => null], ['proposed_by' => $member->toString()]);
    }
}
