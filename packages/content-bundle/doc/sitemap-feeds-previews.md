# Sitemap, feeds and previews

Two endpoints read what is published, one what is not yet, and a provider gives the sitemap its pages, registered with
DoctrineBundle and TwigBundle:

```yaml
# config/routes/phil_content.yaml
phil_content:
    resource: '@PhilContentBundle/config/routes.php'
```

| Route | Path | Controller |
|---|---|---|
| `phil_content_feed` | `/feeds/{resourceKey}.atom` | `FeedController` |
| `phil_content_preview` | `/preview/{reference}/{locale}` | `PreviewController` |

## What a locale publishes

`PublishedContents` reads the contents of a kind that have a live row **in a locale** — no fallback: a sitemap lists
a locale's own pages, a feed its own entries —, each in the latest version that locale was published from, merged as
its page shows it. It reads them through `PublishedRowRepository`, a page of contents in a few queries:

| Method | Reads |
|---|---|
| `find(PublishedRowQuery $query)` | one page of contents, filtered and ordered as the query says ([extension-points.md](extension-points.md#publishedrowrepository)) |
| `chunks($resourceKey, $locale, $size = 500)` | every content of the kind, in lists of at most `$size`, by root: the queries grow with the number of chunks, not of contents |
| `latest($resourceKey, $locale, $limit, $withSlug = false)` | the most recently published first, those never published last; with `$withSlug`, only those a route can serve |

Each `PublishedContent` gives:

| Method | Value |
|---|---|
| `publishedAt()` | when it was last published (`WorkflowCapable`), or `null` |
| `title()` | the excerpt's title, else the SEO title, else the reference |
| `summary()` | the excerpt's description, else the SEO description, or `null` |
| `isHiddenFromSitemaps()` | `seoHideInSitemap` or `seoNoIndex` |

## The sitemap

`phil/sitemap-bundle` serves and dumps the sitemap; `ContentUrlProvider`, a `Phil\Sitemap\UrlProvider` tagged
`phil_sitemap.url_provider` after the sitemap's `RouteUrlProvider`, gives it the contents, after the pages of the
routes: one section per kind under `phil_content.routes` — `news`, `page`; a kind whose key ends in an underscore and
digits, as `news_2026`, is the section `news-2026` (`Sitemap::sectionNamed()`), since that ending names the numbered
parts of a section —, each content in every served locale at its absolute URL (`ContentUrlGenerator`), with when it was
last published as `lastmod` and, when the same content is published in other locales, every locale as an alternate —
`fr_CA` written `fr-CA`. A content hidden from sitemaps, or without a URL in the locale, is left out. Each locale is
read in chunks, and the URLs of a chunk in one lookup of routes (`ContentUrlGenerator::generateAll()`). Without
`phil/sitemap-bundle`, the provider is registered and nothing asks it.

## Feeds

`/feeds/news.atom`, or a route of the application with `{_locale}` and a `title` default:

```yaml
news_feed:
    path: /{_locale}/feeds/{resourceKey}.atom
    controller: Phil\ContentBundle\Infrastructure\Http\FeedController
    defaults: { title: 'News' }
```

The feed holds the latest `feed_limit` entries of the kind in the request's locale that have a slug, read in one page
and their URLs in one lookup of routes, each with its URL as `<id>`,
its title, its summary and when it was published; its own `<updated>` is the latest of them. A kind mounted on no
route, or a locale not served, is a `404`.

## Preview links

```php
$url = $links->url($reference, Locale::fromString('fr'));   // PreviewLinks
```

```bash
bin/console phil:content:preview-link news:0192f3c4-… fr
```

A link carries when it expires — `preview.ttl` seconds later — and an HMAC-SHA256 of the reference, the locale and
that moment, signed with `preview.secret`. `PreviewController` renders the latest draft of the content in the locale
through the template of its key, with the variables of a live page and `preview` set to `true`, sent with
`X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store, private`. A link not signed, expired, for another
content or locale, or for a content without a draft there is a `404`.

## Not here

Publishing at a set time is not built in: the publication runs through `ContentManager::publish()`, which a scheduled
job of the application can call.
