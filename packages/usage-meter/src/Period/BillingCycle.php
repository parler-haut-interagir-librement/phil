<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Period;

use DateTimeImmutable;
use Override;
use Phil\UsageMeter\Exception\InvalidUsage;

/**
 * Billing periods of a number of months counted from an anchor: a subscription taken on 14 March at 10:30 starts a
 * new period on the 14th of each month at 10:30, in the anchor's time zone. An anchor on the 29th, 30th or 31st falls
 * on the last day of a shorter month, and comes back to its own day the month after.
 *
 * The anchor given to `windowAt()` — the subject's own, from an `AnchorProvider` — wins over the one the cycle was
 * built with, which serves the subjects that have none.
 */
final readonly class BillingCycle implements PeriodPolicy
{
    public function __construct(
        public DateTimeImmutable $anchor,
        /** The length of a period in months, 1 to 120. */
        public int $months = 1,
    ) {
        if ($months < 1 || $months > 120) {
            throw InvalidUsage::because(\sprintf('A billing period lasts 1 to 120 months; got %d.', $months));
        }
    }

    public static function monthly(DateTimeImmutable $anchor): self
    {
        return new self($anchor);
    }

    public static function yearly(DateTimeImmutable $anchor): self
    {
        return new self($anchor, 12);
    }

    #[Override]
    public function windowAt(DateTimeImmutable $at, ?DateTimeImmutable $anchor = null): Window
    {
        $anchor ??= $this->anchor;
        $local = $at->setTimezone($anchor->getTimezone());
        // Rounded toward zero: before the anchor, one period too late at most, which the check below takes back.
        $index = intdiv($this->monthNumber($local) - $this->monthNumber($anchor), $this->months);
        $start = $this->boundary($anchor, $index);

        // Earlier in the month than the anchor's day and time: still in the period before.
        if ($start > $at) {
            return Window::period($this->boundary($anchor, $index - 1), $start);
        }

        return Window::period($start, $this->boundary($anchor, $index + 1));
    }

    /**
     * The start of the period `$index` periods after the anchor's, on the anchor's day or the month's last day.
     */
    private function boundary(DateTimeImmutable $anchor, int $index): DateTimeImmutable
    {
        // Day zero of the month after is the last day of the month sought; PHP carries the months of year zero over the
        // years.
        $lastDay = $anchor->setDate(0, $this->monthNumber($anchor) + $index * $this->months + 1, 0);

        return $lastDay->setDate((int) $lastDay->format('Y'), (int) $lastDay->format('n'), min((int) $anchor->format('j'), (int) $lastDay->format('j')));
    }

    /**
     * The month of a date counted from the start of year zero.
     */
    private function monthNumber(DateTimeImmutable $date): int
    {
        return $this->months((int) $date->format('Y'), (int) $date->format('n'));
    }

    private function months(int $years, int $months): int
    {
        return $years * 12 + $months;
    }
}
