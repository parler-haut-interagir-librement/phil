<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\Resource\ResourceReference;
use Throwable;

/**
 * The polls in the table `phil_poll`, through the entity manager; each save and removal is flushed. The revision of
 * the ballots is read and raised in the table itself, by one statement.
 *
 * A ballot is written in a transaction that first raises the revision with an `UPDATE` whose `WHERE` holds the
 * openness of the poll, and goes on only when it changed the row. The statement locks the poll's row until the commit:
 * a closing committed before it leaves the row out, as PostgreSQL and MySQL read the row as it is now for an `UPDATE`;
 * a closing that comes after it waits for the ballot's commit. SQLite lets one writer at a time in the whole database.
 * The transaction is the connection's, not `wrapInTransaction()`'s: a refusal raised before anything is flushed, a
 * ballot already cast, leaves the entity manager open.
 */
final readonly class DoctrinePollRepository implements PollRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Poll
    {
        return $this->entityManager->find(Poll::class, $id);
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->entityManager->getRepository(Poll::class)->findBy(['subject' => $subject->toString()], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function save(Poll $poll): void
    {
        $this->entityManager->persist($poll);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Poll $poll): void
    {
        $this->entityManager->remove($poll);
        $this->entityManager->flush();
    }

    #[Override]
    public function revision(string $id): int
    {
        $revision = $this->entityManager->getConnection()->fetchOne('SELECT revision FROM '.Poll::TABLE.' WHERE id = ?', [$id]);

        return is_numeric($revision) ? (int) $revision : 0;
    }

    #[Override]
    public function raiseRevision(string $id): void
    {
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Poll::TABLE.' SET revision = revision + 1 WHERE id = ?', [$id]);
    }

    #[Override]
    public function raiseRevisionWhileOpen(string $id, DateTimeImmutable $at, callable $write): bool
    {
        // The columns hold the dates in UTC, to the second, as the poll writes them.
        $at = new DateTimeImmutable('@'.$at->getTimestamp());
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $raised = $connection->executeStatement(
                'UPDATE '.Poll::TABLE.' SET revision = revision + 1 WHERE id = ? AND closed_at IS NULL AND (opens_at IS NULL OR opens_at <= ?) AND (closes_at IS NULL OR closes_at > ?)',
                [$id, $at, $at],
                [ParameterType::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE],
            );

            if (1 === $raised) {
                $write();
            }

            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return 1 === $raised;
    }
}
