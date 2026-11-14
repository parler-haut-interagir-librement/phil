# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_find()` and `array_any()` |

The library has no other dependency, not even an extension beyond PHP's core.

## PHP extensions

The tokenizers are plain PHP, and the highlighter uses no extension, even when one is installed. The syntax
highlighting extensions installable by PIE do not fit:

| Extension | Why it is not used |
|---|---|
| `yiipress/highlighter` (syntect) | It returns HTML with inline styles rather than tokens, so neither the `hl-` classes, the themes nor the line annotations would apply; it has no Twig grammar; and it is slower than these tokenizers on PHP, JavaScript, HTML, CSS, YAML and SQL. |
| `xberg-io/tree-sitter-language-pack` | It downloads each grammar from the network on first use, which a server must never do; it publishes no thread-safe build, which FrankenPHP needs; and its PHP API runs no highlighting query. |

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
