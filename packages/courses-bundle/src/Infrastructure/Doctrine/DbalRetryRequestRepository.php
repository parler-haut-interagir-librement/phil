<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Domain\Contract\RetryRequestRepository;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\Progress\RetryDecision;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;

/**
 * The requests for a second chance through DBAL.
 */
final readonly class DbalRetryRequestRepository implements RetryRequestRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(RetryRequest $request): void
    {
        $this->rows->put(CourseTables::RETRY, ['id' => $request->id->value], [
            'enrollment_id' => $request->enrollment->value,
            'activity_id' => $request->activity->value,
            'reason' => $request->reason,
            'decision' => $request->decision->value,
            'requested_at' => $request->requestedAt,
            'decided_at' => $request->decidedAt,
            'decided_by' => $request->decidedBy,
            'note' => '' === $request->note ? null : $request->note,
        ], ['requested_at' => Types::DATETIME_UTC_IMMUTABLE, 'decided_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function find(RetryRequestId $id): ?RetryRequest
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::RETRY.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->request($row);
    }

    #[Override]
    public function pending(EnrollmentId $enrollment, ActivityId $activity): ?RetryRequest
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM '.CourseTables::RETRY.' WHERE enrollment_id = ? AND activity_id = ? AND decision = ?',
            [$enrollment->value, $activity->value, RetryDecision::Pending->value],
        );

        return false === $row ? null : $this->request($row);
    }

    #[Override]
    public function waiting(int $limit = 50): array
    {
        return array_map($this->request(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::RETRY.' WHERE decision = ? ORDER BY requested_at, id LIMIT '.max(1, $limit),
            [RetryDecision::Pending->value],
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function request(array $row): RetryRequest
    {
        return new RetryRequest(
            RetryRequestId::of(Rows::text($row['id'])),
            EnrollmentId::of(Rows::text($row['enrollment_id'])),
            ActivityId::of(Rows::text($row['activity_id'])),
            Rows::text($row['reason']),
            $this->rows->requiredMoment($row['requested_at']),
            RetryDecision::tryFrom(Rows::text($row['decision'])) ?? RetryDecision::Pending,
            $this->rows->moment($row['decided_at']),
            Rows::nullableText($row['decided_by']),
            Rows::nullableText($row['note']),
        );
    }
}
