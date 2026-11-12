# Rendering

The controller renders live content through the application's Twig templates, and four Twig functions read
content, URLs and SEO tags from any template. Both need DoctrineBundle and TwigBundle in the kernel.

## Choosing the template

`Application\Rendering\TemplateResolver::resolve(?string $templateKey): string` returns:

- the template declared for the key under `phil_content.templates`;
- `phil_content.default_template` for a row without a key, with a key nobody declared, or of a row class that is
  not `TemplateCapable`;
- a `LogicException` when neither applies.

```yaml
phil_content:
    templates:
        news: content/news.html.twig
        event: content/event.html.twig
    default_template: content/page.html.twig
```

The template key usually lives on the unlocalized row, so every locale renders the same layout
([capabilities.md](capabilities.md#templatecapable)).

## What the template receives

| Variable | Holds |
|---|---|
| `content` | the merged live row, normalized: the keys of the normalized form (`excerptTitle`, `bodyHtml`, `seoTitle`, `templateData`, …; [pipeline.md](pipeline.md#the-normalized-form)) |
| `root` | the root entity |
| `reference` | its `ContentReference`; prints as `news:0192f3c4-…` |
| `locale` | the requested locale, as a string |
| `resolved_locale` | the locale served, as a string |
| `left_language` | whether the chain left the requested language, for the application to count or announce |

**`content.bodyHtml` is the only value a template may print unescaped** (`|raw`): it is purified again on every
read ([capabilities.md](capabilities.md#bodycapable)). Every other value is printed with Twig's escaping.

```twig
{# templates/content/news.html.twig #}
<title>{{ content.seoTitle ?? content.excerptTitle }}</title>
{{ content_seo(content) }}

<h1>{{ content.excerptTitle }}</h1>
{% if left_language %}<p class="notice">This page is not available in your language.</p>{% endif %}
<div class="body">{{ content.bodyHtml|raw }}</div>

<a href="{{ content_url(reference) }}">Permalink</a>
{% if content.link %}<a href="{{ content_link_url(content.link) }}">Read more</a>{% endif %}
```

## Twig functions

Declared by `Infrastructure\Twig\ContentExtension`, served lazily by `Infrastructure\Twig\ContentRuntime`, which
Twig builds only when a template calls one of them.

| Function | Returns |
|---|---|
| `content(reference, locale = null)` | the live content of a root along the locale's chain, normalized, or `null` |
| `content_single(resource_key, locale = null)` | the live content of a single kind (`phil_content.singles`), as `content()` reads it, or `null` before it is created or published; a kind not declared single throws `InvalidContent` |
| `content_archive(reference, locale = null)` | the publications of a root in a locale, `ArchivedVersion` values (`version`, `publishedAt`), the latest first, when its kind is under `phil_content.archives`; an empty list otherwise |
| `content_url(reference, locale = null, absolute = false)` | the URL of a root's current route in a locale, or `null` |
| `content_link_url(link, locale = null, absolute = false)` | the URL of a normalized `link`: its `url`, or the URL of the root it names (in its pinned `locale` when it has one), or `null` |
| `content_seo(content)` | the `<meta name="description">`, `<meta name="keywords">`, `<meta name="robots">` and `<link rel="canonical">` tags of a normalized content, as safe HTML |
| `content_schema(content, type = 'WebPage', url = null)` | the content as schema.org data, built by `ContentSchema`: a `WebPage`, or the type given (`Article`, `NewsArticle`, `BlogPosting`, with a `headline`), its name, description, `datePublished`, image (an absolute URL only), keywords, URL (else the canonical URL) and `inLanguage`, the request's locale |

- **A reference** is a `ContentReference`, its string form (`news:0192f3c4-…`), or a root entity.
- **The locale** defaults to the current request's; without a request, to the first declared locale (`en` when none is declared).
- **A page never fails on what it reads or links to.** `content()`, `content_url()` and
  `content_link_url()` answer `null` for whatever the domain refuses: nothing published along the chain, a
  locale not served or not spelled as a `Locale` (`fr-CA`), a malformed reference. A URL of a kind mounted on no
  route is `null` as well.
- **A reference to a kind no root declares still fails** (`InvalidContentMapping`): it is a mistake of the
  template or of the configuration, not a state of the content.
- **`content_seo()` escapes every value it prints**: the tags are safe to print raw because they are built here, not
  because the values were trusted. The description is `seoDescription`, or `excerptDescription` when there is none;
  `robots` lists `noindex` and `nofollow` when set; the keywords are joined with a comma and a space. Tags without a
  value are left out. It prints no `<title>`: the template writes its own.

```twig
{% set teaser = content('news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70') %}
{% if teaser %}
    <a href="{{ content_url('news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70') }}">{{ teaser.excerptTitle }}</a>
{% endif %}

<link rel="alternate" hreflang="en" href="{{ content_url(reference, 'en', true) }}">
```

## The archive of a content

`Infrastructure\Http\ArchiveController` serves one dated version of a content whose kind is under
`phil_content.archives`, with the content's own template. The application mounts it on a route of its own, with the kind
as the `resourceKey` default, and a `version` parameter; the content is the one its `slug` names in the request locale,
or the content of a single kind when the route has no slug:

```yaml
now_archive:
    path: /{_locale}/now/{version}
    controller: Phil\ContentBundle\Infrastructure\Http\ArchiveController
    defaults: { resourceKey: now_page }
    requirements: { version: '\d{1,9}' }
```

The template receives the variables of `ContentController` — `content`, `reference`, `locale`, `resolved_locale`, the
request locale since an archive does not fall back, and `left_language`, `false` — and two more: `archived`, the
`ArchivedVersion` shown, and `archive`, every publication of the locale. A kind without archive, a content not found, a
version the locale was not published from and a locale not served answer 404.

```twig
<h1>{{ content.excerptTitle }}</h1>
{% if archived is defined %}<p>As of {{ archived.publishedAt|date('F j, Y') }}</p>{% endif %}
<ol>
    {% for published in content_archive(reference) %}
        <li><a href="{{ path('now_archive', {version: published.version}) }}">{{ published.publishedAt|date('F Y') }}</a></li>
    {% endfor %}
</ol>
```

## URLs and locales

URLs are generated by `ContentUrlGenerator` on the generating route of the root's kind
([routing.md](routing.md#generating-urls)); `_locale` is added only when the locale differs from the request's.

## schema.org

```twig
<script type="application/ld+json">{{ content_schema(content, 'NewsArticle', content_url(reference, absolute: true))|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` writes `<` and `>` as escapes, so no value can close the script element. A type that is not a
schema.org type name — an upper-case letter, then letters — throws `InvalidArgumentException`.
