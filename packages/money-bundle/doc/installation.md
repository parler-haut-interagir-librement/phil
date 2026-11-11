# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| `ext-intl` | any | `NumberFormatter`, for the locale's separators in forms and for `format_money` |
| `phil/money` | ^0.1@dev | `Money` and `Currency` |
| `symfony/form` | ^8.2 | `MoneyType` |
| `symfony/validator` | ^8.2 | `AcceptedCurrency` and `MoneyRange` |
| `symfony/intl` | ^8.2 | The currency data: which codes exist, and their names in a choice |
| `symfony/options-resolver` | ^8.2 | The options of `MoneyType` |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle` |

Optional, each registered only when its package is installed:

| Package | What it adds |
|---|---|
| `twig/twig` ^3.21 | The `format_money` filter |
| `doctrine/dbal` ^4.5 | The `phil_currency` column type |
| `doctrine/orm` ^3.7 | The mapping of `Money` as an embeddable |

## Installing

The bundle is not published on Packagist yet. Install it and `phil/money` from the repository that holds them, then:

```sh
composer require phil/money-bundle
```

Register the bundle, if Symfony Flex did not:

```php
// config/bundles.php
return [
    // ...
    Phil\MoneyBundle\PhilMoneyBundle::class => ['all' => true],
];
```

Then name the currencies the application accepts ([configuration.md](configuration.md)).
