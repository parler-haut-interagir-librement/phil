<?php

declare(strict_types=1);

namespace Phil\NumberWords;

use Phil\NumberWords\Exception\MissingRule;
use Phil\NumberWords\Exception\NumberOutOfRange;

/**
 * The names of a currency in one language, for amounts in words: its unit and its sub-unit, singular and plural, and
 * the gender each number agrees with.
 */
final readonly class CurrencyName
{
    /**
     * @param string      $one            the unit after a count that takes the singular: « dollar »
     * @param string      $other          the unit after any other count: « dollars »
     * @param int         $fractionDigits the decimals of the currency: 2 for cents, 0 for none, at most 4
     * @param string|null $subunitOne     the sub-unit in the singular: « cent »; needed when there are decimals
     * @param string|null $subunitOther   the sub-unit in the plural: « cents »; the singular when left out
     *
     * @throws MissingRule      a currency with decimals and no sub-unit
     * @throws NumberOutOfRange decimals outside 0 to 4
     */
    public function __construct(
        public string $one,
        public string $other,
        public Gender $gender = Gender::Masculine,
        public int $fractionDigits = 2,
        public ?string $subunitOne = null,
        public ?string $subunitOther = null,
        public Gender $subunitGender = Gender::Masculine,
    ) {
        if ($fractionDigits < 0 || $fractionDigits > 4) {
            throw NumberOutOfRange::fractionDigits($fractionDigits);
        }

        if ($fractionDigits > 0 && null === $subunitOne) {
            throw MissingRule::subunit($one);
        }
    }
}
