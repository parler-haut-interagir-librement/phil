# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | `Dom\HTMLDocument`, the HTML5 parser of PHP, readonly classes, typed constants |
| `ext-dom` | HTML is parsed and written back as HTML5 |
| `ext-intl` | `UnicodeNormalization` uses `Normalizer`, and `WordBreak` the Thai dictionary of `IntlBreakIterator` |
| `ext-libxml` | Its flag keeps the parser quiet on whole documents |
| `ext-mbstring` | Text is read character by character, and words are lowered for hyphenation |

The library has no other dependency. Its hyphenation patterns are text files under `data/hyphenation/`, read on first
use and kept by the `Dictionary` that read them.

## Installing

```sh
composer require phil/typography
```

It is a library, not a bundle: there is nothing to register. A Symfony application installs
`phil/typography-bundle` for the configuration, the Twig filters and the UJO integration, or builds a `Typographer`
where it needs one.
