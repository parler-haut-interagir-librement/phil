# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, enums, typed constants |
| `ext-ctype` | The analyser classifies the characters around a delimiter |
| `ext-dom` | The HTML reader parses HTML with `Dom\HTMLDocument`, the HTML5 parser of PHP |
| `ext-filter` | The address of a BBCode `[email]` tag and of a Markdown autolink is validated |
| `ext-libxml` | The flag that keeps the HTML5 parser from reporting the errors of the HTML it reads |
| `ext-mbstring` | Labels, anchors and titles are cut and lowered by character |
| `phil/ujo` | The documents the readers write, their checking and their HTML |
| `symfony/html-sanitizer` 8.2 | The policies of `SanitizerPurifier` |
| `symfony/yaml` 8.2 | The front matter of Markdown, and the YAML a dialect reads with `FrontMatter::yaml()` |

Doctrine ORM 3.7 and DBAL 4.5 are optional: only an application that stores a `MarkedUpText` as an embeddable needs
them, and it requires them itself.

## Installing

```sh
composer require phil/markup
```

It is a library, not a bundle: there is nothing to register. In a Symfony application, `phil/markup-bundle`
registers `DefaultCompiler`, `SanitizerPurifier` and `Markup` as services, and the Twig filters.
