# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | As `phil/typography` |
| `phil/typography` | The typographers, fixers and hyphenation patterns |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and the services |
| `twig/twig` 3.21, optional | The `typography` and `typography_text` filters |
| `symfony/translation-contracts` 3.6 | The current locale, read from Symfony's `LocaleSwitcher` |
| `symfony/translation` 8.2, optional | The `LocaleSwitcher`, which gives the filters the locale of the request |
| `phil/ujo-bundle`, optional | The typographic pass over the HTML of UJO documents |

## Installing

```sh
composer require phil/typography-bundle
```

Symfony Flex enables the bundle; without Flex, add it to `config/bundles.php`:

```php
return [
    // ...
    Phil\TypographyBundle\PhilTypographyBundle::class => ['all' => true],
];
```

With no configuration, the default typographer is the preset of the kernel's default locale, with hyphenation; the
filters, and the HTML of UJO documents when `phil/ujo-bundle` is installed, are fixed in the locale of the request.
