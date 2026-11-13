<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use DateTimeImmutable;

/**
 * The events that share a fingerprint: the title and culprit of the first, a status, a count, the first and last
 * instants and releases seen, and the release that was the last one seen when the group was resolved.
 *
 * A resolved group seen again regresses when the event comes from another release than the one it was resolved on,
 * or when either release is unknown: an event of the release it was resolved on comes from code still running the
 * old way — a worker not restarted yet — and leaves it resolved. An ignored group keeps counting and stays ignored.
 */
final readonly class ErrorGroup
{
    public function __construct(
        public string $fingerprint,
        public string $title,
        public ?string $culprit,
        public GroupStatus $status,
        public int $timesSeen,
        public DateTimeImmutable $firstSeen,
        public DateTimeImmutable $lastSeen,
        public ?string $lastRelease = null,
        public ?string $resolvedRelease = null,
    ) {
    }

    public static function first(string $fingerprint, ErrorEvent $event): self
    {
        return new self($fingerprint, $event->title(), $event->culprit()?->label(), GroupStatus::Open, 1, $event->occurredAt, $event->occurredAt, $event->release);
    }

    public function seen(ErrorEvent $event): self
    {
        $regressed = GroupStatus::Resolved === $this->status && (null === $event->release || $event->release !== $this->resolvedRelease);

        return new self(
            $this->fingerprint,
            $this->title,
            $this->culprit,
            $regressed ? GroupStatus::Regressed : $this->status,
            $this->timesSeen + 1,
            $this->firstSeen,
            max($event->occurredAt, $this->lastSeen),
            $event->release ?? $this->lastRelease,
            $regressed ? null : $this->resolvedRelease,
        );
    }

    /**
     * Resolved on the last release seen.
     */
    public function resolve(): self
    {
        return $this->withStatus(GroupStatus::Resolved, $this->lastRelease);
    }

    public function ignore(): self
    {
        return $this->withStatus(GroupStatus::Ignored, null);
    }

    public function reopen(): self
    {
        return $this->withStatus(GroupStatus::Open, null);
    }

    private function withStatus(GroupStatus $status, ?string $resolvedRelease): self
    {
        return new self($this->fingerprint, $this->title, $this->culprit, $status, $this->timesSeen, $this->firstSeen, $this->lastSeen, $this->lastRelease, $resolvedRelease);
    }
}
