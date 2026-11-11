<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use DateTimeInterface;

/**
 * The days of the week, numbered as ISO 8601 numbers them: Monday is 1.
 */
enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public static function of(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('N'));
    }

    public function previous(): self
    {
        return self::from(($this->value + 5) % 7 + 1);
    }

    /**
     * The schema.org day, `https://schema.org/Monday`.
     */
    public function schemaOrg(): string
    {
        return 'https://schema.org/'.$this->name;
    }
}
