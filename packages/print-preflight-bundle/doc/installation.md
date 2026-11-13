# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, `new` without parentheses |
| `phil/print-preflight`, `phil/fonts` | The checks, the model, and the embedding permissions of fonts |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The bundle class, its configuration and its services |
| `symfony/console`, `symfony/filesystem` 8.2 | The command, and reading the file it checks |
| `phil/fonts-bundle`, optional | Fonts of a measured document named by family, weight and style |
| `phil/paged-media`, optional | `PdfLayout` |
| `phil/media-bundle`, optional | `MediaImages` |

## Installing

```sh
composer require phil/print-preflight-bundle
```

Without Symfony Flex, enable the bundle:

```php
// config/bundles.php
return [
    // …
    Phil\PrintPreflightBundle\PhilPrintPreflightBundle::class => ['all' => true],
];
```
