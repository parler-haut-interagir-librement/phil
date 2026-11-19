<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;

/**
 * The cmi5 launch sessions through DBAL.
 */
final readonly class DbalLaunchSessionRepository implements LaunchSessionRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(LaunchSession $session): void
    {
        $this->rows->put(CourseTables::SESSION, ['id' => $session->id->value], [
            'enrollment_id' => $session->enrollment->value,
            'activity_id' => $session->activity->value,
            'fetched' => $session->fetched,
            'state' => $session->state->value,
            'launched_at' => $session->launchedAt,
            'expires_at' => $session->expiresAt,
            'ended_at' => $session->endedAt,
        ], [
            'fetched' => Types::BOOLEAN,
            'launched_at' => Types::DATETIME_UTC_IMMUTABLE,
            'expires_at' => Types::DATETIME_UTC_IMMUTABLE,
            'ended_at' => Types::DATETIME_UTC_IMMUTABLE,
        ]);
    }

    #[Override]
    public function find(LaunchSessionId $id): ?LaunchSession
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::SESSION.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->session($row);
    }

    #[Override]
    public function open(EnrollmentId $enrollment, ActivityId $activity): array
    {
        return array_map($this->session(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::SESSION.' WHERE enrollment_id = ? AND activity_id = ? AND state IN (?) ORDER BY launched_at, id',
            [$enrollment->value, $activity->value, [SessionState::Launched->value, SessionState::Initialized->value]],
            [2 => ArrayParameterType::STRING],
        ));
    }

    #[Override]
    public function ofEnrollments(EnrollmentId ...$enrollments): array
    {
        if ([] === $enrollments) {
            return [];
        }

        return array_map($this->session(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::SESSION.' WHERE enrollment_id IN (?) ORDER BY launched_at, id',
            [$this->ids($enrollments)],
            [ArrayParameterType::STRING],
        ));
    }

    #[Override]
    public function removeOfEnrollments(EnrollmentId ...$enrollments): void
    {
        if ([] !== $enrollments) {
            $this->connection->executeStatement('DELETE FROM '.CourseTables::SESSION.' WHERE enrollment_id IN (?)', [$this->ids($enrollments)], [ArrayParameterType::STRING]);
        }
    }

    /**
     * @param array<EnrollmentId> $enrollments
     *
     * @return list<string>
     */
    private function ids(array $enrollments): array
    {
        return array_values(array_map(static fn (EnrollmentId $id): string => $id->value, $enrollments));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function session(array $row): LaunchSession
    {
        return new LaunchSession(
            LaunchSessionId::of(Rows::text($row['id'])),
            EnrollmentId::of(Rows::text($row['enrollment_id'])),
            ActivityId::of(Rows::text($row['activity_id'])),
            $this->rows->requiredMoment($row['launched_at']),
            $this->rows->requiredMoment($row['expires_at']),
            Rows::bool($row['fetched']),
            SessionState::tryFrom(Rows::text($row['state'])) ?? SessionState::Abandoned,
            $this->rows->moment($row['ended_at']),
        );
    }
}
