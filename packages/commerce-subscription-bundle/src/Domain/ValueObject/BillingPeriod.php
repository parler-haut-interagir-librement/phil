<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How long one paid period lasts: a count of a unit, `1 month`, `3 months`, `1 year`.
 *
 * **Periods are counted from an anchor, never chained**: the end of the n-th period is the anchor plus n periods,
 * so a subscription started on the 31st ends its months on the last day of shorter months and comes back to the
 * 31st, rather than drifting to the 28th.
 */
final readonly class BillingPeriod
{
    private function __construct(
        public BillingUnit $unit,
        public int $count,
    ) {
    }

    public static function of(BillingUnit $unit, int $count = 1): self
    {
        if ($count < 1 || $count > 36) {
            throw InvalidValue::because(\sprintf('A billing period counts 1 to 36 units; %d does not.', $count));
        }

        return new self($unit, $count);
    }

    /**
     * The end of the `$periods`-th period after the anchor.
     */
    public function after(DateTimeImmutable $anchor, int $periods): DateTimeImmutable
    {
        $units = $this->count * $periods;

        return match ($this->unit) {
            BillingUnit::Day => $anchor->modify(\sprintf('+%d days', $units)),
            BillingUnit::Week => $anchor->modify(\sprintf('+%d weeks', $units)),
            BillingUnit::Month => $this->addMonths($anchor, $units),
            BillingUnit::Year => $this->addMonths($anchor, 12 * $units),
        };
    }

    public function equals(self $other): bool
    {
        return $this->unit === $other->unit && $this->count === $other->count;
    }

    /**
     * Months added on the calendar, the day kept or brought back to the last day of a shorter month.
     */
    private function addMonths(DateTimeImmutable $anchor, int $months): DateTimeImmutable
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $anchor->format('Y-n-j')));
        $index = $year * 12 + $month - 1 + $months;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        $length = (int) new DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month))->format('t');

        return $anchor->setDate($year, $month, min($day, $length));
    }
}
