# Extension points

The library is plain PHP and declares no service. What an application writes is providers; what it may replace is
the container they add to.

## `UrlProvider`

`Phil\Sitemap\UrlProvider` — adds the pages of a part of a site.

| Method | Contract |
|---|---|
| `provide(UrlContainer $urls, ?string $section): void` | Adds its pages, each in a section; asked for one section, it may add only that one's pages and skip loading the others — `null` asks for all |

## `UrlContainer`

`Phil\Sitemap\UrlContainer` — where providers add pages. `Sitemap` is the shipped one: it keeps the pages by section,
only the section asked for when built for one, and cuts them into files.

| Method | Contract |
|---|---|
| `add(Url $url, string $section): void` | Keeps the page in the section; a section is checked by `Sitemap::checkSection()` |

A container of the application's own — one that streams pages straight to a file, say — keeps the rules of sections
so that `XmlWriter` and the file names still hold. See [Reference](reference.md).
