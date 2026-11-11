<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use Override;
use Phil\OpeningHours\Exception\InvalidOpeningHours;
use Stringable;

/**
 * When a place opens and closes on a day. A range that closes before it opens closes the next day: a bar open from
 * `18:00` to `02:00`.
 */
final readonly class TimeRange implements Stringable
{
    private function __construct(
        public TimeOfDay $opens,
        public TimeOfDay $closes,
    ) {
    }

    public static function of(TimeOfDay $opens, TimeOfDay $closes): self
    {
        if ($opens->equals($closes) || TimeOfDay::END_OF_DAY === $opens->minutes) {
            throw InvalidOpeningHours::because(\sprintf('A range from %s to %s is no range.', $opens, $closes));
        }

        return new self($opens, $closes);
    }

    /**
     * `09:00-17:00`, `9h-17h`; an en dash or `to` may stand for the hyphen.
     */
    public static function parse(string $input): self
    {
        $parts = preg_split('/\s*(?:-|–|to)\s*/i', $input);

        if (false === $parts || 2 !== \count($parts)) {
            throw InvalidOpeningHours::unreadable($input, 'a range of hours');
        }

        return self::of(TimeOfDay::parse($parts[0]), TimeOfDay::parse($parts[1]));
    }

    /**
     * Whether the range closes the next day.
     */
    public function isOvernight(): bool
    {
        return -1 === ($this->closes->minutes <=> $this->opens->minutes);
    }

    /**
     * Whether a minute of the range's own day is in it.
     */
    public function contains(int $minute): bool
    {
        return $minute >= $this->opens->minutes && ($this->isOvernight() || $minute < $this->closes->minutes);
    }

    /**
     * Whether a minute of the next day is in it.
     */
    public function spills(int $minute): bool
    {
        return $this->isOvernight() && $minute < $this->closes->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->opens->equals($other->opens) && $this->closes->equals($other->closes);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->opens.'-'.$this->closes;
    }
}
