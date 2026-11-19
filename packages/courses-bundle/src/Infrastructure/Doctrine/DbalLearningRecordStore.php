<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
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

    /**
     * The statements of the registrations, as they were kept, in the order they were stored.
     *
     * @param list<string> $registrations
     *
     * @return list<array{id: string, registration: string, stored_at: DateTimeImmutable, statement: string}>
     */
    public function statementsOf(array $registrations): array
    {
        if ([] === $registrations) {
            return [];
        }

        return array_map(fn (array $row): array => [
            'id' => Rows::text($row['id']),
            'registration' => Rows::text($row['registration']),
            'stored_at' => $this->rows->requiredMoment($row['stored_at']),
            'statement' => Rows::text($row['body']),
        ], $this->connection->fetchAllAssociative(
            'SELECT id, registration, body, stored_at FROM '.CourseTables::STATEMENT.' WHERE registration IN (?) ORDER BY stored_at, id',
            [$registrations],
            [ArrayParameterType::STRING],
        ));
    }

    /**
     * The state documents of the registrations, the activity named by the SHA-256 of its IRI.
     *
     * @param list<string> $registrations
     *
     * @return list<array{registration: string, activity_sha256: string, state_id: string, updated_at: DateTimeImmutable, document: string}>
     */
    public function statesOf(array $registrations): array
    {
        if ([] === $registrations) {
            return [];
        }

        return array_map(fn (array $row): array => [
            'registration' => Rows::text($row['registration']),
            'activity_sha256' => Rows::text($row['activity_hash']),
            'state_id' => Rows::text($row['state_id']),
            'updated_at' => $this->rows->requiredMoment($row['updated_at']),
            'document' => Rows::text($row['document']),
        ], $this->connection->fetchAllAssociative(
            'SELECT registration, activity_hash, state_id, document, updated_at FROM '.CourseTables::STATE.' WHERE registration IN (?) ORDER BY registration, activity_hash, state_id',
            [$registrations],
            [ArrayParameterType::STRING],
        ));
    }

    /**
     * Removes the statements and the state documents of the registrations.
     *
     * @param list<string> $registrations
     */
    public function erase(array $registrations): void
    {
        if ([] === $registrations) {
            return;
        }

        $this->connection->transactional(function () use ($registrations): void {
            $this->connection->executeStatement('DELETE FROM '.CourseTables::STATEMENT.' WHERE registration IN (?)', [$registrations], [ArrayParameterType::STRING]);
            $this->connection->executeStatement('DELETE FROM '.CourseTables::STATE.' WHERE registration IN (?)', [$registrations], [ArrayParameterType::STRING]);
        });
    }
}
