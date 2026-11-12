# phil/locale-dimensions

**Locales, versions and declared fallback chains between locales, as plain value objects.**

phil/locale-dimensions gives an application the three things every translated or versioned record needs and that are
easy to get subtly wrong: a `Locale` with one spelling (`fr`, `fr_CA`), a `Version` counted from one, and a
`FallbackChain` — the locales tried, in order, when the requested one has nothing to serve. `DeclaredLocales` builds the
chains from the array an application declares, refusing a fallback it does not serve.

It exists because the same questions come back in every package that serves more than one language: content pages,
translated fields of a product or an event, a sitemap and its `hreflang` alternates, the strings of an interface. If
each answers on its own, `fr-CA` and `fr_CA` end up as two rows, a missing translation falls to the default language
while the regional variant sits one row away, and a version zero means "nothing yet" in one place and a real row in
another. One set of value objects keeps them in step.

It is a plain PHP library with no dependency. It stores nothing and reads no request: a bundle reads the declared
locales from its configuration, the application chooses the requested locale, and the chain says what to serve.

## Status

| Part | State |
|---|---|
| `Locale`, `Version` | Implemented and tested |
| `FallbackChain`, `DeclaredLocales` | Implemented and tested |
| `Exception\InvalidLocaleDimension` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reference](doc/reference.md) | Every public type: the spelling of a locale, the numbering of versions, chains and how they resolve, the declared locales, the refusals. |
| [Extension points](doc/extension-points.md) | How a bundle declares its locales, and how a reader walks a chain. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
use Phil\LocaleDimensions\{DeclaredLocales, Locale, Version};

$locales = DeclaredLocales::fromArray([
    'fr_CA' => ['fr', 'en'],
    'fr' => ['en'],
    'en' => [],
]);

$chain = $locales->chainFor(Locale::fromString('fr_CA'));
$chain->toString();                                       // 'fr_CA → fr → en'
$served = $chain->resolve([Locale::fromString('en'), Locale::fromString('fr')]);   // fr
$chain->leavesRequestedLanguage($served);                 // false: still French
$served->toLanguageTag();                                 // 'fr', for hreflang

Version::first()->next()->number;                         // 2
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
