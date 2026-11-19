<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Model;

use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\Reservation\Availability\AlwaysAvailable;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\ReminderSchedule;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MaximumAdvance;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;
use Phil\ReservationBundle\Domain\Exception\InvalidReservation;
use Symfony\Component\Uid\Uuid;

/**
 * Something that can be booked — a room, a table, a person's time, a piece of equipment, a bed — with its name, its
 * capacity, its buffers, its hours, its rules, its reminders and how long a hold keeps it. `bookable()` gives the
 * `phil/reservation` resource that decides its bookings.
 *
 * Each value is checked as it is set, by the library's own types: a value the library refuses throws its
 * `InvalidDefinition`. `claims` counts the requests that locked its row, one at a time.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class BookableResource
{
    public const string TABLE = 'phil_reservation_resource';

    #[ORM\Column(name: 'buffer_before')]
    private int $bufferBefore = 0;

    #[ORM\Column(name: 'buffer_after')]
    private int $bufferAfter = 0;

    #[ORM\Column(name: 'minimum_notice', nullable: true)]
    private ?int $minimumNotice = null;

    #[ORM\Column(name: 'maximum_advance', nullable: true)]
    private ?int $maximumAdvance = null;

    #[ORM\Column(name: 'minimum_duration', nullable: true)]
    private ?int $minimumDuration = null;

    #[ORM\Column(name: 'maximum_duration', nullable: true)]
    private ?int $maximumDuration = null;

    #[ORM\Column(name: 'slot_step', nullable: true)]
    private ?int $slotStep = null;

    /** @var ?array<int, list<array{int, int}>> by ISO weekday; `null` when it is always open */
    #[ORM\Column(name: 'hours', type: 'json', nullable: true)]
    private ?array $hours = null;

    /** @var list<string> */
    #[ORM\Column(name: 'closed_days', type: 'json')]
    private array $closedDays = [];

    /** @var list<int> */
    #[ORM\Column(name: 'reminders', type: 'json')]
    private array $reminders = [];

    #[ORM\Column(name: 'hold_minutes')]
    private int $holdMinutes = 15;

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    #[ORM\Column(name: 'claims')]
    private int $claims = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'name', length: 200)]
        private string $name,
        #[ORM\Column(name: 'capacity')]
        private int $capacity,
        #[ORM\Column(name: 'timezone', length: 64)]
        private string $timezone,
    ) {
    }

    /**
     * @param string $timezone an IANA identifier: its hours and its grid are read on that clock
     *
     * @throws InvalidReservation when the name or the time zone is not one
     * @throws InvalidDefinition  when the capacity is below one
     */
    public static function create(string $name, int $capacity = 1, string $timezone = 'UTC'): self
    {
        new Bookable($capacity);

        return new self(Uuid::v7()->toRfc4122(), self::checkedName($name), $capacity, self::checkedZone($timezone));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = self::checkedName($name);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function resize(int $capacity): void
    {
        new Bookable($capacity);
        $this->capacity = $capacity;
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    public function moveTo(string $timezone): void
    {
        $this->timezone = self::checkedZone($timezone);
    }

    public function bufferBefore(): int
    {
        return $this->bufferBefore;
    }

    public function bufferAfter(): int
    {
        return $this->bufferAfter;
    }

    /**
     * The minutes held before and after each booking.
     */
    public function buffer(int $before, int $after): void
    {
        new Bookable($this->capacity, $before, $after);
        $this->bufferBefore = $before;
        $this->bufferAfter = $after;
    }

    /**
     * The rules every booking follows, in minutes; `null` leaves a rule out.
     */
    public function restrict(?int $minimumNotice = null, ?int $maximumAdvance = null, ?int $minimumDuration = null, ?int $maximumDuration = null, ?int $slotStep = null): void
    {
        $this->rules($minimumNotice, $maximumAdvance, $minimumDuration, $maximumDuration, $slotStep);
        $this->minimumNotice = $minimumNotice;
        $this->maximumAdvance = $maximumAdvance;
        $this->minimumDuration = $minimumDuration;
        $this->maximumDuration = $maximumDuration;
        $this->slotStep = $slotStep;
    }

    public function minimumNotice(): ?int
    {
        return $this->minimumNotice;
    }

    public function maximumAdvance(): ?int
    {
        return $this->maximumAdvance;
    }

    public function minimumDuration(): ?int
    {
        return $this->minimumDuration;
    }

    public function maximumDuration(): ?int
    {
        return $this->maximumDuration;
    }

    public function slotStep(): ?int
    {
        return $this->slotStep;
    }

    /**
     * Its weekly hours, as `WeeklyAvailability` reads them, and its days closed; without hours, it is always open and
     * the days closed are dropped.
     *
     * @param ?array<int, list<array{int, int}>> $hours
     * @param list<string>                       $closedDays as `Y-m-d`
     */
    public function openOn(?array $hours, array $closedDays = []): void
    {
        if (null === $hours) {
            $this->hours = null;
            $this->closedDays = [];

            return;
        }

        $this->hours = new WeeklyAvailability($hours, $this->timezone(), $closedDays)->hours;
        $this->closedDays = array_values(array_unique($closedDays));
    }

    /**
     * @return ?array<int, list<array{int, int}>>
     */
    public function hours(): ?array
    {
        return $this->hours;
    }

    /**
     * @return list<string>
     */
    public function closedDays(): array
    {
        return $this->closedDays;
    }

    /**
     * The minutes before its start each booking's holder is reminded.
     */
    public function remindBefore(int ...$offsets): void
    {
        $this->reminders = new ReminderSchedule(...$offsets)->offsets;
    }

    /**
     * @return list<int> the furthest first
     */
    public function reminders(): array
    {
        return $this->reminders;
    }

    public function holdMinutes(): int
    {
        return $this->holdMinutes;
    }

    /**
     * How long a hold keeps its units before it expires unconfirmed: from 1 minute to a day.
     *
     * @throws InvalidReservation when it is out of bounds
     */
    public function holdFor(int $minutes): void
    {
        if ($minutes < 1 || $minutes > 1440) {
            throw InvalidReservation::because(\sprintf('A hold lasts from 1 to 1440 minutes, not %d.', $minutes));
        }

        $this->holdMinutes = $minutes;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Whether it takes new reservations; those already made stay.
     */
    public function activate(bool $active = true): void
    {
        $this->active = $active;
    }

    /**
     * How many requests locked its row.
     */
    public function claims(): int
    {
        return $this->claims;
    }

    /**
     * The resource as `phil/reservation` decides its bookings.
     */
    public function bookable(): Bookable
    {
        $zone = $this->timezone();

        return new Bookable(
            $this->capacity,
            $this->bufferBefore,
            $this->bufferAfter,
            null === $this->hours ? new AlwaysAvailable() : new WeeklyAvailability($this->hours, $zone, $this->closedDays),
            $this->rules($this->minimumNotice, $this->maximumAdvance, $this->minimumDuration, $this->maximumDuration, $this->slotStep),
            $zone,
        );
    }

    public function reminderSchedule(): ReminderSchedule
    {
        return new ReminderSchedule(...$this->reminders);
    }

    /**
     * @return list<MinimumNotice|MaximumAdvance|DurationLimits|SlotGrid>
     */
    private function rules(?int $minimumNotice, ?int $maximumAdvance, ?int $minimumDuration, ?int $maximumDuration, ?int $slotStep): array
    {
        $rules = [];

        if (null !== $minimumNotice) {
            $rules[] = new MinimumNotice($minimumNotice);
        }

        if (null !== $maximumAdvance) {
            $rules[] = new MaximumAdvance($maximumAdvance);
        }

        if (null !== $minimumDuration || null !== $maximumDuration) {
            $rules[] = new DurationLimits($minimumDuration ?? 1, $maximumDuration);
        }

        if (null !== $slotStep) {
            $rules[] = new SlotGrid($slotStep);
        }

        return $rules;
    }

    private static function checkedName(string $name): string
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 200) {
            throw InvalidReservation::because('The name of a resource has from 1 to 200 characters.');
        }

        return $name;
    }

    private static function checkedZone(string $timezone): string
    {
        if (!\in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidReservation::because(\sprintf('The time zone of a resource is an IANA identifier, not "%s".', mb_substr($timezone, 0, 64)));
        }

        return $timezone;
    }
}
