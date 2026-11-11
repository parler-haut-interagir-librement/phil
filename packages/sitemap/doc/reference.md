# Reference

## Pages

| Type | Kind | Role |
|---|---|---|
| `Url` | `final readonly class` | A page: `loc`, `lastmod`, `changefreq`, `priority`, `alternates`, `images` |
| `ChangeFrequency` | enum | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never` |
| `Alternate` | `final readonly class` | The same page in another language: `hreflang` and `href` |
| `Image` | `final readonly class` | An image the page shows, by its address |
| `InvalidSitemap` | `InvalidArgumentException` | What a sitemap cannot hold |

| Rule | |
|---|---|
| An address — page, alternate, image, file | absolute, HTTP or HTTPS, 2048 characters at most, read by phil/url as a browser reads it; no user name or password, no IP address, no host of a single name; `localhost` and `.localhost` only with `allowLocalhost: true`; kept in its canonical form |
| A priority | 0.0 to 1.0, in steps of 0.1, written with one decimal |
| A page's images | 1000 at most |
| A `hreflang` | a language, maybe followed by a script or a region — `fr`, `fr-CA`, `zh-Hant-TW` — or `x-default`; `fr_CA` is written `fr-CA` |

Search engines expect a page's alternates to list every language of the page, its own included.

`Url::$loc`, `Alternate::$href` and `Image::$loc` hold the address as phil/url writes it: `HTTPS://Example.COM/a/../b`
is `https://example.com/b`, `https://bücher.example/` is `https://xn--bcher-kva.example/`. An address with a user name
is refused, since `https://example.com@evil.example/` leads to evil.example; so is one whose host is an IP address or
a single name, which no search engine reaches, `localhost` and the names under `.localhost` included. On a development
machine and in tests, pass `allowLocalhost: true` to `Url`, `Alternate`, `Image` and `XmlWriter::index()`: they then
take `http://localhost:8000/` and `http://app.localhost/`, never `http://127.0.0.1/`.

## Sections and files

| Type | Kind | Role |
|---|---|---|
| `UrlContainer` | interface | `add(Url, string $section)` |
| `UrlProvider` | interface | `provide(UrlContainer, ?string $section)`: adds the pages of a part of a site |
| `Sitemap` | `final class`, a `UrlContainer` | The pages by section; `sections()`, `files()`, `checkSection()`, `sectionNamed()` and `sectionOfFile()` |

A section is 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit, and not
ending in an underscore and digits (`news_1`), which name the numbered parts of a section. A
`Sitemap` built for one section keeps only that one's pages; a provider asked for one section may skip the others so
as not to load them.

`files($perFile)` gives each file its name: the section's own — `news` — when its pages fit one file, numbered parts
— `news_0`, `news_1` — when they do not. A file holds 50,000 pages at most, as the protocol says.
`Sitemap::sectionOfFile($name)` reads the section back from a file's name: `news` for `news` and for `news_1`.
`Sitemap::sectionNamed($key)` gives a provider the section of a key of its own that may end in an underscore and
digits — a kind of content, a genre —: that underscore becomes a hyphen, `news_2026` gives `news-2026`.

## XML

`XmlWriter::urlset($urls)` writes a file: each page with its `<loc>`, then what it knows of `<lastmod>`,
`<changefreq>` and `<priority>`, one `<xhtml:link rel="alternate">` per alternate and one `<image:image>` per image.
`XmlWriter::index($files)` writes the `sitemapindex`: the address of each file and the latest `<lastmod>` of its
pages, when one has any. Text is escaped for XML.
