# phil/number-words

**Numbers in words, in any language ICU spells: cardinals, ordinals, gender and amounts of money, corrected where ICU
differs from usage.**

phil/number-words is a typed facade over the spelling rules of ICU (`NumberFormatter::SPELLOUT` of `ext-intl`). It
says a cardinal on its own (« vingt et un », « eins ») or agreeing with the gender of a noun (« vingt et une »,
« veintiún », « ein »), an ordinal in its gender and number (« premières »), and an amount of money in words with its
units and sub-units (« cent vingt dollars et cinq cents »), for a cheque, a certificate, a contract or a text read
aloud.

ICU knows the numbers of dozens of languages; for French, English, Spanish, German, Italian and Portuguese the library
adds what ICU leaves out or gets wrong: the plural of `cents` and `quatre-vingts` before `millions`, French
hyphenation in the traditional spelling or in the 1990 rectifications, the soft hyphens ICU puts inside long words,
the partitive after `million` (« un million d'euros », « un millón de dólares »), the singular of zero in French, and
the names of common currencies. Nothing falls back in silence: a locale ICU does not spell, a gender or a form the
language lacks, a number too large for words, each throws an exception that says so.

It is a plain PHP library built on `ext-intl`.

## Status

| Part | State |
|---|---|
| Cardinals, counting and agreeing with a gender; ordinals, gendered and plural | Implemented and tested |
| Amounts of money in words, units and sub-units, for ten currencies in six languages, or names passed in | Implemented and tested |
| French corrections: plurals before large nouns, traditional or 1990 hyphenation, elision of `de` | Implemented and tested |
| Partitives in Spanish, Italian and Portuguese, soft hyphens, verbose English | Implemented and tested |
| Exceptions for a missing extension, locale, rule set, currency, or a number out of range | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Spelling numbers](doc/spelling.md) | Cardinals, ordinals, amounts, options, and what each language corrects. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/number-words
```

```php
use Phil\NumberWords\Gender;
use Phil\NumberWords\NumberWords;

$words = new NumberWords('fr_CA');
$words->cardinal(21);                      // vingt et un
$words->cardinal(21, Gender::Feminine);    // vingt et une
$words->ordinal(1, Gender::Feminine);      // première
$words->amount('120.05', 'CAD');           // cent vingt dollars et cinq cents
$words->amount('200000000', 'EUR');        // deux cents millions d'euros
```

## Requirements

PHP 8.5 or later, with `ext-intl`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
