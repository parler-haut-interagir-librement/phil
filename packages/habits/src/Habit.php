<?php

declare(strict_types=1);

namespace Phil\Habits;

use DateTimeZone;
use Phil\Habits\Exception\InvalidHabit;

/**
 * A habit a member keeps: its schedule, the time zone its days are lived in, its first day, and the numeric target a
 * day asks when the habit counts something — pages read, glasses of water — rather than being done or not.
 *
 * The member is a stable opaque identifier of the application, such as a user's UUID: never an email address, which
 * the habit refuses.
 */
final readonly class Habit
{
    public const int MAX_IDENTIFIER_LENGTH = 255;

    public const int MAX_NAME_LENGTH = 200;

    public const int MAX_TARGET = 1_000_000;

    /**
     * @throws InvalidHabit
     */
    public function __construct(
        public string $id,
        public string $member,
        public string $name,
        public Schedule $schedule,
        public DateTimeZone $timeZone,
        public Day $startsOn,
        public ?int $target = null,
        public ?Day $archivedOn = null,
    ) {
        $this->identifier($id, 'habit');
        $this->identifier($member, 'member');

        if (str_contains($member, '@')) {
            throw InvalidHabit::because('A member is named by an opaque identifier, never by an email address.');
        }

        $length = mb_strlen($name);

        if (0 === $length || $length > self::MAX_NAME_LENGTH || trim($name) !== $name || 1 === preg_match('/\p{Cc}/u', $name)) {
            throw InvalidHabit::because(\sprintf('The name of a habit is 1 to %d characters, without surrounding spaces or control characters.', self::MAX_NAME_LENGTH));
        }

        if (null !== $target && ($target < 2 || $target > self::MAX_TARGET)) {
            throw InvalidHabit::because(\sprintf('The target of a habit is a whole number from 2 to %d, or none.', self::MAX_TARGET));
        }

        if ($archivedOn instanceof Day && $archivedOn->isBefore($startsOn)) {
            throw InvalidHabit::because('A habit is archived on or after its first day.');
        }
    }

    /**
     * The same habit, archived on a day: its last day.
     */
    public function archive(Day $on): self
    {
        return new self($this->id, $this->member, $this->name, $this->schedule, $this->timeZone, $this->startsOn, $this->target, $on);
    }

    /**
     * The same habit under another name, schedule or target; its days and check-ins stay.
     */
    public function change(?string $name = null, ?Schedule $schedule = null, ?int $target = null, bool $withoutTarget = false): self
    {
        return new self($this->id, $this->member, $name ?? $this->name, $schedule ?? $this->schedule, $this->timeZone, $this->startsOn, $withoutTarget ? null : $target ?? $this->target, $this->archivedOn);
    }

    /**
     * The units a day's check-in is worth: the target, or one for a habit done or not.
     */
    public function unitsPerDay(): int
    {
        return $this->target ?? 1;
    }

    /**
     * @throws InvalidHabit
     */
    private function identifier(string $value, string $what): void
    {
        if ('' === $value || \strlen($value) > self::MAX_IDENTIFIER_LENGTH || 1 !== preg_match('/^[\x21-\x7E]+$/D', $value)) {
            throw InvalidHabit::because(\sprintf('The identifier of a %s is 1 to %d visible ASCII characters.', $what, self::MAX_IDENTIFIER_LENGTH));
        }
    }
}
