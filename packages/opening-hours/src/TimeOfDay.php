<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use Override;
use Phil\OpeningHours\Exception\InvalidOpeningHours;
use Stringable;

/**
 * A time of day to the minute, from `00:00` to `24:00`, the end of the day a range may close at.
 */
final readonly class TimeOfDay implements Stringable
{
    public const int END_OF_DAY = 1440;

    private function __construct(
        public int $minutes,
    ) {
    }

    public static function of(int $hours, int $minutes = 0): self
    {
        if ($hours < 0 || $minutes < 0 || $minutes > 59 || $hours * 60 + $minutes > self::END_OF_DAY) {
            throw InvalidOpeningHours::because(\sprintf('%d:%02d is not a time of day.', $hours, $minutes));
        }

        return new self($hours * 60 + $minutes);
    }

    /**
     * `9`, `9h`, `9h30`, `09:30`, `24:00`.
     */
    public static function parse(string $input): self
    {
        if (1 !== preg_match('/^(?<hours>\d{1,2})(?:[:h](?<minutes>\d{2})?)?$/i', trim($input), $match)) {
            throw InvalidOpeningHours::unreadable($input, 'a time of day');
        }

        return self::of((int) $match['hours'], (int) ($match['minutes'] ?? 0));
    }

    public function hours(): int
    {
        return intdiv($this->minutes, 60);
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }

    /**
     * `09:30`; the end of the day is `24:00`.
     */
    #[Override]
    public function __toString(): string
    {
        return \sprintf('%02d:%02d', $this->hours(), $this->minutes % 60);
    }
}
