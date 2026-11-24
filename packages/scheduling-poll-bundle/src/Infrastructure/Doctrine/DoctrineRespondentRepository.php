<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Override;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;

/**
 * The respondents in the table `phil_scheduling_poll_respondent`. A respondent is read again from its table each time;
 * every change is one `UPDATE` whose condition is the state it changes from: an answer only while the respondent is
 * not revoked and its poll is open, a notice only once, an erasure only once.
 */
final readonly class DoctrineRespondentRepository implements RespondentRepository
{
    private const string TABLE = Respondent::TABLE;

    private const string POLLS = SchedulingPoll::TABLE;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(Respondent $respondent): void
    {
        $this->entityManager->persist($respondent);
        $this->entityManager->flush();
    }

    #[Override]
    public function get(string $id): ?Respondent
    {
        return $this->one($this->select()->where('r.id = :id')->setParameter('id', $id));
    }

    #[Override]
    public function ofInvitation(string $invitation): ?Respondent
    {
        return $this->one($this->select()->where('r.invitation = :invitation')->setParameter('invitation', $invitation));
    }

    #[Override]
    public function ofPoll(string $poll): array
    {
        /** @var list<Respondent> */
        return $this->select()
            ->where('r.poll = :poll')
            ->orderBy('r.joinedAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->setParameter('poll', $poll)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    #[Override]
    public function count(string $poll): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Respondent::class, 'r')
            ->where('r.poll = :poll')
            ->setParameter('poll', $poll)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Override]
    public function answer(string $id, array $answers, ?string $timezone, DateTimeImmutable $now): bool
    {
        $answered = $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.self::TABLE.' SET answers = ?, timezone = ?, answered_at = ? WHERE id = ? AND revoked_at IS NULL'
            .' AND EXISTS (SELECT 1 FROM '.self::POLLS.' p WHERE p.id = '.self::TABLE.'.poll AND p.closed_at IS NULL AND p.deadline > ?)',
            [array_map(static fn (Availability $answer): string => $answer->value, $answers), $timezone, $now, $id, $now],
            [Types::JSON, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );

        return 1 === $answered;
    }

    #[Override]
    public function revoke(string $id, DateTimeImmutable $now): bool
    {
        return 1 === $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.self::TABLE.' SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL',
            [$now, $id],
            [Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING],
        );
    }

    #[Override]
    public function claimNotice(string $id, DateTimeImmutable $now): bool
    {
        return 1 === $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.self::TABLE.' SET notified_at = ? WHERE id = ? AND notified_at IS NULL',
            [$now, $id],
            [Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING],
        );
    }

    #[Override]
    public function releaseNotice(string $id): void
    {
        $this->entityManager->getConnection()->executeStatement('UPDATE '.self::TABLE.' SET notified_at = NULL WHERE id = ?', [$id], [ParameterType::STRING]);
    }

    #[Override]
    public function eraseClosedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $now): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.self::TABLE.' SET name = NULL, email = NULL, timezone = NULL, erased_at = ? WHERE erased_at IS NULL'
            .' AND poll IN (SELECT p.id FROM '.self::POLLS.' p WHERE p.closed_at < ?)',
            [$now, $cutoff],
            [Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function ofPerson(?ResourceReference $account, ?string $email): array
    {
        $query = $this->person($this->select(), $account, $email);

        if (!$query instanceof QueryBuilder) {
            return [];
        }

        /** @var list<Respondent> */
        return $query->orderBy('r.joinedAt', 'ASC')->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
    }

    #[Override]
    public function forgetPerson(?ResourceReference $account, ?string $email): int
    {
        $query = $this->person($this->entityManager->createQueryBuilder()->delete(Respondent::class, 'r'), $account, $email);

        if (!$query instanceof QueryBuilder) {
            return 0;
        }

        $this->entityManager->clear();

        $deleted = $query->getQuery()->execute();

        return \is_int($deleted) ? $deleted : 0;
    }

    private function select(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()->select('r')->from(Respondent::class, 'r');
    }

    private function one(QueryBuilder $query): ?Respondent
    {
        $respondent = $query->getQuery()->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();

        return $respondent instanceof Respondent ? $respondent : null;
    }

    private function person(QueryBuilder $query, ?ResourceReference $account, ?string $email): ?QueryBuilder
    {
        $conditions = [];

        if ($account instanceof ResourceReference) {
            $conditions[] = 'r.account = :account';
            $query->setParameter('account', $account->toString());
        }

        if (null !== $email && '' !== $email) {
            $conditions[] = 'LOWER(r.email) = :email';
            $query->setParameter('email', strtolower($email));
        }

        return [] === $conditions ? null : $query->where(implode(' OR ', $conditions));
    }
}
