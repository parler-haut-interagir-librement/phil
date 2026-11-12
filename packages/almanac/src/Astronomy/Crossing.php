<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;

/**
 * When the Sun's centre crosses an altitude in a solar day: rising through it in the morning and setting through it
 * in the evening, or neither when it stays above it — midnight sun — or below it — polar night — all day.
 */
final readonly class Crossing
{
    private function __construct(
        public ?DateTimeImmutable $morning,
        public ?DateTimeImmutable $evening,
        public Altitude $sun,
    ) {
    }

    public static function at(DateTimeImmutable $morning, DateTimeImmutable $evening): self
    {
        return new self($morning, $evening, Altitude::Crosses);
    }

    public static function never(Altitude $sun): self
    {
        return new self(null, null, $sun);
    }

    /**
     * The seconds between the two crossings: the whole day when the Sun stays above, none when it stays below.
     */
    public function seconds(): int
    {
        return match ($this->sun) {
            Altitude::Crosses => (int) $this->evening?->getTimestamp() - (int) $this->morning?->getTimestamp(),
            Altitude::AlwaysAbove => 86400,
            Altitude::AlwaysBelow => 0,
        };
    }
}
