# phil/money

**Amounts of money as exact integers in a known currency, with no float anywhere.**

phil/money is a value object for money: an amount in the smallest unit of its currency, and a currency that the
Unicode CLDR data knows. Amounts add, subtract, multiply by whole numbers and compare only within one currency.
Decimals are read and written as strings to the currency's own precision. An amount splits by ratios or into equal
parts without losing or creating a unit, and prints as a schema.org `MonetaryAmount` or `Offer` price. It does not
convert currencies, hold exchange rates, round fractions of a unit, format amounts for display or know which
currencies an application accepts.

It is for any PHP code that handles prices, totals or refunds and cannot afford a cent lost to float rounding. The
other phil/* packages that deal in amounts, such as `phil/tax` and `phil/commerce-bundle`, all speak its `Money`.

It is a plain PHP library with no framework, built on `symfony/intl` for the currency data. In a Symfony
application, reach for `phil/money-bundle`, which adds the accepted currencies, a form type, validation, Twig and
Doctrine.

Two immutable types carry the design, `Money` and `Currency`. Every operation returns a new value, and an operation
across two currencies throws instead of guessing.

## Status

| Part | State |
|---|---|
| `Currency`: ISO 4217 codes known to the CLDR data, precision, localized name and symbol | Implemented and tested |
| `Money`: integer arithmetic, comparison, decimal parsing and printing | Implemented and tested |
| Allocation by ratios and equal split | Implemented and tested |
| `SchemaOrg`: schema.org `MonetaryAmount` and `Offer` price | Implemented and tested |
| Exceptions under one `MoneyException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Currencies](doc/currencies.md) | `Currency`: which codes are accepted, precision, name and symbol. |
| [Amounts](doc/amounts.md) | `Money`: creating, decimals, arithmetic, comparison and the integer range. |
| [Allocation](doc/allocation.md) | `allocate()` and `split()`: how units left over are given out. |
| [Exceptions](doc/exceptions.md) | `MoneyException` and the three exceptions, with their messages. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that counts money. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/money
```

```php
use Phil\Money\Currency;
use Phil\Money\Money;

$cad = Currency::of('CAD');

$price = Money::ofDecimal('19.99', $cad);
$total = $price->multiply(3)->add(Money::of(500, $cad));
$total->toDecimal(); // "64.97"

[$first, $second, $third] = Money::ofDecimal('100', $cad)->split(3); // 33.34, 33.33, 33.33
```

## Requirements

PHP 8.5 or later with `ext-intl`, and `symfony/intl` 8.2 for the currency data.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
