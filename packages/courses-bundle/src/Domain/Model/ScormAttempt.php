<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\SessionEnd;

/**
 * An attempt of a learner at a SCORM activity: its number among the learner's attempts at the activity, the version of
 * SCORM, where it stands, the session that may commit to it — each launch opens the next, so that the page of an
 * earlier launch commits no more —, the tracking data the content wrote, by element, and what the platform read of it:
 * the completion, the success, the scaled score and the total time. The time the platform measured itself — the seconds
 * its sessions ran, by the server's clock, whatever the content reports — bounds an attempt under a time limit.
 * Moments are kept in UTC.
 */
final class ScormAttempt
{
    public private(set) Duration $totalTime;

    public private(set) DateTimeImmutable $startedAt;

    public private(set) DateTimeImmutable $updatedAt;

    public private(set) ?DateTimeImmutable $endedAt;

    /** When the running session opened, by the server's clock; null before the first. */
    public private(set) ?DateTimeImmutable $sessionOpenedAt;

    /**
     * @param int<1, max>           $number
     * @param int<0, max>           $session
     * @param array<string, string> $data
     * @param int<0, max>           $elapsed the seconds the sessions ended so far ran, by the server's clock
     */
    public function __construct(
        public readonly ScormAttemptId $id,
        public readonly EnrollmentId $enrollment,
        public readonly ActivityId $activity,
        public readonly int $number,
        public readonly ScormVersion $version,
        DateTimeImmutable $startedAt,
        public private(set) AttemptState $state = AttemptState::Running,
        public private(set) int $session = 0,
        public private(set) array $data = [],
        public private(set) RuntimeStatus $status = new RuntimeStatus(),
        ?Duration $totalTime = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $endedAt = null,
        public private(set) int $elapsed = 0,
        ?DateTimeImmutable $sessionOpenedAt = null,
    ) {
        $this->totalTime = $totalTime ?? Duration::zero();
        $this->startedAt = $startedAt->setTimezone(new DateTimeZone('UTC'));
        $this->updatedAt = ($updatedAt ?? $startedAt)->setTimezone(new DateTimeZone('UTC'));
        $this->endedAt = $endedAt?->setTimezone(new DateTimeZone('UTC'));
        $this->sessionOpenedAt = $sessionOpenedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Opens the next session, with the data the platform set for it, and gives its number.
     *
     * @param array<string, string> $data
     */
    public function open(array $data, DateTimeImmutable $at): int
    {
        $this->state = AttemptState::Running;
        $this->data = $data;
        $this->updatedAt = $at->setTimezone(new DateTimeZone('UTC'));
        $this->sessionOpenedAt = $this->updatedAt;

        return ++$this->session;
    }

    /**
     * The seconds the attempt ran by the server's clock until that moment: the sessions ended, and the running one
     * since it opened.
     *
     * @return int<0, max>
     */
    public function spent(DateTimeImmutable $at): int
    {
        return $this->elapsed + (AttemptState::Running === $this->state ? $this->since($at) : 0);
    }

    /**
     * Whether a session may commit: the attempt's running session.
     */
    public function accepts(int $session): bool
    {
        return AttemptState::Running === $this->state && $session === $this->session;
    }

    /**
     * Keeps a commit of the running session.
     *
     * @param array<string, string> $data
     */
    public function commit(array $data, RuntimeStatus $status, DateTimeImmutable $at): void
    {
        $this->data = $data;
        $this->status = $status;
        $this->updatedAt = $at->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Ends the running session: suspended, ended, or paused until the next.
     *
     * @param array<string, string> $data
     */
    public function end(array $data, SessionEnd $end, DateTimeImmutable $at): void
    {
        if (AttemptState::Running === $this->state) {
            $this->elapsed += $this->since($at);
        }

        $this->commit($data, $end->status, $at);
        $this->totalTime = $end->totalTime;
        $this->state = match (true) {
            $end->suspended => AttemptState::Suspended,
            $end->attemptEnded => AttemptState::Ended,
            default => AttemptState::Paused,
        };

        if (AttemptState::Ended === $this->state) {
            $this->endedAt = $at->setTimezone(new DateTimeZone('UTC'));
        }
    }

    /**
     * Ends an attempt between two sessions, its data as it is: the time allowed is spent.
     */
    public function expire(DateTimeImmutable $at): void
    {
        $this->state = AttemptState::Ended;
        $this->updatedAt = $at->setTimezone(new DateTimeZone('UTC'));
        $this->endedAt = $this->updatedAt;
    }

    /**
     * @return int<0, max>
     */
    private function since(DateTimeImmutable $at): int
    {
        return $this->sessionOpenedAt instanceof DateTimeImmutable ? max(0, $at->getTimestamp() - $this->sessionOpenedAt->getTimestamp()) : 0;
    }
}
