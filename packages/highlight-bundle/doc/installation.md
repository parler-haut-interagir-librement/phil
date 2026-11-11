# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | As `phil/highlight` |
| `phil/highlight` | The highlighter and its languages |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and the services |
| `twig/twig` 3.21, optional | The `highlight` filter |
| `phil/ujo-bundle`, optional | The code blocks of UJO documents coloured on the server |
| `symfony/asset-mapper` 8.2, optional | The theme stylesheet, mapped as `phil-highlight/highlight.css` |

## Installing

```sh
composer require phil/highlight-bundle
```

Symfony Flex enables the bundle; without Flex, add it to `config/bundles.php`:

```php
return [
    // ...
    Phil\HighlightBundle\PhilHighlightBundle::class => ['all' => true],
];
```

With no configuration, the highlighter reads every language `phil/highlight` ships, and the code blocks of UJO
documents are coloured when `phil/ujo-bundle` is installed.

## The stylesheet

With AssetMapper, the bundle maps its `assets/styles` folder as `phil-highlight/`; a layout links the theme:

```twig
<link rel="stylesheet" href="{{ asset('phil-highlight/highlight.css') }}">
```

With another pipeline, import `vendor/phil/highlight-bundle/assets/styles/highlight.css`. The themes of
`phil/highlight`, in `vendor/phil/highlight/themes/`, colour the same classes and serve as well: see
[Theme](theme.md).
