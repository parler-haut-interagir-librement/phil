<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;

/**
 * One launch of a cmi5 assignable unit: its session, whether the fetch URL gave its token already, until when the
 * token holds, and the state the statements moved the session to. The token is derived from the session and a secret
 * of the application, never kept. Moments are kept in UTC.
 */
final class LaunchSession
{
    public readonly DateTimeImmutable $launchedAt;

    public readonly DateTimeImmutable $expiresAt;

    public private(set) ?DateTimeImmutable $endedAt;

    public function __construct(
        public readonly LaunchSessionId $id,
        public readonly EnrollmentId $enrollment,
        public readonly ActivityId $activity,
        DateTimeImmutable $launchedAt,
        DateTimeImmutable $expiresAt,
        public private(set) bool $fetched = false,
        public private(set) SessionState $state = SessionState::Launched,
        ?DateTimeImmutable $endedAt = null,
    ) {
        $this->launchedAt = $launchedAt->setTimezone(new DateTimeZone('UTC'));
        $this->expiresAt = $expiresAt->setTimezone(new DateTimeZone('UTC'));
        $this->endedAt = $endedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Whether the token still opens the session: not ended, not expired.
     */
    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->state->isOpen() && $now < $this->expiresAt;
    }

    /**
     * Gives the token once: says whether this call is the first.
     */
    public function fetch(): bool
    {
        if ($this->fetched) {
            return false;
        }

        $this->fetched = true;

        return true;
    }

    public function initialize(): void
    {
        $this->state = SessionState::Initialized;
    }

    public function end(SessionState $state, DateTimeImmutable $at): void
    {
        $this->state = $state;
        $this->endedAt = $at->setTimezone(new DateTimeZone('UTC'));
    }
}
