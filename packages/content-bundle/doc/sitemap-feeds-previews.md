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

A link carries when it expires — `preview.ttl` seconds later — and a signature made with `preview.secret`.
`PreviewController` renders the latest draft of the content in the locale through the template of its key, with the
variables of a live page and `preview` set to `true`, sent with `X-Robots-Tag: noindex, nofollow` and
`Cache-Control: no-store, private`. A link not signed, expired, for another content or locale, for another scope, of
another version of the signature, or for a content without a draft there is a `404`.

### The scope of a link

The signature covers the reference, the locale, the expiry and **the scope** the link was made in:

| The application | Scope signed | A link opened… |
|---|---|---|
| with `phil/tenancy-bundle` and a tenant active | the tenant | in any other tenant is refused; on any host of the same tenant, it works |
| without a tenant active | the host of the link, without its case | on any other host is refused |

So a secret shared by several tenants or several sites does not let a link made for one show the draft of the content
of the same name in another. The link is made on the host of the router's request context: the request's in a page,
and in the console the host of `framework.router.default_uri`, which should be the host the link is read on. The
console's tenant is the one `phil/tenancy-bundle` reads from its command option.

### The signature

The signature is `v2.` followed by the HMAC-SHA256, in hexadecimal, under a key derived from the secret by
HKDF-SHA256 under `phil_content.preview_link`, of the version, the scope, the reference and the
locale, each written after its length, then the expiry. The version comes first so that a new format can be told from
an old one and refused cleanly; a signature that does not start with the current version is refused.

### Upgrading from 0.1

Links made by 0.1 sign neither a version nor a scope: they are refused, as an unsigned link is, with a `404`. Send the
link again from the administration or with `phil:content:preview-link`. `PreviewLinks::verify()` takes the host the
link was opened on as its last argument, which a controller of the application calling it passes
(`$request->getHost()`).

## Not here

Publishing at a set time is not built in: the publication runs through `ContentManager::publish()`, which a scheduled
job of the application can call.
