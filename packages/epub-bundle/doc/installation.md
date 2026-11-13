# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, `new` without parentheses |
| `phil/epub` | The books and the EPUB writer |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The bundle class, its configuration and its services |
| `symfony/console`, `symfony/filesystem` 8.2 | The command, and the files kept or written |
| `symfony/messenger` 8.2, optional | `WriteEpub` and its handler |
| `psr/event-dispatcher`, optional | The `EpubStored` event; Symfony's event dispatcher provides it |
| `phil/fonts-bundle`, optional | `EpubFonts`, when the bundle is also enabled |
| `phil/media-bundle`, optional | The media storage and `MediaAssets`, when the bundle is also enabled |

## Installing

```sh
composer require phil/epub-bundle
```

Without Symfony Flex, enable the bundle:

```php
// config/bundles.php
return [
    // …
    Phil\EpubBundle\PhilEpubBundle::class => ['all' => true],
];
```
