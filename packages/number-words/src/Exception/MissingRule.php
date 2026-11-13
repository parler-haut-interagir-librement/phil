<?php

declare(strict_types=1);

namespace Phil\NumberWords\Exception;

use InvalidArgumentException;

/**
 * The language has no rule for what was asked: a gender it does not have, a plural ordinal, a verbose form, a
 * currency without names, a currency with a sub-unit but no name for it.
 */
final class MissingRule extends InvalidArgumentException implements NumberWordsException
{
    public static function ruleSet(string $locale, string $ruleSet): self
    {
        return new self(\sprintf('ICU has no rule set "%s" for "%s".', $ruleSet, $locale));
    }

    public static function currency(string $locale, string $currency): self
    {
        return new self(\sprintf('No names for the currency "%s" in "%s": pass a CurrencyName.', $currency, $locale));
    }

    public static function subunit(string $currency): self
    {
        return new self(\sprintf('The currency "%s" has decimals but no name for its sub-unit.', $currency));
    }
}
