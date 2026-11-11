# phil/slug

**URL slugs made from titles and names, as a type that accepts nothing else.**

phil/slug gives slugs a value type. `Slug` is one URL segment of lowercase ASCII letters and digits, in words joined
by single hyphens, at most 255 characters long: `my-first-article`. It refuses a text that is not already a slug and
never fixes it, because `My-Article` beside `my-article` would be two addresses for one page. Sluggers make slugs
from a title or a name. `TransliteratingSlugger` transliterates to ASCII, in a locale when one is given, and cuts a
long slug at the last hyphen that fits, so no word is cut in two. A decorator changes what a slugger gives:
`LeadingWordsRemoved` drops a leading article, so directory names sort by their first meaningful word. No word is
dropped unless the application lists it.

It is for any PHP application that builds addresses from what people type: article titles, product names, directory
entries. An application names its sluggers, one per use, and `Sluggers` picks one by its name or gives the default.
In a Symfony application, phil/slug-bundle builds the named sluggers from the configuration, autowires them and adds
the `phil_slug` Twig filter.

It is a small PHP library built on the `AsciiSlugger` of `symfony/string`; with `ext-intl`, transliteration covers
every script, not only the Latin ones. The design rests on one interface, `Slugger`, the checked value type it
returns, and decoration to change a slugger without subclassing it.

## Status

| Part | State |
|---|---|
| `Slug`, `InvalidSlug` | Implemented and tested |
| `Slugger`, `TransliteratingSlugger`, `LeadingWordsRemoved` | Implemented and tested |
| `Sluggers`, `UnknownSlugger` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | The types, the rules of a slug, the sluggers and their decoration. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
$slugger = new TransliteratingSlugger();
$slugger->slug('Jeux de rôle')->value;                                    // 'jeux-de-role'

$names = new LeadingWordsRemoved($slugger, ['le', 'la', 'les', 'l', 'the']);
$names->slug("L'Ordre du Corbeau")->value;                                // 'ordre-du-corbeau'

$sluggers = new Sluggers(['default' => $slugger, 'names' => $names], 'default');
$sluggers->slug('The Guild', 'names')->value;                             // 'guild'

Slug::fromString('my-first-article');                                     // checked, never fixed
```

In a Symfony application, `phil/slug-bundle` declares the sluggers in the configuration.

## Requirements

PHP 8.5 or later, `symfony/string` and `symfony/translation-contracts`; `ext-intl` for the transliteration of every script.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
