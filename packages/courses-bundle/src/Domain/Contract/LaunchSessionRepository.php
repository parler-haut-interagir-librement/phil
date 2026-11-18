<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;

/**
 * The cmi5 launch sessions.
 */
interface LaunchSessionRepository
{
    public function save(LaunchSession $session): void;

    public function find(LaunchSessionId $id): ?LaunchSession;

    /**
     * @return list<LaunchSession> the sessions of that enrollment and activity still open
     */
    public function open(EnrollmentId $enrollment, ActivityId $activity): array;
}
