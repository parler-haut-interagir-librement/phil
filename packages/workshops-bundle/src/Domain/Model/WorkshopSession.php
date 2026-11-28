<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\Reservation\Interval;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\SessionStatus;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * One time a workshop is given: its span on a clock of its own, the seats it holds and the window in which it takes
 * registrations. Registration opens at once when the session has no opening moment, and closes when it starts when it
 * has no closing moment.
 *
 * Moments are kept in UTC by their columns' type, `datetime_utc_immutable`, and given back on the session's clock.
 * `claims` counts the requests that locked its row, one at a time.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_workshops_session_start', columns: ['workshop', 'starts_at'])]
final class WorkshopSession
{
    public const string TABLE = 'phil_workshops_session';

    /** The most seats a session holds. */
    public const int MAX_CAPACITY = 100_000;

    #[ORM\Column(name: 'opens_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $opensAt = null;

    #[ORM\Column(name: 'closes_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $closesAt = null;

    #[ORM\Column(name: 'status', length: 16, enumType: SessionStatus::class)]
    private SessionStatus $status = SessionStatus::Scheduled;

    #[ORM\Column(name: 'claims')]
    private int $claims = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Workshop::class)]
        #[ORM\JoinColumn(name: 'workshop', nullable: false, onDelete: 'CASCADE')]
        private Workshop $workshop,
        #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $endsAt,
        #[ORM\Column(name: 'timezone', length: 64)]
        private string $timezone,
        #[ORM\Column(name: 'capacity')]
        private int $capacity,
    ) {
    }

    /**
     * @throws InvalidWorkshop when the workshop is inactive, the time zone unknown or the capacity out of bounds
     */
    public static function schedule(Workshop $workshop, Interval $span, string $timezone, int $capacity): self
    {
        if (!$workshop->isActive()) {
            throw InvalidWorkshop::because('An inactive workshop takes no new session.');
        }

        return new self(Uuid::v7()->toRfc4122(), $workshop, $span->start, $span->end, self::zone($timezone)->getName(), self::checkedCapacity($capacity));
    }

    public function workshop(): Workshop
    {
        return $this->workshop;
    }

    /**
     * Its span, on its clock.
     */
    public function span(): Interval
    {
        return new Interval($this->local($this->startsAt), $this->local($this->endsAt));
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function status(): SessionStatus
    {
        return $this->status;
    }

    public function opensAt(): ?DateTimeImmutable
    {
        return $this->opensAt instanceof DateTimeImmutable ? $this->local($this->opensAt) : null;
    }

    public function closesAt(): ?DateTimeImmutable
    {
        return $this->closesAt instanceof DateTimeImmutable ? $this->local($this->closesAt) : null;
    }

    /**
     * When registration closes: its closing moment, or the start of the session.
     */
    public function closing(): DateTimeImmutable
    {
        return $this->local($this->closesAt ?? $this->startsAt);
    }

    /**
     * Why the session refuses a registration at a moment, whatever its seats; `null` when it takes one.
     */
    public function refusalAt(DateTimeImmutable $now): ?RefusalReason
    {
        return match (true) {
            SessionStatus::Cancelled === $this->status => RefusalReason::Cancelled,
            $this->opensAt instanceof DateTimeImmutable && $now->getTimestamp() < $this->opensAt->getTimestamp() => RefusalReason::NotOpen,
            $now->getTimestamp() >= $this->closing()->getTimestamp() => RefusalReason::Closed,
            default => null,
        };
    }

    /**
     * The window in which it takes registrations. It closes after it opens, and at the latest when the session ends.
     *
     * @throws InvalidWorkshop when the window is empty or closes after the session ends
     */
    public function openRegistration(?DateTimeImmutable $opensAt, ?DateTimeImmutable $closesAt): void
    {
        $this->assertWindow($opensAt, $closesAt, $this->startsAt, $this->endsAt);
        $this->opensAt = $opensAt;
        $this->closesAt = $closesAt;
    }

    /**
     * Moved to another span; its registration window must still fit.
     *
     * @throws InvalidWorkshop when the session is cancelled, or its window no longer fits
     */
    public function reschedule(Interval $span): void
    {
        $this->assertScheduled('moved');
        $this->assertWindow($this->opensAt, $this->closesAt, $span->start, $span->end);
        $this->startsAt = $span->start;
        $this->endsAt = $span->end;
    }

    /**
     * The seats it holds. Whoever lowers it checks first that the seats taken still fit.
     *
     * @throws InvalidWorkshop when the session is cancelled, or the capacity out of bounds
     */
    public function resize(int $capacity): void
    {
        $this->assertScheduled('resized');
        $this->capacity = self::checkedCapacity($capacity);
    }

    /**
     * @throws InvalidWorkshop when it is already cancelled
     */
    public function cancel(): void
    {
        $this->assertScheduled('cancelled');
        $this->status = SessionStatus::Cancelled;
    }

    private function assertWindow(?DateTimeImmutable $opensAt, ?DateTimeImmutable $closesAt, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): void
    {
        $closing = $closesAt ?? $startsAt;

        if ($closesAt instanceof DateTimeImmutable && $closesAt->getTimestamp() > $endsAt->getTimestamp()) {
            throw InvalidWorkshop::because('Registration closes at the latest when the session ends.');
        }

        if ($opensAt instanceof DateTimeImmutable && $opensAt->getTimestamp() >= $closing->getTimestamp()) {
            throw InvalidWorkshop::because('Registration opens before it closes.');
        }
    }

    private function assertScheduled(string $change): void
    {
        if (SessionStatus::Scheduled !== $this->status) {
            throw InvalidWorkshop::because(\sprintf('A cancelled session is not %s.', $change));
        }
    }

    private function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->timezone());
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Throwable) {
            // The column holds 64 characters and no time zone name is longer: what PHP refuses covers the empty and
            // the overlong ones too.
            throw InvalidWorkshop::because(\sprintf('"%s" is not a time zone.', mb_substr($timezone, 0, 64)));
        }
    }

    private static function checkedCapacity(int $capacity): int
    {
        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            throw InvalidWorkshop::because(\sprintf('A session holds from 1 to %d seats.', self::MAX_CAPACITY));
        }

        return $capacity;
    }
}
