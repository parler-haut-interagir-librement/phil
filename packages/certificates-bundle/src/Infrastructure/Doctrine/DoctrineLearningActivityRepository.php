<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CertificatesBundle\Domain\Contract\LearningActivityRepository;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;

/**
 * The activities of continuing education through Doctrine; `save()` and `remove()` flush, and the credits are summed by
 * the database.
 */
final readonly class DoctrineLearningActivityRepository implements LearningActivityRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(LearningActivity $activity): void
    {
        $this->entityManager->persist($activity);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(LearningActivity $activity): void
    {
        $this->entityManager->remove($activity);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(string $id): ?LearningActivity
    {
        return $this->entityManager->find(LearningActivity::class, $id);
    }

    #[Override]
    public function findBySource(MemberId $member, string $source): ?LearningActivity
    {
        return $this->entityManager->getRepository(LearningActivity::class)->findOneBy(['member' => $member->value, 'source' => $source]);
    }

    #[Override]
    public function of(MemberId $member, Period $period, int $limit, int $offset = 0): array
    {
        /** @var list<LearningActivity> */
        return $this->entityManager->createQuery('SELECT a FROM '.LearningActivity::class.' a WHERE a.member = :member AND a.completedAt >= :from AND a.completedAt < :until ORDER BY a.completedAt DESC, a.id DESC')
            ->setParameter('member', $member->value)
            ->setParameter('from', $period->from, 'datetime_immutable')
            ->setParameter('until', $period->until, 'datetime_immutable')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getResult();
    }

    #[Override]
    public function total(MemberId $member, Period $period, ?Course $toward = null): Credits
    {
        $query = $this->entityManager->createQuery('SELECT COALESCE(SUM(a.hundredths), 0) FROM '.LearningActivity::class.' a WHERE a.member = :member AND a.completedAt >= :from AND a.completedAt < :until'.($toward instanceof Course ? ' AND (a.course IS NULL OR a.course = :course)' : ''))
            ->setParameter('member', $member->value)
            ->setParameter('from', $period->from, 'datetime_immutable')
            ->setParameter('until', $period->until, 'datetime_immutable');

        if ($toward instanceof Course) {
            $query->setParameter('course', $toward);
        }

        return Credits::fromHundredths((int) $query->getSingleScalarResult());
    }
}
