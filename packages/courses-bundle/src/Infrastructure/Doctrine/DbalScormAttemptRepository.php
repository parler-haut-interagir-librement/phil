<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\CoursesBundle\Domain\Contract\ScormAttemptRepository;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Runtime\ScormVersion;

/**
 * The SCORM attempts through DBAL: what the platform read of each in `phil_course_scorm_attempt`, its tracking data
 * as a JSON object of elements and values in `phil_course_scorm_data`, written in one transaction.
 */
final readonly class DbalScormAttemptRepository implements ScormAttemptRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(ScormAttempt $attempt): void
    {
        $this->connection->transactional(function () use ($attempt): void {
            $this->rows->put(CourseTables::SCORM_ATTEMPT, ['id' => $attempt->id->value], [
                'enrollment_id' => $attempt->enrollment->value,
                'activity_id' => $attempt->activity->value,
                'number' => $attempt->number,
                'version' => $attempt->version->value,
                'state' => $attempt->state->value,
                'session' => $attempt->session,
                'completion' => $attempt->status->completion,
                'success' => $attempt->status->success,
                'score' => $attempt->status->score,
                'total_time' => $attempt->totalTime->centiseconds,
                'started_at' => $attempt->startedAt,
                'updated_at' => $attempt->updatedAt,
                'ended_at' => $attempt->endedAt,
            ], [
                'number' => Types::INTEGER,
                'session' => Types::INTEGER,
                'score' => Types::FLOAT,
                'total_time' => Types::BIGINT,
                'started_at' => Types::DATETIME_UTC_IMMUTABLE,
                'updated_at' => Types::DATETIME_UTC_IMMUTABLE,
                'ended_at' => Types::DATETIME_UTC_IMMUTABLE,
            ]);
            $this->rows->put(CourseTables::SCORM_DATA, ['attempt_id' => $attempt->id->value], [
                'data' => json_encode((object) $attempt->data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                'updated_at' => $attempt->updatedAt,
            ], ['updated_at' => Types::DATETIME_UTC_IMMUTABLE]);
        });
    }

    #[Override]
    public function find(ScormAttemptId $id): ?ScormAttempt
    {
        $row = $this->connection->fetchAssociative($this->select().' WHERE a.id = ?', [$id->value]);

        return false === $row ? null : $this->attempt($row);
    }

    #[Override]
    public function latest(EnrollmentId $enrollment, ActivityId $activity): ?ScormAttempt
    {
        $row = $this->connection->fetchAssociative($this->select().' WHERE a.enrollment_id = ? AND a.activity_id = ? ORDER BY a.number DESC', [$enrollment->value, $activity->value]);

        return false === $row ? null : $this->attempt($row);
    }

    #[Override]
    public function ofEnrollments(EnrollmentId ...$enrollments): array
    {
        if ([] === $enrollments) {
            return [];
        }

        return array_map($this->attempt(...), $this->connection->fetchAllAssociative(
            $this->select().' WHERE a.enrollment_id IN (?) ORDER BY a.enrollment_id, a.activity_id, a.number',
            [array_map(static fn (EnrollmentId $id): string => $id->value, $enrollments)],
            [ArrayParameterType::STRING],
        ));
    }

    #[Override]
    public function removeOfEnrollments(EnrollmentId ...$enrollments): void
    {
        if ([] === $enrollments) {
            return;
        }

        $ids = array_map(static fn (EnrollmentId $id): string => $id->value, $enrollments);
        $this->connection->transactional(function () use ($ids): void {
            $this->connection->executeStatement(
                'DELETE FROM '.CourseTables::SCORM_DATA.' WHERE attempt_id IN (SELECT id FROM '.CourseTables::SCORM_ATTEMPT.' WHERE enrollment_id IN (?))',
                [$ids],
                [ArrayParameterType::STRING],
            );
            $this->connection->executeStatement('DELETE FROM '.CourseTables::SCORM_ATTEMPT.' WHERE enrollment_id IN (?)', [$ids], [ArrayParameterType::STRING]);
        });
    }

    private function select(): string
    {
        return 'SELECT a.*, d.data FROM '.CourseTables::SCORM_ATTEMPT.' a LEFT JOIN '.CourseTables::SCORM_DATA.' d ON d.attempt_id = a.id';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function attempt(array $row): ScormAttempt
    {
        return new ScormAttempt(
            ScormAttemptId::of(Rows::text($row['id'])),
            EnrollmentId::of(Rows::text($row['enrollment_id'])),
            ActivityId::of(Rows::text($row['activity_id'])),
            max(1, Rows::int($row['number'])),
            ScormVersion::tryFrom(Rows::text($row['version'])) ?? ScormVersion::Scorm2004,
            $this->rows->requiredMoment($row['started_at']),
            AttemptState::tryFrom(Rows::text($row['state'])) ?? AttemptState::Ended,
            max(0, Rows::int($row['session'])),
            $this->data($row['data'] ?? null),
            new RuntimeStatus(Rows::text($row['completion']), Rows::text($row['success']), Rows::nullableFloat($row['score'])),
            Duration::ofCentiseconds(Rows::int($row['total_time'])),
            $this->rows->requiredMoment($row['updated_at']),
            $this->rows->moment($row['ended_at']),
        );
    }

    /**
     * @return array<string, string>
     */
    private function data(mixed $json): array
    {
        try {
            $data = json_decode(Rows::text($json), true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return \is_array($data) ? array_filter($data, static fn (mixed $value, int|string $key): bool => \is_string($key) && \is_string($value), \ARRAY_FILTER_USE_BOTH) : [];
    }
}
