<?php

declare(strict_types=1);

namespace Phil\Recurrence;

use DateTimeInterface;

/**
 * The days of the week, by their RFC 5545 abbreviations.
 */
enum Weekday: string
{
    case Monday = 'MO';
    case Tuesday = 'TU';
    case Wednesday = 'WE';
    case Thursday = 'TH';
    case Friday = 'FR';
    case Saturday = 'SA';
    case Sunday = 'SU';

    public static function of(DateTimeInterface $date): self
    {
        return match ($date->format('N')) {
            '1' => self::Monday,
            '2' => self::Tuesday,
            '3' => self::Wednesday,
            '4' => self::Thursday,
            '5' => self::Friday,
            '6' => self::Saturday,
            default => self::Sunday,
        };
    }

    /**
     * The ISO 8601 number: Monday is 1.
     */
    public function iso(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
            self::Sunday => 7,
        };
    }
}
