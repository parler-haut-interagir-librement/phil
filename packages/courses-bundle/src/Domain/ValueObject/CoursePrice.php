<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * The price of a course in a catalogue: a whole number of the minor units of a currency — cents for `CAD` — above zero,
 * and the currency's ISO 4217 code. A course without a price is free; the bundle never converts nor rounds a price, the
 * store that sells it does what money asks.
 */
final readonly class CoursePrice
{
    /** The largest amount a price holds, in minor units: ten billion units of a currency with two decimals. */
    public const int MAX_MINOR_UNITS = 1_000_000_000_000;

    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {
    }

    /**
     * @throws InvalidCourse when the amount is not above zero and within the bound, or the currency is not a code of
     *                       three letters
     */
    public static function of(int $minorUnits, string $currency): self
    {
        if ($minorUnits < 1 || $minorUnits > self::MAX_MINOR_UNITS) {
            throw InvalidCourse::because(\sprintf('A price is 1 to %d minor units of its currency; a free course has none.', self::MAX_MINOR_UNITS));
        }

        $currency = strtoupper(trim($currency));

        if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw InvalidCourse::because('The currency of a price is its ISO 4217 code, three letters such as "CAD".');
        }

        return new self($minorUnits, $currency);
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }
}
