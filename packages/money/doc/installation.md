# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-intl` | any | The default locale (`Locale::getDefault()`) of `name()` and `symbol()` called without a locale |
| `symfony/intl` | ^8.2 | The currency data of the Unicode CLDR: which codes exist, their fraction digits, names and symbols |

`symfony/intl` ships the data of every locale with the package; the extension only supplies the default locale.

## Installing

```sh
composer require phil/money
```

The library registers nothing and reads no configuration. In a Symfony application, `phil/money-bundle` adds
forms, validation, Twig formatting and Doctrine columns for its types.
