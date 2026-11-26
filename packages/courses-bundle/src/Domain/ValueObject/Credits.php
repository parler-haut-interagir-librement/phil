<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Training\CreditUnit;

/**
 * The credits a course grants on its completion: an amount with two decimals at most, kept as hundredths so that sums
 * stay exact, from 0.01 to 9 999.99, in continuing education units or hours.
 */
final readonly class Credits
{
    public const int MAX_HUNDREDTHS = 999_999;

    /** The hours of a continuing education unit, as accrediting bodies count it: ten contact hours. */
    public const int CEU_HOURS = 10;

    private function __construct(
        public int $hundredths,
        public CreditUnit $unit,
    ) {
    }

    /**
     * @throws InvalidCourse when the amount is outside the bounds
     */
    public static function hundredths(int $hundredths, CreditUnit $unit): self
    {
        if ($hundredths < 1 || $hundredths > self::MAX_HUNDREDTHS) {
            throw InvalidCourse::because('Credits are from 0.01 to 9999.99.');
        }

        return new self($hundredths, $unit);
    }

    /**
     * @param string $amount such as "1.5" or "10", two decimals at most
     *
     * @throws InvalidCourse when the amount is not a number of two decimals at most, or outside the bounds
     */
    public static function of(string $amount, CreditUnit $unit): self
    {
        if (1 !== preg_match('/^(\d{1,4})(?:\.(\d{1,2}))?$/D', $amount, $match)) {
            throw InvalidCourse::because('Credits are written as a number of two decimals at most, such as "1.5".');
        }

        [, $whole, $decimals] = $match + [2 => ''];

        return self::hundredths((int) $whole * 100 + (int) str_pad($decimals, 2, '0'), $unit);
    }

    /**
     * The amount as text: "1.5", "10", "0.25".
     */
    public function amount(): string
    {
        return self::write($this->hundredths);
    }

    /**
     * A number of hundredths as text, a total of credits included: "1.5", "10", "0.25".
     */
    public static function write(int $hundredths): string
    {
        $cents = $hundredths % 100;

        return intdiv($hundredths, 100).(0 === $cents ? '' : rtrim(\sprintf('.%02d', $cents), '0'));
    }
}
