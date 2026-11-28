<?php

declare(strict_types=1);

namespace Phil\Shifts\Roster;

use DateTimeImmutable;
use Phil\Shifts\Allocation\InterestAllocator;
use Phil\Shifts\Event\InterestExpressed;
use Phil\Shifts\Event\PlaceTransferred;
use Phil\Shifts\Event\ReservePromoted;
use Phil\Shifts\Event\ShiftEvent;
use Phil\Shifts\Event\VolunteerConfirmed;
use Phil\Shifts\Event\VolunteerReserved;
use Phil\Shifts\Event\VolunteerWithdrew;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Identifier;
use Phil\Shifts\Shift\Shift;

/**
 * The volunteers of one shift: those who hold a place, the reserve in the order it filled, and the interested. A
 * roster is immutable: each operation returns the next one, with the events of that change in `$changes`, or throws a
 * ShiftRefused with its reason.
 *
 * A volunteer stands once on a roster. The places never exceed the capacity, the reserve never exceeds its size, and
 * the interested are 1,000 at most. Once the shift is closed — a fixed shift started, the window of a task or a
 * duration over —, nobody joins or leaves it.
 */
final readonly class Roster
{
    public const int MAX_INTERESTED = 1000;

    /**
     * @param list<Place>      $places  in the order they were taken
     * @param list<ShiftEvent> $changes what the operation that returned this roster changed
     *
     * @throws InvalidShift when the places hold a volunteer twice or exceed the bounds of the shift
     */
    public function __construct(
        public Shift $shift,
        public array $places = [],
        public array $changes = [],
    ) {
        $seen = [];
        $count = [PlaceStatus::Confirmed->value => 0, PlaceStatus::Reserve->value => 0, PlaceStatus::Interested->value => 0];

        foreach ($places as $place) {
            if (isset($seen[$place->volunteer])) {
                throw InvalidShift::because('A volunteer stands once on a roster.');
            }

            $seen[$place->volunteer] = $place->status;
            ++$count[$place->status->value];
        }

        if ($count[PlaceStatus::Confirmed->value] > $shift->capacity || $count[PlaceStatus::Reserve->value] > $shift->reserve || $count[PlaceStatus::Interested->value] > self::MAX_INTERESTED) {
            throw InvalidShift::because('A roster holds no more places, reserve or interested than its shift allows.');
        }
    }

    public static function open(Shift $shift): self
    {
        return new self($shift);
    }

    /**
     * A shift corrected by a coordinator, with the same volunteers: a capacity or a reserve smaller than those who
     * stand in it is refused.
     *
     * @throws InvalidShift when the shift is another one, or too small for its volunteers
     */
    public function withShift(Shift $shift): self
    {
        if ($shift->id !== $this->shift->id) {
            throw InvalidShift::because('A roster keeps the identifier of its shift.');
        }

        return new self($shift, $this->places);
    }

    /**
     * A volunteer takes a free place of a shift open to sign-ups, or waits in its reserve when it is full.
     *
     * @param list<string> $roles the roles of the volunteer
     *
     * @throws ShiftRefused when the shift is not open to sign-ups, closed, requires a role, is full, or holds the
     *                      volunteer already
     */
    public function signUp(string $volunteer, array $roles, DateTimeImmutable $now): self
    {
        $this->assertJoinable($volunteer, $roles, AssignmentMode::SelfSignUp, $now);

        if ($this->placesLeft() > 0) {
            return $this->adding(new Place($volunteer, PlaceStatus::Confirmed, $now), new VolunteerConfirmed($this->shift->id, $volunteer));
        }

        if (\count($this->reserve()) < $this->shift->reserve) {
            return $this->adding(new Place($volunteer, PlaceStatus::Reserve, $now), new VolunteerReserved($this->shift->id, $volunteer));
        }

        throw ShiftRefused::because(ShiftRefused::FULL);
    }

    /**
     * A volunteer says they are interested in a shift whose places are allocated.
     *
     * @param list<string> $roles the roles of the volunteer
     *
     * @throws ShiftRefused when the places of the shift are not allocated, the shift is closed, requires a role, holds
     *                      the volunteer already or has 1,000 interested
     */
    public function expressInterest(string $volunteer, array $roles, DateTimeImmutable $now): self
    {
        $this->assertJoinable($volunteer, $roles, AssignmentMode::Interest, $now);

        if (\count($this->interested()) >= self::MAX_INTERESTED) {
            throw ShiftRefused::because(ShiftRefused::FULL);
        }

        return $this->adding(new Place($volunteer, PlaceStatus::Interested, $now), new InterestExpressed($this->shift->id, $volunteer));
    }

    /**
     * A coordinator gives a place, in every mode and whatever the roles: a volunteer of the reserve or of the
     * interested moves to a place.
     *
     * @throws ShiftRefused when the shift is closed or full, or the volunteer holds a place already
     */
    public function assign(string $volunteer, string $coordinator, DateTimeImmutable $now): self
    {
        Identifier::volunteer($volunteer);
        Identifier::volunteer($coordinator);
        $this->assertOpen($now);
        $current = $this->statusOf($volunteer);

        if (PlaceStatus::Confirmed === $current) {
            throw ShiftRefused::because(ShiftRefused::ALREADY_ON_ROSTER);
        }

        if ($this->placesLeft() < 1) {
            throw ShiftRefused::because(ShiftRefused::FULL);
        }

        return new self($this->shift, [...$this->without($volunteer), new Place($volunteer, PlaceStatus::Confirmed, $now, $coordinator)], [new VolunteerConfirmed($this->shift->id, $volunteer, $coordinator)]);
    }

    /**
     * A volunteer leaves the shift, or a coordinator takes them off it; a place freed goes to the first volunteer of
     * the reserve.
     *
     * @throws ShiftRefused when the shift is closed or the volunteer is not on it
     */
    public function withdraw(string $volunteer, DateTimeImmutable $now): self
    {
        $this->assertOpen($now);
        $status = $this->statusOf($volunteer) ?? throw ShiftRefused::because(ShiftRefused::NOT_ON_ROSTER);
        $places = $this->without($volunteer);
        $changes = [new VolunteerWithdrew($this->shift->id, $volunteer)];

        if (PlaceStatus::Confirmed === $status) {
            foreach ($places as $index => $place) {
                if (PlaceStatus::Reserve === $place->status) {
                    unset($places[$index]);
                    $places[] = $place->with(PlaceStatus::Confirmed, $now);
                    $changes[] = new ReservePromoted($this->shift->id, $place->volunteer);

                    break;
                }
            }
        }

        return new self($this->shift, array_values($places), $changes);
    }

    /**
     * The free places allocated among the interested, as the allocator chooses; those it does not choose stay
     * interested.
     *
     * @param array<string, int> $minutesHeld the minutes each volunteer already holds elsewhere, which an allocator
     *                                        may balance
     *
     * @throws ShiftRefused when the places of the shift are not allocated, or the shift is closed
     */
    public function allocate(InterestAllocator $allocator, array $minutesHeld, DateTimeImmutable $now): self
    {
        if (AssignmentMode::Interest !== $this->shift->mode) {
            throw ShiftRefused::because(ShiftRefused::WRONG_MODE);
        }

        $this->assertOpen($now);
        $free = $this->placesLeft();
        $interested = $this->interested();

        if ($free < 1 || [] === $interested) {
            return new self($this->shift, $this->places);
        }

        $candidates = array_map(static fn (Place $place): string => $place->volunteer, $interested);
        $chosen = \array_slice(array_unique(array_intersect($allocator->choose($interested, $free, $minutesHeld), $candidates)), 0, $free);
        $taken = array_flip($chosen);
        $places = array_values(array_filter($this->places, static fn (Place $place): bool => !isset($taken[$place->volunteer])));
        $changes = [];

        foreach ($chosen as $volunteer) {
            $places[] = new Place($volunteer, PlaceStatus::Confirmed, $now);
            $changes[] = new VolunteerConfirmed($this->shift->id, $volunteer);
        }

        return new self($this->shift, $places, $changes);
    }

    /**
     * The place of one volunteer passed to another through the swap board; the one who takes it leaves the reserve or
     * the interested if they stood there.
     *
     * @throws ShiftRefused when the shift is closed, the first volunteer holds no place, or the second holds one
     */
    public function transfer(string $from, string $to, DateTimeImmutable $now): self
    {
        Identifier::volunteer($to);
        $this->assertOpen($now);

        if (PlaceStatus::Confirmed !== $this->statusOf($from)) {
            throw ShiftRefused::because(ShiftRefused::NOT_CONFIRMED);
        }

        if (PlaceStatus::Confirmed === $this->statusOf($to)) {
            throw ShiftRefused::because(ShiftRefused::ALREADY_ON_ROSTER);
        }

        $places = array_values(array_filter($this->places, static fn (Place $place): bool => $place->volunteer !== $from && $place->volunteer !== $to));
        $places[] = new Place($to, PlaceStatus::Confirmed, $now);

        return new self($this->shift, $places, [new PlaceTransferred($this->shift->id, $to, $from)]);
    }

    public function statusOf(string $volunteer): ?PlaceStatus
    {
        foreach ($this->places as $place) {
            if ($place->volunteer === $volunteer) {
                return $place->status;
            }
        }

        return null;
    }

    /**
     * @return list<Place>
     */
    public function confirmed(): array
    {
        return $this->of(PlaceStatus::Confirmed);
    }

    /**
     * @return list<Place> in the order they will take the places that free
     */
    public function reserve(): array
    {
        return $this->of(PlaceStatus::Reserve);
    }

    /**
     * @return list<Place> in the order they expressed their interest
     */
    public function interested(): array
    {
        return $this->of(PlaceStatus::Interested);
    }

    public function placesLeft(): int
    {
        return $this->shift->capacity - \count($this->confirmed());
    }

    /**
     * Whether a critical shift still lacks volunteers, which a coordinator's screen shows first.
     */
    public function isUnderstaffed(): bool
    {
        return $this->shift->critical && $this->placesLeft() > 0;
    }

    /**
     * @param list<string> $roles
     */
    private function assertJoinable(string $volunteer, array $roles, AssignmentMode $mode, DateTimeImmutable $now): void
    {
        Identifier::volunteer($volunteer);

        if ($mode !== $this->shift->mode) {
            throw ShiftRefused::because(ShiftRefused::WRONG_MODE);
        }

        $this->assertOpen($now);

        if (!$this->shift->admits($roles)) {
            throw ShiftRefused::because(ShiftRefused::ROLE_REQUIRED);
        }

        if ($this->statusOf($volunteer) instanceof PlaceStatus) {
            throw ShiftRefused::because(ShiftRefused::ALREADY_ON_ROSTER);
        }
    }

    private function assertOpen(DateTimeImmutable $now): void
    {
        if (!$this->shift->isOpenAt($now)) {
            throw ShiftRefused::because(ShiftRefused::CLOSED);
        }
    }

    private function adding(Place $place, ShiftEvent $event): self
    {
        return new self($this->shift, [...$this->places, $place], [$event]);
    }

    /**
     * @return array<int, Place> in the order of the places, with the keys they had
     */
    private function without(string $volunteer): array
    {
        return array_filter($this->places, static fn (Place $place): bool => $place->volunteer !== $volunteer);
    }

    /**
     * @return list<Place>
     */
    private function of(PlaceStatus $status): array
    {
        return array_values(array_filter($this->places, static fn (Place $place): bool => $place->status === $status));
    }
}
