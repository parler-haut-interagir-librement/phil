# phil/sitemap

**Sitemap XML for search engines, built from checked pages and cut to the protocol's limits.**

phil/sitemap models a sitemap as values. A `Url` is a page with what the sitemap protocol lets a site say of it: when
it last changed, how often it changes, how it ranks, the same page in other languages and the images it shows.
Providers add pages to a `Sitemap` by section, and the sitemap cuts each section into files of 50,000 pages at most.
`XmlWriter` writes each file's `urlset`, with `xhtml:link` alternates and the image extension, and the
`sitemapindex` that lists the files. It neither serves nor stores the files.

It is for any PHP site that wants search engines to find its pages, in one language or several. Each value checks
what the protocol requires: absolute HTTP or HTTPS addresses of 2,048 characters at most, a priority in steps of
0.1, a known change frequency, a valid `hreflang`. A mistake fails where the page is built, before any invalid XML
is written. In a Symfony application, phil/sitemap-bundle reads pages from routes and providers, serves
`/sitemap.xml` and dumps the files; phil/content-bundle and phil/directory-bundle give it their published pages.

It is a plain PHP library with no dependency beyond `ext-filter`. `UrlProvider` is the extension point: each provider
knows one part of a site and, asked for one section, may skip loading the others.

## Status

| Part | State |
|---|---|
| `Url`, `ChangeFrequency`, `Alternate`, `Image` | Implemented and tested |
| `Sitemap`, `UrlContainer`, `UrlProvider` | Implemented and tested |
| `XmlWriter`: `urlset` and `sitemapindex` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | The types, their rules, sections and files, the XML written. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
$sitemap = new Sitemap();
$sitemap->add(new Url(
    'https://example.com/fr/calendrier',
    new DateTimeImmutable('2026-11-04 12:00'),
    ChangeFrequency::Weekly,
    0.8,
    [new Alternate('fr', 'https://example.com/fr/calendrier'), new Alternate('en', 'https://example.com/en/calendar')],
    [new Image('https://example.com/calendar.png')],
), 'pages');

foreach ($sitemap->files() as $name => $urls) {
    file_put_contents("public/sitemap.$name.xml", XmlWriter::urlset($urls));
}
```

A `UrlProvider` adds the pages of a part of the site; in a Symfony application, `phil/sitemap-bundle` collects the
providers, serves the sitemap and writes it as files.

## Requirements

PHP 8.5 or later, with the filter extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
