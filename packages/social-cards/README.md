# phil/social-cards

**What a page says of itself when it is shared: Open Graph, X and fediverse tags, a 1200×630 card drawn from a
template, and the tags of linked pages read and checked.**

phil/social-cards models the share metadata of a page as a value: its title and description, its canonical address,
its image and the text that replaces it, its Open Graph type, its locale and the locales it is also written in, its
site, its author and dates, and the accounts of the site and of the author on X and on the fediverse. `MetaTags`
writes them as the canonical link, the description, the Open Graph tags, the `article:` tags of an article, the card
of X and `fediverse:creator`, escaped for HTML.

A page without an image of its own gets a card: `GdCardRenderer` draws a 1200×630 PNG from a declarative
`CardTemplate` — a background colour or image, an accent band, a logo, the fonts and colours of the title and subtitle
—, fitting the title by shrinking it between two sizes and breaking its lines by the rules of its language, then
cutting it with an ellipsis when it still does not hold. The fonts are read by phil/fonts. The same template and
content always draw the same bytes, and `CardGenerator` keeps each card under a fingerprint of everything it shows.

The other way, `TagReader` reads the tags of a page of another site — bounded in bytes, tags and lengths, parsed by
the HTML5 parser of PHP —, `LinkPreview` makes the card of a link from them, `LinkFinder` finds the links a member
pasted in a text, and `TagCheck` says what is missing or wrong in the tags of a page.

The cards are drawn by GD, without text shaping: Latin, Greek, Cyrillic and the scripts of China, Japan and Korea draw
well; Arabic, Hebrew and the scripts of India do not, as [the cards](doc/cards.md#what-gd-draws-and-what-it-does-not)
explain.

It is a plain PHP library: PHP 8.5 with the ctype, DOM, GD, intl, libxml, mbstring and URI extensions, and
phil/fonts. In a
Symfony application, phil/social-cards-bundle sets the tags of each page from a Twig function, serves the cards,
draws them in a worker, fetches linked pages safely and checks a page from the command line.

## Status

| Part | State |
|---|---|
| `ShareMetadata`, `ShareImage`, `ShareType`, `TwitterCard`, `MetaTags` | Implemented and tested |
| `CardTemplate`, `CardFont`, `CardImage`, `Color`, `CardContent` | Implemented and tested |
| `LineBreaker`, `TextFitter`, `GdCardRenderer`, `CardGenerator`, the stores | Implemented and tested |
| `TagReader`, `PageTags`, `LinkPreview`, `TagCheck` | Implemented, tested and fuzzed |
| `LinkFinder` | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and the extensions used. |
| [Share tags](doc/tags.md) | The share metadata, its rules, and the tags written. |
| [Cards](doc/cards.md) | Templates, fonts, the fitting of the title, the drawing, fingerprints and stores. |
| [Previews and checks](doc/previews.md) | Reading the tags of a page, link previews, the links of a text, the check of a page's tags. |
| [Exceptions](doc/exceptions.md) | What is thrown, and when. |
| [Extension points](doc/extension-points.md) | The ports an application implements or replaces. |
| [Reference](doc/reference.md) | Every type of the library. |
| [Testing](doc/testing.md) | How the library is tested, benchmarked and fuzzed. |

## Quick start

```php
use Phil\SocialCards\Card\{CardContent, CardGenerator, CardTemplate, DirectoryCardStore, GdCardRenderer};
use Phil\SocialCards\{MetaTags, ShareMetadata};

$template = CardTemplate::fromArray(['title_font' => '/fonts/Inter-Bold.ttf', 'accent' => '#e11d48']);
$cards = new CardGenerator(new GdCardRenderer(), new DirectoryCardStore('/var/cards'));
$card = $cards->generate($template, new CardContent('Release notes', 'What changed this month', 'en'));

echo MetaTags::html(MetaTags::of(ShareMetadata::fromArray([
    'title' => 'Release notes',
    'url' => 'https://example.com/releases',
    'description' => 'What changed this month',
    'image' => ['url' => "https://example.com/cards/{$card->fingerprint}.png", 'alt' => 'Release notes', 'width' => 1200, 'height' => 630],
    'site_name' => 'Example',
    'fediverse_creator' => '@team@example.social',
])));
```

## Requirements

PHP 8.5 or later, with the ctype, DOM, GD (with FreeType), intl, libxml, mbstring and URI extensions, and
`phil/fonts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
