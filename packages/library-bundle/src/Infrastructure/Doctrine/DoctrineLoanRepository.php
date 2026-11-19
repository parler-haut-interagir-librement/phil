<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\LibraryBundle\Domain\Contract\LoanRepository;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use SortDirection;

/**
 * The loans with Doctrine: the ORM for one loan and the short lists the services change, DBAL for the counts and the
 * bulk changes of the history. Moments are compared as `datetime_utc_immutable`, the type of their columns. `save()`
 * flushes the loan with the copy it claims in one transaction: the copy's version refuses a loan made meanwhile.
 */
final readonly class DoctrineLoanRepository implements LoanRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Loan
    {
        return $this->entityManager->find(Loan::class, $id);
    }

    #[Override]
    public function save(Loan $loan): void
    {
        $this->entityManager->persist($loan);
        $this->entityManager->flush();
    }

    #[Override]
    public function holding(Copy $copy): array
    {
        /** @var list<Loan> */
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Loan::class, 'l')
            ->where('l.copy = :copy')
            ->andWhere('l.status IN (:holding)')
            ->orderBy('l.startsAt', SortDirection::Ascending)
            ->addOrderBy('l.id', SortDirection::Ascending)
            ->setParameter('copy', $copy->id)
            ->setParameter('holding', $this->values(LoanStatus::holding()), ArrayParameterType::STRING)
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function activeOf(Copy $copy): ?Loan
    {
        return $this->entityManager->getRepository(Loan::class)->findOneBy(['copy' => $copy->id, 'status' => LoanStatus::Active]);
    }

    #[Override]
    public function ofMember(string $member, int $limit, bool $open = false): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('l', 'c', 't')
            ->from(Loan::class, 'l')
            ->join('l.copy', 'c')
            ->join('c.title', 't')
            ->where('l.member = :member')
            ->orderBy('l.startsAt', SortDirection::Descending)
            ->addOrderBy('l.id', SortDirection::Descending)
            ->setParameter('member', $member)
            ->setMaxResults($limit);

        if ($open) {
            $query->andWhere('l.status IN (:holding)')->setParameter('holding', $this->values(LoanStatus::holding()), ArrayParameterType::STRING);
        }

        /** @var list<Loan> */
        return $query->getQuery()->getResult();
    }

    #[Override]
    public function countOpen(string $member): int
    {
        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM '.Loan::TABLE.' WHERE member = ? AND status IN (?)',
            [$member, $this->values(LoanStatus::holding())],
            [Types::STRING, ArrayParameterType::STRING],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    #[Override]
    public function hasOverdue(string $member, DateTimeImmutable $now): bool
    {
        return false !== $this->entityManager->getConnection()->fetchOne(
            'SELECT 1 FROM '.Loan::TABLE.' WHERE member = ? AND status = ? AND due_at <= ?',
            [$member, LoanStatus::Active->value, $now],
            [Types::STRING, Types::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function dueBefore(DateTimeImmutable $moment, int $limit): array
    {
        /** @var list<Loan> */
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Loan::class, 'l')
            ->where('l.status = :active')
            ->andWhere('l.dueAt <= :moment')
            ->orderBy('l.dueAt', SortDirection::Ascending)
            ->addOrderBy('l.id', SortDirection::Ascending)
            ->setParameter('active', LoanStatus::Active->value)
            ->setParameter('moment', $moment, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function forgetMember(string $member): int
    {
        $changed = $this->entityManager->getConnection()->executeStatement('UPDATE '.Loan::TABLE.' SET member = NULL, version = version + 1 WHERE member = ?', [$member]);
        $this->detachLoans();

        return (int) $changed;
    }

    #[Override]
    public function forgetClosedBefore(DateTimeImmutable $moment): int
    {
        $changed = $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.Loan::TABLE.' SET member = NULL, version = version + 1 WHERE member IS NOT NULL AND status IN (?) AND closed_at < ?',
            [$this->values(LoanStatus::closed()), $moment],
            [ArrayParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );
        $this->detachLoans();

        return (int) $changed;
    }

    /**
     * Detaches the loans the entity manager holds, which a bulk change made stale, and nothing else.
     */
    private function detachLoans(): void
    {
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap()[Loan::class] ?? [] as $loan) {
            $this->entityManager->detach($loan);
        }
    }

    /**
     * @param list<LoanStatus> $statuses
     *
     * @return list<string>
     */
    private function values(array $statuses): array
    {
        return array_map(static fn (LoanStatus $status): string => $status->value, $statuses);
    }
}
