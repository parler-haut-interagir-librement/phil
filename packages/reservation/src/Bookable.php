<?php

declare(strict_types=1);

namespace Phil\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Availability\AlwaysAvailable;
use Phil\Reservation\Contract\Availability;
use Phil\Reservation\Contract\Rule;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Exception\InvalidInterval;

/**
 * Something that can be booked — a room, a table, a person's time, a piece of equipment, a bed — with its capacity,
 * the buffers each booking keeps around it, when it is open, the rules a booking follows and the clock it reads them
 * on. It checks a request against the bookings already made and finds the free slots of a period.
 *
 * A booking holds its units from its buffer before its start to its buffer after its end: the setting up and the
 * clearing away. Buffers add up: with fifteen minutes after and fifteen before, the booking that follows one ending at
 * 10:00 starts at 10:30 at the earliest.
 */
final readonly class Bookable
{
    /** The most candidate starts `slots()` reads in one call. */
    public const int MAX_CANDIDATES = 10_000;

    /** The most slots `slots()` gives in one call. */
    public const int MAX_SLOTS = 1_000;

    /**
     * @param int        $capacity     the units booked at once: 1 for a room, 8 for the seats of a table
     * @param int        $bufferBefore minutes held before each booking
     * @param int        $bufferAfter  minutes held after each booking
     * @param list<Rule> $rules
     *
     * @throws InvalidDefinition when the capacity is below one or a buffer negative
     */
    public function __construct(
        public int $capacity = 1,
        public int $bufferBefore = 0,
        public int $bufferAfter = 0,
        public Availability $availability = new AlwaysAvailable(),
        public array $rules = [],
        public DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
        if ($capacity < 1) {
            throw InvalidDefinition::because(\sprintf('A resource holds at least one unit, not %d.', $capacity));
        }

        if ($bufferBefore < 0 || $bufferAfter < 0) {
            throw InvalidDefinition::because('The buffers of a resource are not negative.');
        }
    }

    /**
     * Whether the request can be booked, and the bookings it meets.
     *
     * @param iterable<Booking> $bookings the bookings that hold units: confirmed, or held and not expired
     */
    public function check(BookingRequest $request, iterable $bookings): Decision
    {
        $violations = [];
        $quantity = $request->quantity >= 1 && $request->quantity <= $this->capacity;

        if (!$quantity) {
            $violations[] = new Violation(ViolationCode::Quantity, ['max' => $this->capacity]);
        }

        foreach ($this->rules as $rule) {
            $violation = $rule->check($request, $this->timezone);

            if (null !== $violation) {
                $violations[] = $violation;
            }
        }

        if (!$this->open($request->interval)) {
            $violations[] = new Violation(ViolationCode::Unavailable);
        }

        $from = $request->interval->from - $this->bufferBefore * 60;
        $until = $request->interval->until + $this->bufferAfter * 60;
        [$spans, $conflicts] = $this->spans($bookings, $from, $until, $request->replacing);
        $free = $this->capacity - LoadProfile::of($spans)->peak($from, $until);

        if ($quantity && $request->quantity > $free) {
            $violations[] = new Violation(ViolationCode::Full, ['free' => max(0, $free)]);
        }

        return new Decision($violations, $conflicts);
    }

    /**
     * Whether one of its open spans holds the whole interval.
     */
    public function open(Interval $interval): bool
    {
        return array_any($this->availability->windows($interval), static fn (Interval $window): bool => $window->covers($interval));
    }

    /**
     * The units still free over the whole interval, buffers included.
     *
     * @param iterable<Booking> $bookings
     */
    public function free(Interval $interval, iterable $bookings, ?string $replacing = null): int
    {
        $from = $interval->from - $this->bufferBefore * 60;
        $until = $interval->until + $this->bufferAfter * 60;

        return max(0, $this->capacity - LoadProfile::of($this->spans($bookings, $from, $until, $replacing)[0])->peak($from, $until));
    }

    /**
     * The slots of a period that a request asked for now would get: open, following the rules, with enough units free.
     * Starts go by the step on the resource's clock from midnight, each day anew.
     *
     * @param int               $minutes  the length of a slot
     * @param iterable<Booking> $bookings
     * @param ?int              $step     the minutes between two starts; the length of a slot by default
     *
     * @return list<Interval>
     *
     * @throws InvalidDefinition when the length, the quantity, the step or the limit is out of bounds
     * @throws InvalidInterval   when the availability does not read a period that long
     */
    public function slots(Interval $period, int $minutes, iterable $bookings, DateTimeImmutable $now, int $quantity = 1, ?int $step = null, int $limit = 100): array
    {
        $step ??= $minutes;

        if ($minutes < 1 || $step < 1 || $limit < 1 || $limit > self::MAX_SLOTS) {
            throw InvalidDefinition::because(\sprintf('A slot lasts a minute or more, its starts are a minute apart or more, and at most %d slots are read.', self::MAX_SLOTS));
        }

        if ($quantity < 1 || $quantity > $this->capacity) {
            return [];
        }

        $before = $this->bufferBefore * 60;
        $after = $this->bufferAfter * 60;
        $profile = LoadProfile::of($this->spans($bookings, $period->from - $before, $period->until + $after, null)[0]);
        $slots = [];
        $read = 0;

        foreach ($this->availability->windows($period) as $window) {
            $end = min($window->until, $period->until);

            for ($start = $this->align(max($window->from, $period->from), $step); $start + $minutes * 60 <= $end; $start = $this->next($start, $step)) {
                if (++$read > self::MAX_CANDIDATES) {
                    return $slots;
                }

                if ($this->capacity - $profile->peak($start - $before, $start + $minutes * 60 + $after) < $quantity) {
                    continue;
                }

                $slot = Interval::ofTimestamps($start, $start + $minutes * 60, $this->timezone);

                if ($this->follows(new BookingRequest($slot, $now, $quantity))) {
                    $slots[] = $slot;

                    if (\count($slots) >= $limit) {
                        return $slots;
                    }
                }
            }
        }

        return $slots;
    }

    private function follows(BookingRequest $request): bool
    {
        return array_all($this->rules, fn (Rule $rule): bool => !$rule->check($request, $this->timezone) instanceof Violation);
    }

    /**
     * The first start of the step's grid at or after a moment, counted from midnight of its day on the resource's clock.
     */
    private function align(int $moment, int $step): int
    {
        $midnight = $this->midnight($moment);
        $seconds = $step * 60;

        return $midnight + (intdiv($moment - $midnight + $seconds - 1, $seconds) * $seconds);
    }

    /**
     * The start after another: one step later, or the midnight that comes first, where the grid starts anew.
     */
    private function next(int $start, int $step): int
    {
        $later = $start + $step * 60;
        $midnight = $this->midnight($later);

        return $midnight > $start ? $midnight : $this->align($later, $step);
    }

    private function midnight(int $moment): int
    {
        return new DateTimeImmutable('@'.$moment)->setTimezone($this->timezone)->setTime(0, 0)->getTimestamp();
    }

    /**
     * The spans the bookings hold, buffers included, that meet a span, and the identifiers of those bookings.
     *
     * @param iterable<Booking> $bookings
     *
     * @return array{list<array{int, int, int}>, list<string>}
     */
    private function spans(iterable $bookings, int $from, int $until, ?string $replacing): array
    {
        $spans = [];
        $ids = [];

        foreach ($bookings as $booking) {
            $start = $booking->interval->from - $this->bufferBefore * 60;
            $end = $booking->interval->until + $this->bufferAfter * 60;

            if ($booking->id !== $replacing && $start < $until && $from < $end) {
                $spans[] = [$start, $end, $booking->quantity];
                $ids[] = $booking->id;
            }
        }

        return [$spans, $ids];
    }
}
