# Reference

Every type of the bundle, by layer.

## `Phil\MoneyBundle\Application`

| Type | Kind | Role |
|---|---|---|
| `AcceptedCurrencies` | `final class` | The accepted currencies, in order, and the default; for an open list, the currencies in current use; `isKnown()` for the configuration |

## `Phil\MoneyBundle\Infrastructure`

| Type | Kind | Role |
|---|---|---|
| `Form\MoneyType` | form type | An amount field and, when several are offered, a currency choice |
| `Form\MoneyTransformer` | model transformer | Between a `?Money` and the two fields' values |
| `Form\LocalizedDecimal` | `final class` | A decimal read and written in a locale, without a float |
| `Validator\AcceptedCurrency`, `Validator\AcceptedCurrencyValidator` | constraint and validator | The currency is accepted |
| `Validator\MoneyRange`, `Validator\MoneyRangeValidator` | constraint and validator | The amount lies between two bounds |
| `Twig\MoneyExtension` | Twig attribute extension | The `format_money` filter; the `money_schema` and `price_schema` functions |
| `Doctrine\CurrencyType` | DBAL type | `phil_currency` |

## Files

| File | Role |
|---|---|
| `config/doctrine/Money.orm.xml` | `Phil\Money\Money` as an embeddable |
| `translations/validators.en.php`, `translations/validators.fr.php` | The constraints' messages and the form's |

## The bundle

`Phil\MoneyBundle\PhilMoneyBundle`, an `AbstractBundle` with the alias `phil_money`
([configuration.md](configuration.md)).
