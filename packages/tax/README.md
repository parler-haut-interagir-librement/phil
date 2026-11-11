# phil/tax

**Several taxes on one sale, each computed on the same base and itemised to the cent.**

phil/tax computes multi-rate taxation on a shared base: a sale taxed by several taxes at once, a federal and a
provincial tax for instance, each computed on the same base, each rounded on its own line and itemised on the
invoice. It also extracts the taxes a tax-included price contains. It does not decide which rates apply to a sale,
whether a seller must collect tax, which jurisdiction taxes a sale, or what a receipt must mention. It holds no
current rates and never compounds one tax on another. Those belong to the application or to the tax engine behind
it.

It is for any PHP code that issues invoices, receipts or credit notes and must show each tax on its own line.
`phil/commerce-bundle` uses it to tax each order line; an application without a store can call it directly.

It is a plain PHP library whose only dependency is `phil/money`, whose `Money` it takes and returns. Rates are held in
parts per million, never in a float. Each tax is rounded half away from zero in integer arithmetic, so a credit note
reverses an invoice exactly.

The design is small: a `TaxRate` value, a `MultiRateTaxCalculator` that applies a list of rates, and a `TaxBreakdown`
of `TaxLine`s that the invoice prints.

## Status

| Part | State |
|---|---|
| `TaxRate`: code, parts per million, percentage read and printed, tax on a base | Implemented and tested |
| `TaxLine`: computed or reported by an external engine | Implemented and tested |
| `TaxBreakdown`: lines on one base, totals, line by code, credit-note reversal | Implemented and tested |
| `MultiRateTaxCalculator`: every rate on the same base | Implemented and tested |
| Exceptions under one `TaxException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Rates](doc/rates.md) | `TaxRate`: codes, parts per million, percentages, rounding and the largest base. |
| [Breakdowns](doc/breakdowns.md) | `TaxLine`, `TaxBreakdown` and `MultiRateTaxCalculator`: one base, line-by-line rounding, reversal. |
| [Choosing the rates](doc/choosing-rates.md) | What the library leaves to the application, and a resolver it may write. |
| [Exceptions](doc/exceptions.md) | `TaxException` and the three exceptions, with their messages. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that taxes. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/tax
```

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;
use Phil\Tax\TaxRate;

$breakdown = new MultiRateTaxCalculator()->calculate(Money::ofDecimal('19.99', Currency::of('CAD')), [
    TaxRate::ofPercent('GST', '5'),
    TaxRate::ofPercent('QST', '9.975'),
]);

$breakdown->line('QST')?->amount->toDecimal(); // "1.99", on 19.99 and not on 19.99 plus GST
$breakdown->totalWithTax()->toDecimal();       // "22.98"
$credit = $breakdown->reversed();              // every line negated
```

## Requirements

PHP 8.5 or later, and `phil/money` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
