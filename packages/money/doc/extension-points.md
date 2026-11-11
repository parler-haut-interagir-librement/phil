# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through arguments, and what it adds is written beside the library.

| Point | How |
|---|---|
| The accepted currencies | `Currency::of()` accepts every ISO 4217 code of the CLDR data; an application that sells in fewer keeps its own list and checks a code against it before calling the library |
| The rounding of a share | `allocate()` and `split()` decide where the units left over go; a different rule is written over the `minorUnits` property and `Money::of()`, never with floats |
| How an amount is shown | `Money::toDecimal()` gives the digits; the application formats them in its locale, or uses `SchemaOrg::amount()` and `SchemaOrg::price()` for structured data |
| Catching the library's errors | Every exception implements `Phil\Money\Exception\MoneyException`: one `catch` covers `UnknownCurrency`, `InvalidAmount` and `CurrencyMismatch` |

A conversion between currencies is not the library's: rates belong to the application, which multiplies minor units
and makes a new `Money` in the target currency.
