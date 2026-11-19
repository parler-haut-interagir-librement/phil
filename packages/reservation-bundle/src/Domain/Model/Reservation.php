<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Reservation\Booking;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Exception\InvalidReservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Units of a resource booked for a span: held until a moment, then confirmed or expired; confirmed until cancelled.
 * Its holder is an identifier the application gives — an account, an order, a session —, never a name or an address:
 * a holder that looks like an e-mail address is refused, since an address changes and identifies a person.
 *
 * Moments are kept in UTC by their columns' type, `datetime_utc_immutable`, and given back on the resource's clock.
 * `lastReminder` is the offset of the last reminder sent, so that each is sent once.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_reservation_period', columns: ['resource', 'starts_at', 'ends_at'])]
#[ORM\Index(name: 'phil_reservation_state', columns: ['status', 'starts_at'])]
final class Reservation
{
    public const string TABLE = 'phil_reservation';

    /** What an e-mail address looks like: something, an at sign, a domain with a dot. */
    private const string EMAIL = '/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/D';

    #[ORM\Column(name: 'hold_expires_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $holdExpiresAt = null;

    #[ORM\Column(name: 'last_reminder', nullable: true)]
    private ?int $lastReminder = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: BookableResource::class)]
        #[ORM\JoinColumn(name: 'resource', nullable: false, onDelete: 'CASCADE')]
        private BookableResource $resource,
        #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $endsAt,
        #[ORM\Column(name: 'quantity')]
        private int $quantity,
        #[ORM\Column(name: 'holder', length: 255, nullable: true)]
        private ?string $holder,
        #[ORM\Column(name: 'status', length: 16, enumType: ReservationStatus::class)]
        private ReservationStatus $status,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Units kept for the resource's hold time from now, until confirmed.
     *
     * @throws InvalidDefinition  when the quantity is below one
     * @throws InvalidReservation when the holder is not an identifier
     */
    public static function hold(BookableResource $resource, Interval $interval, int $quantity, ?string $holder, DateTimeImmutable $now): self
    {
        $reservation = self::make($resource, $interval, $quantity, $holder, ReservationStatus::Held, $now);
        $reservation->holdExpiresAt = $now->modify(\sprintf('+%d minutes', $resource->holdMinutes()));

        return $reservation;
    }

    /**
     * Units booked at once, without a hold.
     *
     * @throws InvalidDefinition  when the quantity is below one
     * @throws InvalidReservation when the holder is not an identifier
     */
    public static function confirmed(BookableResource $resource, Interval $interval, int $quantity, ?string $holder, DateTimeImmutable $now): self
    {
        return self::make($resource, $interval, $quantity, $holder, ReservationStatus::Confirmed, $now);
    }

    public function resource(): BookableResource
    {
        return $this->resource;
    }

    /**
     * Its span, on the resource's clock.
     */
    public function interval(): Interval
    {
        return new Interval($this->local($this->startsAt), $this->local($this->endsAt));
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function holder(): ?string
    {
        return $this->holder;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function holdExpiresAt(): ?DateTimeImmutable
    {
        return $this->holdExpiresAt instanceof DateTimeImmutable ? $this->local($this->holdExpiresAt) : null;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->local($this->createdAt);
    }

    public function lastReminder(): ?int
    {
        return $this->lastReminder;
    }

    /**
     * Whether it keeps its units at a moment: confirmed, or held and not expired.
     */
    public function holdsUnits(DateTimeImmutable $now): bool
    {
        return ReservationStatus::Confirmed === $this->status
            || (ReservationStatus::Held === $this->status && $this->holdExpiresAt instanceof DateTimeImmutable && $now->getTimestamp() < $this->holdExpiresAt->getTimestamp());
    }

    /**
     * What `phil/reservation` checks a request against.
     */
    public function booking(): Booking
    {
        return new Booking($this->id, $this->interval(), $this->quantity);
    }

    /**
     * Confirmed from a hold, expired or not: whoever confirms an expired hold checks it again first.
     *
     * @throws InvalidReservation when it is not a hold
     */
    public function confirm(): void
    {
        if (ReservationStatus::Held !== $this->status && ReservationStatus::Expired !== $this->status) {
            throw InvalidReservation::because(\sprintf('Only a hold is confirmed, not a reservation %s.', $this->status->value));
        }

        $this->status = ReservationStatus::Confirmed;
        $this->holdExpiresAt = null;
    }

    /**
     * @throws InvalidReservation when it is already cancelled or expired
     */
    public function cancel(): void
    {
        $this->assertLive('cancelled');
        $this->status = ReservationStatus::Cancelled;
        $this->holdExpiresAt = null;
    }

    /**
     * Expires a hold whose moment has come; anything else is left as it is.
     *
     * @return bool whether it expired
     */
    public function expire(DateTimeImmutable $now): bool
    {
        if ($this->holdsUnits($now) || ReservationStatus::Held !== $this->status) {
            return false;
        }

        $this->status = ReservationStatus::Expired;

        return true;
    }

    /**
     * Moved or resized: the reminders start over.
     *
     * @throws InvalidReservation when it is cancelled or expired
     * @throws InvalidDefinition  when the quantity is below one
     */
    public function reschedule(Interval $interval, int $quantity): void
    {
        $this->assertLive('moved');
        new Booking($this->id, $interval, $quantity);
        $this->startsAt = $interval->start;
        $this->endsAt = $interval->end;
        $this->quantity = $quantity;
        $this->lastReminder = null;
    }

    /**
     * Records the reminder sent, by its offset.
     */
    public function remind(int $offset): void
    {
        $this->lastReminder = $offset;
    }

    private static function make(BookableResource $resource, Interval $interval, int $quantity, ?string $holder, ReservationStatus $status, DateTimeImmutable $now): self
    {
        $id = Uuid::v7()->toRfc4122();
        new Booking($id, $interval, $quantity);

        if (null !== $holder && ('' === $holder || \strlen($holder) > 255)) {
            throw InvalidReservation::because('The holder of a reservation is an identifier of 1 to 255 bytes.');
        }

        if (null !== $holder && 1 === preg_match(self::EMAIL, $holder)) {
            throw InvalidReservation::because('The holder of a reservation is a stable identifier, never an e-mail address.');
        }

        return new self($id, $resource, $interval->start, $interval->end, $quantity, $holder, $status, $now);
    }

    private function assertLive(string $change): void
    {
        if (ReservationStatus::Held !== $this->status && ReservationStatus::Confirmed !== $this->status) {
            throw InvalidReservation::because(\sprintf('A reservation %s is not %s.', $this->status->value, $change));
        }
    }

    private function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->resource->timezone());
    }
}
