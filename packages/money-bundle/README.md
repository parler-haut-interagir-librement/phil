# phil/money-bundle

**Money amounts in Symfony forms, validation, Twig templates and Doctrine entities, without floats.**

phil/money-bundle brings `phil/money` into a Symfony application. It keeps the currencies the application accepts,
by configuration. It adds a form type that reads an amount in the request's locale without ever going through a
float, two validation constraints, a `format_money` Twig filter with two schema.org functions, and Doctrine mappings that store a currency in one
column and an amount in two. It does not convert currencies, fetch exchange rates, round amounts or decide prices:
amounts stay in their currency, and an amount finer than its currency is refused rather than rounded.

It is for a Symfony application where an administrator enters prices, a visitor sees them, and entities store them.
Code outside Symfony, or a domain layer that only computes, needs `phil/money` alone.

It is a Symfony bundle. It plugs into the Form and Validator components, into Twig when Twig is installed, and into
Doctrine ORM when DoctrineBundle is registered, mapping `Money` as an embeddable.

The configuration is checked when the container is built, so an unknown currency code stops the kernel with the
code named. Every amount crosses the bundle as a `Money`, from the form field to the database column.

## Status

| Part | State |
|---|---|
| `phil_money` configuration and `AcceptedCurrencies` | Implemented and tested |
| `MoneyType` form type, read and written in the request's locale | Implemented and tested |
| `AcceptedCurrency` and `MoneyRange` constraints, messages in English and French | Implemented and tested |
| `format_money` Twig filter, `money_schema` and `price_schema` Twig functions | Implemented and tested |
| `phil_currency` column type and the `Money` embeddable | Implemented and tested |
| A kernel test of a form, the constraints, Twig and SQLite | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the bundle. |
| [Configuration](doc/configuration.md) | Every key, its default, and the services the bundle registers. |
| [Forms](doc/forms.md) | `MoneyType`: options, the fields it renders, how the amount is read, and its errors. |
| [Validation](doc/validation.md) | `AcceptedCurrency` and `MoneyRange`, with their messages. |
| [Twig](doc/twig.md) | The `format_money` filter, and `format_currency`. |
| [Doctrine](doc/doctrine.md) | The `phil_currency` column type and the `Money` embeddable. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test forms and entities that hold money. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/money-bundle
```

```yaml
# config/packages/phil_money.yaml
phil_money:
    currencies: [CAD, USD]
```

```php
#[ORM\Embedded(class: Money::class)]
#[AcceptedCurrency]
#[MoneyRange(min: '0.01')]
public Money $price;

$builder->add('price', MoneyType::class);
```

```twig
{{ product.price|format_money }}
```

## Requirements

PHP 8.5 or later with `ext-intl`, `phil/money`, and Symfony 8.2's form, validator and intl components. Optional:
`twig/twig` for the filter, `doctrine/dbal` and `doctrine/orm` for the mappings.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
