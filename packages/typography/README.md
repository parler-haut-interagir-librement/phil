# phil/typography

**Real quotation marks, dashes, French spaces and soft hyphens in your HTML, in every language, without touching the markup.**

phil/typography fixes the microtypography of text: straight quotes become the quotation marks of the language,
`--` and spaced hyphens become dashes, three dots an ellipsis, `1920 x 1080` a multiplication, `(tm)` a trademark
sign; a number and its unit are held together by a no-break space; French gets its spaces before `: ; ! ?` and inside
`« »`, by the rules of France or of Canada; long words get soft hyphens where the language's hyphenation patterns
allow a break. It works on HTML parsed as HTML5, rewriting only the text — elements, attributes and comments come back
as they were, and the text of `pre`, `code`, `kbd`, `script`, `style` and `textarea` is never changed.

It is for any PHP application that shows text written by people who type on a keyboard: a CMS, a blog, a forum, a
shop, a tool that lays out books or prints PDFs. It runs on the HTML a template or a Markdown converter produced,
or on plain text. A preset gives each locale the fixers it needs, in the right order, so `Typographer::for('fr_CA')`
is all most applications write. In Symfony, `phil/typography-bundle` adds the Twig filters and fixes the HTML of UJO
documents.

It is a plain PHP library modelled on JoliTypo, with no dependency beyond the `dom`, `intl`, `libxml` and `mbstring`
extensions. Hyphenation is Liang's algorithm, the one TeX uses, implemented in PHP; the patterns are imported from
hyph-utf8, for the 69 languages whose licence permits them in an MIT package, each recorded in `data/LICENSES`, and
generated for Czech, Hungarian, Armenian, Indonesian, Latvian, Macedonian and Romanian
from the orthographic rules of each language; the Armenian and Macedonian ones are experimental and served only when
asked for. Thai, which breaks lines without a hyphen, gets zero-width spaces
between its words from the ICU dictionary of `ext-intl`.

The design is small: a `Fixer` rewrites the text of a `Run`, the text nodes of one block; `Typographer` walks the DOM
and runs its fixers in order; `Preset` chooses them for a `Locale`; `Hyphenator` breaks words with the `Patterns` a
`Dictionary` loads.

## Status

| Part | State |
|---|---|
| `Typographer`: HTML fragments, whole documents and plain text; protected elements, configurable | Implemented and tested |
| Fixers: Unicode normalization, ellipsis, dimension, dash, smart quotes, apostrophe, comma, French spacing, legal signs, units, hyphenation, Thai word breaks | Implemented and tested |
| Quotation marks of 66 languages and regions, after the Unicode CLDR | Implemented and tested |
| French spacing by the rules of France and of Canada | Implemented and tested |
| Hyphenation patterns of 69 languages from hyph-utf8, with their exceptions and minimum lengths | Implemented and tested |
| Hyphenation patterns of Czech, Hungarian, Armenian, Indonesian, Latvian, Macedonian and Romanian, written from the rules of each language: rules, not a dictionary — less complete than the TeX patterns; Armenian and Macedonian experimental, off by default | Implemented and tested |
| Thai: zero-width spaces between words, from the ICU dictionary | Implemented and tested |
| Presets: the fixers of each locale | Implemented and tested |
| Emoji: every sequence left byte for byte | Implemented and tested |
| Hyphenation for Zulu | Left out: no patterns in hyph-utf8 |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [HTML and text](doc/html.md) | How HTML is read and written back, runs of text, protected elements, emoji. |
| [Fixers](doc/fixers.md) | Every fixer, what it changes and what it leaves alone. |
| [Locales](doc/locales.md) | Presets, quotation marks, dash styles and French spacing by locale. |
| [Hyphenation](doc/hyphenation.md) | Liang's algorithm, the languages and their licences, and patterns of your own. |
| [Exceptions](doc/exceptions.md) | `TypographyException` and its three exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that fixes text. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/typography
```

```php
use Phil\Typography\Typographer;

echo Typographer::for('fr_FR')->fix('<p>"Bonjour" , dit-il : l\'été arrive !</p>');
// <p>«&nbsp;Bon­jour&nbsp;», dit-il&nbsp;: l’été ar­rive !</p>  (narrow no-break space before "!", soft hyphens)

echo Typographer::for('en_US', hyphenation: false)->fixText('"Wait..." - she said, 1914-1918.');
// “Wait…”—she said, 1914–1918.
```

## Requirements

PHP 8.5 or later, with `ext-dom`, `ext-intl`, `ext-libxml` and `ext-mbstring`. Nothing else.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE). The hyphenation patterns imported under `data/hyphenation/` keep their own licences, given for each
language in [data/LICENSES](data/LICENSES); those written for this library are under its MIT licence.
