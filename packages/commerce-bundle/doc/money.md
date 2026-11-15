# Money

The bundle counts every amount with `phil/money`, which `phil/tax`, a requirement of the bundle, brings:
`Phil\Money\Money`, an amount in the smallest unit of its currency, and `Phil\Money\Currency`, an ISO 4217 code the
Unicode CLDR data knows. The bundle defines no money type of its own; the library's documentation describes both types,
decimal parsing, allocation and its exceptions.

What the bundle relies on:

- **Integers in minor units.** `phil/tax` computes taxes in integer arithmetic on `Money::$minorUnits`
  ([taxes.md](taxes.md)).
- **The currency is part of the value.** Amounts in two currencies are never added, subtracted or compared:
  `Phil\Money\Exception\CurrencyMismatch` is thrown, by the library and by `phil/tax` when a tax line mixes
  currencies. **No conversion happens in the bundle, ever.**
- **A negative amount is legitimate.** A credit note reverses an invoice line.
- **An overflow is refused** with `Phil\Money\Exception\InvalidAmount`, rather than turned into a float.

The exceptions of `phil/money` implement `Phil\Money\Exception\MoneyException`, not the `DomainError` of
`phil/ddd-bundle`: an application that maps domain errors to responses maps both.

Forms, validation, Twig formatting and Doctrine columns for these types are in `phil/money-bundle`.

```php
use Phil\Money\Currency;
use Phil\Money\Money;

$cad = Currency::of('CAD');

$price = Money::ofDecimal('19.99', $cad);
$total = $price->add(Money::of(500, $cad));        // 24.99 CAD
$price->add(Money::of(100, Currency::of('USD'))); // CurrencyMismatch
```
