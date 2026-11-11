<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\OpeningHours\Exception\InvalidOpeningHours;

/**
 * Hours that replace the week's from a day through another: a holiday closed, a Christmas Eve that closes early, a
 * summer with other hours. Dates are calendar days, `Y-m-d`, in the place's time zone.
 */
final readonly class SpecialHours
{
    private function __construct(
        public string $from,
        public string $through,
        public DayHours $hours,
        public ?string $label,
    ) {
    }

    /**
     * @param string $from    `2026-12-24`
     * @param string $through the last day, `$from` by default
     */
    public static function of(string $from, ?string $through = null, ?DayHours $hours = null, ?string $label = null): self
    {
        $through ??= $from;

        if (!self::isDate($from) || !self::isDate($through) || $through < $from) {
            throw InvalidOpeningHours::because(\sprintf('From %s through %s is no period of days.', $from, $through));
        }

        $label = trim($label ?? '');

        if (mb_strlen($label) > 100) {
            throw InvalidOpeningHours::because('The label of special hours is 100 characters or fewer.');
        }

        return new self($from, $through, $hours ?? DayHours::closed(), '' === $label ? null : $label);
    }

    public function covers(DateTimeInterface $day): bool
    {
        $date = $day->format('Y-m-d');

        return $date >= $this->from && $date <= $this->through;
    }

    private static function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return false !== $parsed && $parsed->format('Y-m-d') === $date;
    }
}
