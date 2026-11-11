# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/markup` | ^0.1 | The formats, the levels, the readers, the compiler and the purifier |
| `phil/ujo` | ^0.1 | The UJO renderer and the JSON the converter writes |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |
| `symfony/translation-contracts` | ^3.6 | The current locale of the smart punctuation, read from Symfony's `LocaleSwitcher` |
| `phil/typography` | ^0.1, optional | Smart punctuation in every format, only when it is installed |
| `symfony/form` | ^8.2, optional | The form type, registered only when Symfony Form is installed |
| `twig/twig` | ^3.21, optional | The filters, registered only when Twig is installed |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\MarkupBundle\PhilMarkupBundle::class => ['all' => true],
];
```

Without configuration, every format is enabled, Markdown is the default one, the filters purify at `all`, and every
format gets smart punctuation when `phil/typography` is installed: see [Configuration](configuration.md).
