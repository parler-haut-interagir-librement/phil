<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Almanac\Exception\InvalidRule;

/**
 * Another rule's dates, moved by days: `easter - 47 days`, `monday on or before 02-22 + 3 days`. A date moved into
 * another year stays its rule's.
 */
final readonly class Offset implements Rule
{
    public function __construct(
        public Rule $rule,
        public int $days,
    ) {
        if ($rule instanceof self || 0 === $days || abs($days) > 366) {
            throw InvalidRule::because(\sprintf('An offset is 1 to 366 days either way, of a rule that has none; not %d days of "%s".', $days, $rule));
        }
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        return array_map(fn (DateTimeImmutable $date): DateTimeImmutable => $date->modify(\sprintf('%+d days', $this->days)), $this->rule->in($year, $zone));
    }

    #[Override]
    public function isInstant(): bool
    {
        return $this->rule->isInstant();
    }

    #[Override]
    public function __toString(): string
    {
        return \sprintf('%s %s %d %s', $this->rule, substr(\sprintf('%+d', $this->days), 0, 1), abs($this->days), 1 === abs($this->days) ? 'day' : 'days');
    }
}
