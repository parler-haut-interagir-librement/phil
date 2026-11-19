<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\Reservation\Bookable;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MaximumAdvance;

/**
 * The rules of the library's loans: how many days a loan lasts — due at the end of its last day on the library's
 * clock —, how many times it is renewed, how many loans a member holds at once, whether a copy overdue stops a member
 * from borrowing, how far ahead a copy is reserved, and how long the history of a closed loan names its member.
 */
final readonly class LoanPolicy
{
    public DateTimeZone $timezone;

    /**
     * @param ?int $historyDays the days a closed loan keeps its member; forever when null
     *
     * @throws InvalidLibraryData when a number is out of its bounds or the time zone is unknown
     */
    public function __construct(
        public int $loanDays = 21,
        public int $maxRenewals = 2,
        public int $maxOpenLoans = 10,
        public bool $blockWhenOverdue = true,
        public int $reservationDays = 90,
        public ?int $historyDays = null,
        string $timezone = 'UTC',
    ) {
        if ($loanDays < 1 || $loanDays > 365 || $maxRenewals < 0 || $maxRenewals > 99 || $maxOpenLoans < 1 || $maxOpenLoans > 999 || $reservationDays < 1 || $reservationDays > 730 || (null !== $historyDays && ($historyDays < 0 || $historyDays > 36_500))) {
            throw InvalidLibraryData::because('A loan lasts 1 to 365 days, is renewed 0 to 99 times, a member holds 1 to 999 loans, a copy is reserved 1 to 730 days ahead, and a history is kept 0 to 36500 days.');
        }

        if (!\in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidLibraryData::because(\sprintf('"%s" is not a time zone.', $timezone));
        }

        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * The due date of a loan counted from a moment: the end of the day so many days later, on the library's clock.
     */
    public function dueFrom(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->timezone)->modify(\sprintf('+%d days', $this->loanDays))->setTime(23, 59, 59);
    }

    /**
     * The longest a loan lasts, renewals included, in minutes, with a day to spare for the end of the first day.
     */
    public function longestMinutes(): int
    {
        return ($this->loanDays * ($this->maxRenewals + 1) + 1) * 1440;
    }

    /**
     * A copy as `phil/reservation` sees it: one unit, open always, reserved so far ahead, lent so long at most.
     */
    public function bookable(): Bookable
    {
        return new Bookable(1, rules: [new MaximumAdvance($this->reservationDays * 1440), new DurationLimits(1, $this->longestMinutes())], timezone: $this->timezone);
    }
}
