<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Application;

use InvalidArgumentException;
use Phil\Money\Currency;
use Symfony\Component\Intl\Currencies;

/**
 * The currencies the application accepts, from `phil_money.currencies`, and the one an empty amount is in.
 *
 * An empty list accepts every currency the Unicode CLDR data knows, and has no default unless one is named; it
 * offers, through `all()`, only the currencies in current use — legal tender in at least one country today — so that
 * a choice does not list the Deutsche Mark. A list without a named default defaults to its first currency.
 */
final class AcceptedCurrencies
{
    /**
     * The currencies in current use, computed once, for an open list.
     *
     * @var list<Currency>|null
     */
    private ?array $inUse = null;

    /**
     * @param list<Currency> $currencies
     */
    private function __construct(
        private readonly array $currencies,
        private readonly ?Currency $default,
    ) {
    }

    /**
     * @param list<string> $codes
     */
    public static function of(array $codes, ?string $default = null): self
    {
        $currencies = array_map(Currency::of(...), array_values(array_unique($codes)));
        $defaultCurrency = null === $default ? ($currencies[0] ?? null) : Currency::of($default);

        if (null !== $defaultCurrency && [] !== $currencies && !self::contains($currencies, $defaultCurrency)) {
            throw new InvalidArgumentException(\sprintf('The default currency %s is not one of the accepted currencies.', $defaultCurrency->code));
        }

        return new self($currencies, $defaultCurrency);
    }

    /**
     * Whether the Unicode CLDR data knows the code, as `Currency::of()` requires: what the configuration checks.
     */
    public static function isKnown(string $code): bool
    {
        return Currencies::exists($code);
    }

    /**
     * Whether the list is closed; an open one accepts every known currency.
     */
    public function isRestricted(): bool
    {
        return [] !== $this->currencies;
    }

    /**
     * The accepted currencies in their configured order or, for an open list, every currency in current use by code:
     * legal tender in at least one country today, as the CLDR data records it.
     *
     * @return list<Currency>
     */
    public function all(): array
    {
        if ($this->isRestricted()) {
            return $this->currencies;
        }

        if (null === $this->inUse) {
            $this->inUse = [];

            foreach (Currencies::getCurrencyCodes() as $code) {
                if (Currencies::isValidInAnyCountry($code)) {
                    $this->inUse[] = Currency::of($code);
                }
            }
        }

        return $this->inUse;
    }

    public function accepts(Currency $currency): bool
    {
        return !$this->isRestricted() || self::contains($this->currencies, $currency);
    }

    public function default(): ?Currency
    {
        return $this->default;
    }

    /**
     * @param list<Currency> $currencies
     */
    private static function contains(array $currencies, Currency $currency): bool
    {
        return array_any($currencies, $currency->equals(...));
    }
}
