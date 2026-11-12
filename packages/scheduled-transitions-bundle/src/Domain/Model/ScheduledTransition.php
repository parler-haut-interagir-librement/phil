<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Domain\Exception\InvalidSchedule;
use Phil\ScheduledTransitionsBundle\Domain\Transition\TransitionStatus;
use Symfony\Component\Uid\Uuid;

/**
 * A transition to apply to a resource at a moment — `publish` a page on Monday at nine, `unpublish` it a month later —,
 * its attempts and their outcome. Times are kept in UTC.
 *
 * A run claims a due transition before applying it, until a moment kept in `claimed_until`: the repository writes it,
 * in one statement that only one run can win; the entity only reads it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_scheduled_transition')]
#[ORM\Index(columns: ['status', 'due_at'])]
#[ORM\Index(columns: ['resource_kind', 'resource_id'])]
final class ScheduledTransition
{
    private const string UTC = 'UTC';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'resource_kind', length: 64)]
        private string $resourceKind,
        #[ORM\Column(name: 'resource_id', length: 255)]
        private string $resourceId,
        #[ORM\Column(name: 'transition', length: 64)]
        private string $transition,
        #[ORM\Column(name: 'due_at')]
        private DateTimeImmutable $dueAt,
        #[ORM\Column(name: 'status', length: 16, enumType: TransitionStatus::class)]
        private TransitionStatus $status,
        #[ORM\Column(name: 'attempts')]
        private int $attempts,
        #[ORM\Column(name: 'last_error', length: 255, nullable: true)]
        private ?string $lastError,
        #[ORM\Column(name: 'applied_at', nullable: true)]
        private ?DateTimeImmutable $appliedAt,
        #[ORM\Column(name: 'claimed_until', nullable: true)]
        private ?DateTimeImmutable $claimedUntil = null,
    ) {
    }

    public static function of(ResourceReference $resource, string $transition, DateTimeImmutable $dueAt): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $transition)) {
            throw InvalidSchedule::because(\sprintf('A transition is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $transition));
        }

        return new self(Uuid::v7()->toRfc4122(), $resource->key, $resource->id, $transition, self::utc($dueAt), TransitionStatus::Pending, 0, null, null);
    }

    public function resource(): ResourceReference
    {
        return ResourceReference::of($this->resourceKind, $this->resourceId);
    }

    public function transition(): string
    {
        return $this->transition;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->read($this->dueAt);
    }

    public function status(): TransitionStatus
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function appliedAt(): ?DateTimeImmutable
    {
        return $this->appliedAt instanceof DateTimeImmutable ? $this->read($this->appliedAt) : null;
    }

    /**
     * Until when a run holds the transition, as it was loaded; `null` when none does.
     */
    public function claimedUntil(): ?DateTimeImmutable
    {
        return $this->claimedUntil instanceof DateTimeImmutable ? $this->read($this->claimedUntil) : null;
    }

    public function isDueAt(DateTimeImmutable $now): bool
    {
        return TransitionStatus::Pending === $this->status && $this->dueAt() <= $now;
    }

    /**
     * Moves a pending transition to another moment.
     */
    public function reschedule(DateTimeImmutable $dueAt): void
    {
        $this->assertPending();
        $this->dueAt = self::utc($dueAt);
    }

    public function cancel(): void
    {
        $this->assertPending();
        $this->status = TransitionStatus::Cancelled;
    }

    public function applied(DateTimeImmutable $at): void
    {
        $this->assertPending();
        ++$this->attempts;
        $this->status = TransitionStatus::Done;
        $this->appliedAt = self::utc($at);
        $this->lastError = null;
    }

    /**
     * Records a failed attempt: the transition stays pending until its attempts are spent.
     */
    public function failed(string $error, int $maxAttempts): void
    {
        $this->assertPending();
        ++$this->attempts;
        $this->lastError = mb_substr($error, 0, 255);

        if ($this->attempts >= $maxAttempts) {
            $this->status = TransitionStatus::Failed;
        }
    }

    private function assertPending(): void
    {
        if (TransitionStatus::Pending !== $this->status) {
            throw InvalidSchedule::because(\sprintf('The transition "%s" of %s is %s, no longer pending.', $this->transition, $this->resource()->toString(), $this->status->value));
        }
    }

    private static function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone(self::UTC));
    }

    /**
     * Doctrine reads a `datetime_immutable` in the server's time zone: the stored wall clock is UTC.
     */
    private function read(DateTimeImmutable $stored): DateTimeImmutable
    {
        return new DateTimeImmutable($stored->format('Y-m-d H:i:s'), new DateTimeZone(self::UTC));
    }
}
