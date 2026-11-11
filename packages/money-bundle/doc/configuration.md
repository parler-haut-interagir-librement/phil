# Configuration

```yaml
# config/packages/phil_money.yaml
phil_money:
    currencies: [CAD, USD]  # default: [], every known currency
    default_currency: CAD   # default: null, the first accepted currency
    doctrine_mapping: true  # default: true
```

| Key | Default | Meaning |
|---|---|---|
| `currencies` | `[]` | The ISO 4217 codes the application accepts, in the order a choice offers them. Empty accepts every currency the CLDR data knows, and offers in a choice only those in current use: legal tender in at least one country today. |
| `default_currency` | `null` | The currency an empty amount starts in. Without it, the first of `currencies`; with an empty list, none. |
| `doctrine_mapping` | `true` | Maps `Phil\Money\Money` as an embeddable on the ORM, when the application configures the ORM. |

The configuration is checked when the container is built: a code the currency data does not know, or a default
outside a non-empty list, stops the kernel with the code named.

## Services

| Service | Registered | Role |
|---|---|---|
| `Phil\MoneyBundle\Application\AcceptedCurrencies` | always | The accepted currencies and the default; inject it where the application needs the list |
| `Phil\MoneyBundle\Infrastructure\Form\MoneyType` | always, tag `form.type` | The form type |
| `Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrencyValidator` | always, tag `validator.constraint_validator` | The validator of `AcceptedCurrency` |
| `Phil\MoneyBundle\Infrastructure\Validator\MoneyRangeValidator` | always, tag `validator.constraint_validator` | The validator of `MoneyRange` |
| `Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension` | when Twig is installed, tags `twig.attribute_extension` and `twig.runtime` | The `format_money` filter and the `money_schema` and `price_schema` functions |

When DoctrineBundle is registered, the bundle prepends to its configuration:

```yaml
doctrine:
    dbal:
        types:
            phil_currency: Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType
    orm:                     # only when the application configures the ORM and doctrine_mapping is true
        mappings:
            PhilMoney:
                type: xml
                is_bundle: false
                dir: '%vendor%/phil/money-bundle/config/doctrine'
                prefix: Phil\Money
```

An application with several entity managers sets `doctrine_mapping: false` and declares the `PhilMoney` mapping on
the managers that need it.

## Exchange rates

The bundle holds none and converts nothing: an amount stays in its currency, and `Money` refuses to combine two.
An application that converts — a price list in a second currency, a report in one — owns its rates and their
dates, and multiplies by them with its own rounding.
