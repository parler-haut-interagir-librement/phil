<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;

/**
 * The requests for a second chance.
 */
interface RetryRequestRepository
{
    public function save(RetryRequest $request): void;

    public function find(RetryRequestId $id): ?RetryRequest;

    /**
     * The request of that enrollment for that activity still waiting for a decision, if any.
     */
    public function pending(EnrollmentId $enrollment, ActivityId $activity): ?RetryRequest;

    /**
     * @return list<RetryRequest> the requests waiting for a decision, the oldest first
     */
    public function waiting(int $limit = 50): array;
}
