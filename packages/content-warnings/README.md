# phil/content-warnings

**Content warnings for games, books, articles and events, and the themes each reader folds or hides.**

phil/content-warnings describes what a piece of content may be hard to meet, and lets each reader decide what
happens to it. A catalogue lists the warning themes — gore, spiders, suicide, flashing lights — grouped in categories
and labelled in several languages. The library ships a base list of about seventy themes in twelve categories,
labelled in English, French, Spanish, German, Italian and Portuguese. Each application completes it, changes its
labels, removes themes or replaces it with a list of its own. A piece of content carries its warnings, one per theme,
each with an optional severity and a note. A reader's preferences say, per theme, per category or by default, whether
warned content is shown, folded behind its warning or hidden. The matcher turns the two into one decision per piece
of content.

It is for any application that publishes content some readers would rather approach with care or not at all: a game
catalogue, a library, a blog, an events calendar, a safety tool whose lines hide and whose veils fold. It is a plain
PHP library with no dependency beyond PHP's JSON and multibyte extensions. In a Symfony application, reach for
`phil/content-warnings-bundle`, which adds the configuration, the Doctrine column, the form fields, the folded display
in Twig and a store of preferences.

The design keeps keys and labels apart. Warnings and preferences name themes and categories by stable keys, so stored
data survives a reworded label, and a theme an application removes stays on the content that names it. Every value is
immutable and bounded: 100 warnings per piece of content, 2,000 themes per catalogue.

## Status

| Part | State |
|---|---|
| `Catalogue`, `Category`, `Theme`, `LocalizedText` | Implemented and tested |
| `CatalogueLoader`: definitions from arrays, PHP and JSON files, merged or replacing | Implemented and tested |
| `BaseCatalogue`: the base list in six languages | Implemented and tested |
| `ContentWarning`, `ContentWarnings`, `Severity`, kept as an array | Implemented and tested |
| `WarningPreferences`, `Preference`, `Display`, kept as an array | Implemented and tested |
| `WarningMatcher` and `Decision` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Catalogue](doc/catalogue.md) | Themes, categories, labels, the base list and definitions that complete or replace it. |
| [Warnings](doc/warnings.md) | The warnings of a piece of content, their severity and note, and how they are stored. |
| [Preferences and decisions](doc/preferences.md) | A reader's preferences, how a warning finds its display, and the decision for a piece of content. |
| [Extension points](doc/extension-points.md) | What an application chooses, completes or relies on. |
| [Testing](doc/testing.md) | The library's tests, benchmarks and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/content-warnings
```

```php
use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\CatalogueLoader;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningMatcher;
use Phil\ContentWarnings\WarningPreferences;

$catalogue = new CatalogueLoader()->apply(['remove' => ['clowns']], BaseCatalogue::load());

$warnings = ContentWarnings::of(
    new ContentWarning('gore', Severity::Severe, 'The final battle'),
    new ContentWarning('spiders'),
);

$preferences = WarningPreferences::defaults(Display::Show)
    ->withCategory('phobias', Display::Fold)
    ->withTheme('gore', Display::Hide, Severity::Moderate);

$decision = new WarningMatcher($catalogue)->decide($warnings, $preferences);
$decision->display;                          // Display::Hide
$catalogue->theme('spiders')?->label('fr');  // "Araignées"
```

## Requirements

PHP 8.5 or later with `ext-json` and `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
