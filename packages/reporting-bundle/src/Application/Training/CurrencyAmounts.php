<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use NumberFormatter;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * Amounts in minor units of a currency written as decimal numbers of it: 123456 cents of `CAD` are 1234.56, 500 yen
 * of `JPY` are 500.
 */
final readonly class CurrencyAmounts
{
    public int $fractionDigits;

    /**
     * @throws InvalidFigures when the code is not three capital letters
     */
    public function __construct(
        public string $currency,
    ) {
        if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw InvalidFigures::because(\sprintf('"%s" is not an ISO 4217 currency code', $currency));
        }

        $this->fractionDigits = new NumberFormatter('en@currency='.$currency, NumberFormatter::CURRENCY)->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    public function decimal(int $minor): float
    {
        return fdiv($minor, 10 ** $this->fractionDigits);
    }
}
