# Reference

Every type of the library, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Money\Currency` | `final class`, readonly `code`, `Stringable`, one instance per code | An ISO 4217 code known to the CLDR data; precision, name and symbol |
| `Phil\Money\Money` | `final readonly class` | Integer minor units with a currency; arithmetic, comparison, decimals, allocation |
| `Phil\Money\SchemaOrg` | `final class` | `amount(Money)`, `price(Money)`: schema.org `MonetaryAmount` and `Offer` price |
| `Phil\Money\Exception\MoneyException` | interface, extends `Throwable` | Every exception of the library |
| `Phil\Money\Exception\UnknownCurrency` | `final class`, `InvalidArgumentException` | A code the CLDR data does not know |
| `Phil\Money\Exception\InvalidAmount` | `final class`, `InvalidArgumentException` | A decimal, an amount or an allocation refused |
| `Phil\Money\Exception\CurrencyMismatch` | `final class`, `DomainException` | Two currencies met in one operation |

See [currencies.md](currencies.md), [amounts.md](amounts.md), [allocation.md](allocation.md) and
[exceptions.md](exceptions.md).

## schema.org

`SchemaOrg::amount(Money)` gives a `MonetaryAmount`, and `SchemaOrg::price(Money)` gives the `price` and
`priceCurrency` of an `Offer`. The value is the exact decimal as text, since a float would round it. The array has
a `@type` and no `@context`: it is meant to be embedded in a larger JSON-LD document.
