<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\Repository\CalendarChoiceRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * The choices of members' calendars with Doctrine; a member's are counted and forgotten with DBAL.
 */
final readonly class DoctrineCalendarChoiceRepository implements CalendarChoiceRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(CalendarChoice $choice): void
    {
        $this->entityManager->persist($choice);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(CalendarChoice $choice): void
    {
        $this->entityManager->remove($choice);
        $this->entityManager->flush();
    }

    #[Override]
    public function of(Member $member, ChoiceSource $source, string $value): ?CalendarChoice
    {
        return $this->entityManager->getRepository(CalendarChoice::class)->findOneBy(['member' => $member->toString(), 'source' => $source->value, 'value' => $value]);
    }

    #[Override]
    public function choicesOf(Member $member, int $limit): array
    {
        /** @var list<CalendarChoice> */
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(CalendarChoice::class, 'c')
            ->where('c.member = :member')
            ->orderBy('c.chosenAt')
            ->addOrderBy('c.id')
            ->setParameter('member', $member->toString())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function count(Member $member): int
    {
        $count = $this->entityManager->getConnection()->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE member = ?', CalendarChoice::TABLE),
            [$member->toString()],
            [ParameterType::STRING],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    #[Override]
    public function forgetMember(Member $member): void
    {
        $this->entityManager->getConnection()->delete(CalendarChoice::TABLE, ['member' => $member->toString()]);
    }
}
