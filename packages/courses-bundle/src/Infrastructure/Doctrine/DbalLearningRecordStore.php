<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Psr\Clock\ClockInterface;

/**
 * The learning record store inside the bundle, through DBAL: the statements by identifier — a statement sent twice
 * with the same identifier is kept once, as xAPI asks —, and the state documents by activity, registration and
 * identifier, the activity's IRI kept as its SHA-256 so that the key stays bounded.
 */
final readonly class DbalLearningRecordStore implements LearningRecordStore
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function store(string $statementId, string $statement, string $registration): void
    {
        try {
            $this->connection->insert(CourseTables::STATEMENT, [
                'id' => $statementId,
                'registration' => $registration,
                'body' => $statement,
                'stored_at' => $this->clock->now(),
            ], ['stored_at' => Types::DATETIME_UTC_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            // The statement is kept already: xAPI keeps the first one of an identifier.
        }
    }

    #[Override]
    public function state(string $activityId, string $registration, string $stateId): ?string
    {
        $document = $this->connection->fetchOne(
            'SELECT document FROM '.CourseTables::STATE.' WHERE activity_hash = ? AND registration = ? AND state_id = ?',
            [hash('sha256', $activityId), $registration, $stateId],
        );

        return false === $document ? null : Rows::text($document);
    }

    #[Override]
    public function saveState(string $activityId, string $registration, string $stateId, string $document): void
    {
        $this->rows->put(
            CourseTables::STATE,
            ['activity_hash' => hash('sha256', $activityId), 'registration' => $registration, 'state_id' => $stateId],
            ['document' => $document, 'updated_at' => $this->clock->now()],
            ['updated_at' => Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function deleteState(string $activityId, string $registration, string $stateId): void
    {
        $this->connection->delete(CourseTables::STATE, ['activity_hash' => hash('sha256', $activityId), 'registration' => $registration, 'state_id' => $stateId]);
    }
}
