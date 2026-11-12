<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;

/**
 * The month of a cumulative counter, in UTC like the days it adds up.
 */
final readonly class UtcMonth implements Stringable
{
    private function __construct(
        public int $year,
        public int $month,
    ) {
    }

    public static function of(UtcDay $day): self
    {
        return self::fromString(substr($day->toString(), 0, 7));
    }

    /**
     * To read back a stored month, in `YYYY-MM` form.
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])$/D', $value, $parts)) {
            throw InvalidValue::because(\sprintf('"%s" is not a month in YYYY-MM form.', $value));
        }

        return new self((int) $parts['year'], (int) $parts['month']);
    }

    public function minus(int $months): self
    {
        $total = $this->year * 12 + $this->month - 1 - $months;

        return new self(intdiv($total, 12), $total % 12 + 1);
    }

    public function firstDay(): UtcDay
    {
        return UtcDay::fromString($this->toString().'-01');
    }

    public function toString(): string
    {
        return \sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
