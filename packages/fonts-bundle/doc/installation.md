# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, `array_find()`, `new` without parentheses |
| `phil/fonts` | Reading font files, the `@font-face` rules and the licence records |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The bundle class, its configuration and its services |
| `symfony/console` 8.2 | The three commands |
| `symfony/filesystem` 8.2 | Writing the licence register and the stylesheet |
| `ext-brotli`, optional | Reading WOFF2 files |
| `twig/twig`, optional | The `fonts_css()` function |
| `phil/paged-media`, optional | `PdfFonts` |
| `phil/media-bundle`, optional | `MediaFonts`, when the bundle is also enabled |

## Installing

```sh
composer require phil/fonts-bundle
```

Without Symfony Flex, enable the bundle:

```php
// config/bundles.php
return [
    // …
    Phil\FontsBundle\PhilFontsBundle::class => ['all' => true],
];
```

## `ext-brotli` for WOFF2

WOFF2 compresses its tables with Brotli, which PHP does not decode on its own. The bundle reads WOFF2 files when the
`brotli` extension is loaded, and finds it by itself:

```sh
pecl install brotli
```

Without it, a WOFF2 file is listed by `phil:fonts:list` as "Not read: ext-brotli is not loaded", reading it directly
throws `FontUnreadable` with a message that says to install the extension or to use the WOFF, TrueType or OpenType file
of the font, and the `@font-face` rules take the face's metadata from those siblings. Keeping a `.woff` or `.ttf` file
beside each `.woff2` file is enough for a server without the extension.
