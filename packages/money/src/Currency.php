<?php

declare(strict_types=1);

namespace Phil\Money;

use Phil\Money\Exception\UnknownCurrency;
use Stringable;
use Symfony\Component\Intl\Currencies;

/**
 * An ISO 4217 alphabetic currency code that the Unicode CLDR data knows, always explicit.
 *
 * **Known, not merely well formed.** `XYZ` has the shape of a code and names nothing; it is refused, so that
 * every currency has a precision, a name and a symbol. Which currencies an application accepts is its own
 * decision, made by configuration: this type only guarantees that the code is real.
 *
 * Codes are case-sensitive: `cad` is refused rather than corrected, since a lowercase code is a sign that the
 * input was never validated.
 *
 * **One instance per code.** The first `of()` of a code checks it against the CLDR data; later calls return the same
 * instance, without a lookup, so that reading a stored code back inside a loop costs an array access. Only known codes
 * are kept, a few hundred at most.
 */
final class Currency implements Stringable
{
    /** @var array<string, self> the currencies already checked, by code */
    private static array $known = [];

    private function __construct(
        public readonly string $code,
    ) {
    }

    public static function of(string $code): self
    {
        return self::$known[$code] ??= self::known($code);
    }

    /**
     * How many decimals an amount in this currency counts: 2 for the Canadian dollar, 0 for the yen, 3 for the
     * Bahraini dinar.
     */
    public function fractionDigits(): int
    {
        return Currencies::getFractionDigits($this->code);
    }

    /**
     * The currency's name in a locale, the default locale when none is given.
     */
    public function name(?string $locale = null): string
    {
        return Currencies::getName($this->code, $locale);
    }

    /**
     * The currency's symbol in a locale, the default locale when none is given: `$` for the Canadian dollar in
     * `fr_CA`, `CA$` in `en`.
     */
    public function symbol(?string $locale = null): string
    {
        return Currencies::getSymbol($this->code, $locale);
    }

    private static function known(string $code): self
    {
        if (!Currencies::exists($code)) {
            throw UnknownCurrency::code($code);
        }

        return new self($code);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
