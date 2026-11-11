# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_find()` and `array_any()` |

The library has no other dependency, not even an extension beyond PHP's core.

## Installing

```sh
composer require phil/highlight
```

It is a library, not a bundle: there is nothing to register. Build a `Highlighter` where code is shown, once, and keep
it: `Highlighter::default()` builds every tokenizer, which is cheap, and none holds state between two sources.

## The themes

The HTML holds classes only; a theme colours them. Three ship in `themes/`:

| File | Colours |
|---|---|
| `themes/light.css` | For a light background |
| `themes/dark.css` | For a dark background |
| `themes/auto.css` | Light or dark as the system prefers, unless an ancestor carries `data-theme="light"` or `data-theme="dark"` |

Serve the one you want from `vendor/phil/highlight/themes/`, copy it into the application's assets, or write your
own ([html-and-themes.md](html-and-themes.md)). `phil/ux-bundle`'s stylesheet colours the same classes with its own
colour roles: an application that loads it needs no theme from here.
