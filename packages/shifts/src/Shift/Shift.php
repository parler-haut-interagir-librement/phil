<?php

declare(strict_types=1);

namespace Phil\Shifts\Shift;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Shifts\Exception\InvalidShift;

/**
 * A shift: what it asks — fixed, a task, a duration —, its window, the minutes it counts, its places and its reserve,
 * how its places are given, the role it requires, whether it is critical, the weight of its minutes and whether a swap
 * of its places waits for a coordinator.
 *
 * The bounds: a window from 5 minutes to 31 days; a fixed shift counts the minutes of its window, a task or a duration
 * from 5 to 1,440 minutes that fit in the window; 1 to 1,000 places and a reserve of 0 to 100; a weight from 10 to
 * 1,000 percent, 100 by default — a night shift may count 150 %; a title of 200 characters, a location of 200 and a
 * description of 2,000. The instants are kept in UTC.
 */
final readonly class Shift
{
    public const int MAX_CAPACITY = 1000;

    public const int MAX_RESERVE = 100;

    public const int MIN_MINUTES = 5;

    public const int MAX_MINUTES = 1440;

    public const int MAX_WINDOW_DAYS = 31;

    public const int MIN_WEIGHT = 10;

    public const int MAX_WEIGHT = 1000;

    public DateTimeImmutable $startsAt;

    public DateTimeImmutable $endsAt;

    /** The minutes the shift counts for each volunteer who serves it. */
    public int $minutes;

    /**
     * @param ?int    $minutes      the minutes of a task or a duration; null, or those of the window, for a fixed shift
     * @param int     $weight       the percent the minutes count for, 100 for as many as they are
     * @param ?string $requiredRole the role a volunteer needs to sign up, express interest or take a swap
     *
     * @throws InvalidShift when a value is outside its bounds
     */
    public function __construct(
        public string $id,
        public string $title,
        public ShiftKind $kind,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        ?int $minutes = null,
        public int $capacity = 1,
        public AssignmentMode $mode = AssignmentMode::SelfSignUp,
        public int $reserve = 0,
        public ?string $requiredRole = null,
        public bool $critical = false,
        public int $weight = 100,
        public bool $swapApproval = false,
        public string $location = '',
        public string $description = '',
    ) {
        Identifier::key($id, 'shift');
        Identifier::text($title, 'title', 200);
        Identifier::text($location, 'location', 200, false);
        Identifier::text($description, 'description', 2000, false);

        if (null !== $requiredRole) {
            Identifier::role($requiredRole);
        }

        $this->startsAt = $startsAt->setTimezone(new DateTimeZone('UTC'));
        $this->endsAt = $endsAt->setTimezone(new DateTimeZone('UTC'));
        $window = intdiv($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp(), 60);

        if ($window < self::MIN_MINUTES || $window > self::MAX_WINDOW_DAYS * self::MAX_MINUTES) {
            throw InvalidShift::because('The window of a shift ends 5 minutes to 31 days after it starts.');
        }

        $this->minutes = $this->count($kind, $window, $minutes);

        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            throw InvalidShift::because('A shift has 1 to 1,000 places.');
        }

        if ($reserve < 0 || $reserve > self::MAX_RESERVE) {
            throw InvalidShift::because('The reserve of a shift holds 0 to 100 volunteers.');
        }

        if ($weight < self::MIN_WEIGHT || $weight > self::MAX_WEIGHT) {
            throw InvalidShift::because('The weight of a shift is 10 to 1,000 percent.');
        }
    }

    /**
     * The minutes a volunteer is credited with, weighted, rounded half up to the minute.
     */
    public function weightedMinutes(): int
    {
        return intdiv($this->minutes * $this->weight + 50, 100);
    }

    /**
     * Whether a volunteer could not serve both shifts: two fixed shifts whose windows, widened by the rest asked between
     * them, overlap. A task or a duration is served when the volunteer chooses, and never overlaps.
     */
    public function overlaps(self $other, int $restMinutes = 0): bool
    {
        if (ShiftKind::Fixed !== $this->kind || ShiftKind::Fixed !== $other->kind) {
            return false;
        }

        $rest = max(0, $restMinutes) * 60;

        return $this->startsAt->getTimestamp() < $other->endsAt->getTimestamp() + $rest
            && $other->startsAt->getTimestamp() < $this->endsAt->getTimestamp() + $rest;
    }

    /**
     * Whether volunteers may still join or leave: before the start of a fixed shift, before the end of the window of a
     * task or a duration.
     */
    public function isOpenAt(DateTimeImmutable $now): bool
    {
        $limit = ShiftKind::Fixed === $this->kind ? $this->startsAt : $this->endsAt;

        return $now < $limit;
    }

    /**
     * Whether the shift is over, and its minutes served: once its window ended.
     */
    public function isOverAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->endsAt;
    }

    /**
     * @param list<string> $roles
     */
    public function admits(array $roles): bool
    {
        return null === $this->requiredRole || \in_array($this->requiredRole, $roles, true);
    }

    private function count(ShiftKind $kind, int $window, ?int $minutes): int
    {
        if (ShiftKind::Fixed === $kind) {
            if (null !== $minutes && $minutes !== $window) {
                throw InvalidShift::because('A fixed shift counts the minutes of its window.');
            }

            if ($window > self::MAX_MINUTES) {
                throw InvalidShift::because('A fixed shift lasts 1,440 minutes at most.');
            }

            return $window;
        }

        if (null === $minutes || $minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES || $minutes > $window) {
            throw InvalidShift::because('A task or a duration counts 5 to 1,440 minutes that fit in its window.');
        }

        return $minutes;
    }
}
