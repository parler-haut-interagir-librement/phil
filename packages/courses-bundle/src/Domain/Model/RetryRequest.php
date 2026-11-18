<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Progress\RetryDecision;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;

/**
 * A learner's request for a second chance at an activity they failed every try of: why they ask, and who granted it,
 * refused it or waived the activity instead, when and why. A request is decided once.
 */
final class RetryRequest
{
    public const int REASON_LENGTH = 2000;

    public readonly DateTimeImmutable $requestedAt;

    public readonly string $reason;

    /** Why the teacher decided as they did, as they wrote it; empty when they wrote nothing. */
    public private(set) string $note;

    public private(set) ?DateTimeImmutable $decidedAt;

    /**
     * @throws InvalidCourse when the reason is too long
     */
    public function __construct(
        public readonly RetryRequestId $id,
        public readonly EnrollmentId $enrollment,
        public readonly ActivityId $activity,
        string $reason,
        DateTimeImmutable $requestedAt,
        public private(set) RetryDecision $decision = RetryDecision::Pending,
        ?DateTimeImmutable $decidedAt = null,
        public private(set) ?string $decidedBy = null,
        ?string $note = null,
    ) {
        $reason = trim($reason);

        if (mb_strlen($reason) > self::REASON_LENGTH) {
            throw InvalidCourse::because(\sprintf('The reason of a request is %d characters at most.', self::REASON_LENGTH));
        }

        $this->reason = $reason;
        $this->note = $this->checkedNote($note ?? '');
        $this->requestedAt = $requestedAt->setTimezone(new DateTimeZone('UTC'));
        $this->decidedAt = $decidedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    public function isPending(): bool
    {
        return RetryDecision::Pending === $this->decision;
    }

    /**
     * @param string $by   the stable identifier of who decided
     * @param string $note why, as the teacher writes it
     *
     * @throws InvalidCourse when the note is too long
     */
    public function decide(RetryDecision $decision, string $by, DateTimeImmutable $at, string $note = ''): void
    {
        if (!$this->isPending()) {
            throw new LogicException(\sprintf('The request "%s" is decided already.', $this->id->value));
        }

        if (RetryDecision::Pending === $decision) {
            throw new LogicException('A request is decided by a grant, a refusal or a waiver.');
        }

        $this->note = $this->checkedNote($note);
        $this->decision = $decision;
        $this->decidedBy = $by;
        $this->decidedAt = $at->setTimezone(new DateTimeZone('UTC'));
    }

    private function checkedNote(string $note): string
    {
        $note = trim($note);

        if (mb_strlen($note) > self::REASON_LENGTH) {
            throw InvalidCourse::because(\sprintf('The note of a decision is %d characters at most.', self::REASON_LENGTH));
        }

        return $note;
    }
}
