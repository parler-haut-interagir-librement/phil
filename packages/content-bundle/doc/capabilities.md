# Capabilities

What a row holds beyond its dimension is declared by the **capabilities** it implements. Each capability is an
interface in `Phil\ContentBundle\Domain\Capability` paired with a trait, a `CapabilityMapping` for its columns
in `Infrastructure\Doctrine\Mapping`, and a data mapper, a merger and a normalizer in `Application`. A row class
implements the ones it needs; the others add no column to its table.

```php
class PageDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable, RoutableCapable
{
    use RoutableTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
```

**The traits carry no mapping.** The columns of each capability live in the infrastructure, in its
`CapabilityMapping`, and are added to every mapped class that implements the interface when Doctrine loads it
([storage.md](storage.md#the-mapping-listener)). The pipeline recognises a capability by `instanceof`.

## Overview

| Capability | Values | Written on | Columns |
|---|---|---|---|
| `TemplateCapable` | template key, field values | key on the unlocalized row, values on the localized row | `template_key` `VARCHAR(64)`, `template_data` JSON |
| `WorkflowCapable` | workflow place, last publication date | the workflow, on the localized row; the date on both | `workflow_place` `VARCHAR(32)`, `workflow_published` `datetime_immutable` |
| `SeoCapable` | title, description, keywords, canonical URL, `noindex`, `nofollow`, hidden from the sitemap | localized row | `seo_title` `VARCHAR(255)`, `seo_description` text, `seo_keywords` JSON, `seo_canonical_url` `VARCHAR(2048)`, `seo_no_index`, `seo_no_follow`, `seo_hide_in_sitemap` nullable booleans |
| `ExcerptCapable` | title, description, image reference | title and description localized, image shared | `excerpt_title` `VARCHAR(255)`, `excerpt_description` text, `excerpt_image` `VARCHAR(2048)` |
| `LinkCapable` | a `Link`: another content root, or a `Url` | localized row | `link_resource_key` `VARCHAR(64)`, `link_resource_id` `VARCHAR(64)`, `link_locale` (`phil_content_locale`, 12), `link_url` `VARCHAR(2048)` |
| `RoutableCapable` | a `Slug` | localized row | `slug` `VARCHAR(255)` |
| `BodyCapable` | a `Body`: source, `MarkupFormat`, HTML | localized row | `body_source` text, `body_format` `VARCHAR(16)`, `body_html` text |

Every column is nullable except `template_data` and `seo_keywords`, which hold an empty JSON array when
nothing is set. "Written on" is where the bundle's data mapper writes the value; the merger reads both rows, so
an application that writes a value on the other row directly is still read ([pipeline.md](pipeline.md)).

## Values are refused, never corrected

A trait setter or a value object refuses what cannot be stored with `InvalidContent` (a `DomainError` of
`phil/ddd-bundle`, and an `InvalidArgumentException`). It never trims, truncates or rewrites: a row that
silently rewrote what it was handed would store something its caller never saw. Trimming, and reading an emptied
field as `null`, is the data mappers' work on submitted data.

`CapabilityText` holds the checks of free text:

| Check | Used for | Refuses |
|---|---|---|
| `text($value, $field)` | descriptions | invalid UTF-8; an empty or untrimmed string (an absent value is `null`); control characters other than line breaks and tabs |
| `line($value, $field)` | titles, keywords | what `text()` refuses; line breaks and tabs; more than `CapabilityText::LINE_LENGTH` (255) characters |
| `reference($value, $field)` | the excerpt image | anything but 1 to 2048 printable ASCII characters without spaces |

## TemplateCapable

```php
public function getTemplateKey(): ?string;
public function setTemplateKey(?string $templateKey): void;
/** @return array<string, mixed> */
public function getTemplateData(): array;
/** @param array<string, mixed> $templateData */
public function setTemplateData(array $templateData): void;
```

The template key chooses the Twig template ([rendering.md](rendering.md)); the field values are a free document
the bundle never renders. **One JSON document rather than one column per field**, because the fields are the
template's, and a template changes without a migration. The trait does not validate either value. Merging is
shallow: a field holding a list or a nested block is one value, replaced whole by the localized row.

## WorkflowCapable

```php
public function getWorkflowPlace(): ?string;
public function setWorkflowPlace(?string $workflowPlace, array $context = []): void;
public function getWorkflowPublished(): ?DateTimeImmutable;
public function setWorkflowPublished(?DateTimeImmutable $workflowPublished): void;
```

The getter and setter of the place follow the method marking store of `symfony/workflow` (`property:
workflowPlace`, single state). The row only stores where it stands; the workflow is `ContentWorkflow`
([publication.md](publication.md)). **A row class must be `WorkflowCapable` to be published**: publishing a
row without it throws `TransitionRefused`.

`workflow_published` is a `datetime_immutable`, which stores no time zone: the publisher writes it in UTC, and a
draft row with a publication date belongs to a frozen version.

## SeoCapable

Getters and setters for `seoTitle` (a line), `seoDescription` (text), `seoKeywords` (a list of lines),
`seoCanonicalUrl` (a `Url`), and three directives, `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap`.

- **Keywords** are kept in order, a repeated keyword once; a keyword holding a comma is refused, since commas
  separate keywords in the `<meta>` tag.
- **A directive has three states.** `null` says the row does not set it, so the merged row keeps what the shared
  row said; `true` and `false` are explicit, so a localized `false` lifts a shared `true`. A directive nobody
  set reads as `false` once normalized.

## ExcerptCapable

Getters and setters for `excerptTitle` (a line), `excerptDescription` (text) and `excerptImage` (a reference).

**The image is a reference, not a media model**: a URL, a public path, or a media library's identifier, at most
2048 printable ASCII characters. What it points to, and how it is resized and served, is the application's.
`ExcerptDataMapper` writes it on the unlocalized row, since an image is usually the same in every language, and
the title and description on the localized row. The merger copies each value only when the source sets it, so a
localized excerpt keeps the shared image.

## LinkCapable

```php
public function getLink(): ?Link;
public function setLink(?Link $link): void;
```

A `Link` points to another content root or to an absolute URL, never both, never neither:

```php
use Phil\LocaleDimensions\Locale;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\Url\Url;

$toArticle = Link::toContent('article', '0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70');          // follows the reader's locale
$toFrench = Link::toContent('article', '0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70', Locale::fromString('fr'));
$outside = Link::toUrl(Url::fromString('https://example.org/'));
$toArticle->isToContent();   // true
```

- **A content root is named by a resource key and an identifier, both the application's**, with the shapes of a
  `ContentReference`; an optional locale pins the language of the target. The link is resolved when it is
  rendered, so it follows the target when its slug changes.
- **The shape is checked; whether the target root exists is the application's question**, asked when it submits
  or renders the link.
- Four columns rather than one document, so a query finds every row that points to one root by
  `(link_resource_key, link_resource_id)`. `setLink()` writes the four together, and the link is merged whole: a
  localized URL never borrows the locale of a shared content link.

A URL — a link to another site, a canonical URL — is a `Url` of `phil/url`: an absolute `http` or `https` address,
at most 2048 characters, without credentials, normalised — scheme and host in lowercase, an international host in
its ASCII form, the default port dropped, the root path `/` —, so two spellings of one address are stored as one.
The data mappers turn its refusal into `InvalidContent`. A link inside the application is a content root, not a
URL.

## RoutableCapable

```php
public function getSlug(): ?Slug;
public function setSlug(?Slug $slug): void;
```

`Slug` is `Phil\Slug\Slug`, from `phil/slug`. `Slug::fromString()` accepts one URL segment: lowercase ASCII
letters and digits in words joined by single hyphens, at most 255 characters (`my-first-article`); a submitted
`slug` that is not one is an `InvalidContent`. Turning a title into a slug — transliteration, the choice of words —
is the application's, with a slugger of `phil/slug` or of `phil/slug-bundle` when it wants one.

**A slug is per locale, and not unique in the row table.** Every version and both stages of one locale carry the
same slug, so a unique index there would refuse the copy that publishing makes. Uniqueness lives in the route
table instead: an edit is refused with `SlugTaken` when its slug is the current address of another existing root
of the same kind in the same locale ([routing.md](routing.md)).

## BodyCapable

```php
public function getBody(): ?Body;
public function setBody(?Body $body): void;
```

The main text of an article or a page is a `BodyCapable` rather than a field of the template's data. The
template's values are a free document the bundle never renders; the body is the one text the bundle compiles,
and so the one place where it produces HTML. As a capability, it has typed columns and a single path to the
screen:

1. **On write**, `BodyDataMapper` compiles the source — Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO or the format
   of an add-on through a UJO document, HTML as written, or plain text —, through the `MarkupCompiler` port, purifies
   the result through the `HtmlPurifier` port, and stores the source, its format and the purified HTML. **A draft keeps
   the format its author writes in.** A UJO source that is not a valid document is refused with `InvalidContent`. The
   raw HTML of an editor granted [`raw_html_role`](configuration.md#raw_html_role) is rendered, as it will be published;
   anyone else's is shown as text. The page is not compiled again on each read.
2. **On publication**, `ContentPublisher` stores on the live row the body the `BodyConverter` port makes of the draft's:
   **the source converted into UJO** — UJO (Universal JSON Output), the block document format of `phil/ujo` —, the
   format `ujo`, and the HTML compiled from that UJO, purified at `Level::All`; the UJO's `time` is the moment of the
   publication. Every published text is in one format, whatever its draft was written in, and a block editor opens it
   ([The lifecycle of a body](#the-lifecycle-of-a-body)).
3. **On read**, `BodyNormalizer` purifies the stored HTML again before handing it out. A row written around the
   data mapper — by a migration, by hand — is purified all the same, and a stricter policy applies to every row
   the day it is configured. **The `bodyHtml` of the normalized row is the only body HTML a template may print
   unescaped.**

   That purification is paid on every read, and nothing caches it: about 60 µs per paragraph of rich HTML, a
   millisecond for a typical article, ten for a long read. One page reads one body and does not notice; a listing, a
   feed or an API endpoint that normalizes many contents pays it once per content. Such a page normalizes only what
   it prints — an excerpt needs no body —, or caches its own response. A cache keyed by the row's version would not
   be safe, since a row written around the data mapper keeps its version.

`Body::of(string $source, MarkupFormat|string $format, string $html)` keeps the source as written, leading spaces
included (they mean something in Markdown), and the format by its name; it refuses a source that is only whitespace and
a source or HTML that is not valid UTF-8. `MarkupFormat` is `phil/markup`'s: `Markdown` (`markdown`), `Html` (`html`),
`BBCode` (`bbcode`), `MediaWiki` (`mediawiki`), `DokuWiki` (`dokuwiki`), `Creole` (`creole`), `Text` (`text`) and `Ujo`
(`ujo`); a format an add-on brings to the compiler is given by its name, 16 characters at most, the length of the
column. A source in another markup language is converted before it is imported. The source, its format and its HTML
travel together through the merger, so the HTML always matches the source it was compiled from.

The default adapters are `phil/markup`'s, which describes them:

- **`DefaultCompiler`**: Markdown, BBCode, MediaWiki, DokuWiki, Creole and the formats of add-ons read by the library's
  own analyser into UJO, and UJO, rendered by `phil/ujo`, raw HTML in the written formats escaped since the level is not
  given to `compile()`, and unsafe links dropped; HTML as written; plain text by the library's own compiler.
- **`SanitizerPurifier::defaultPolicies()`** (`symfony/html-sanitizer`): the body is purified at `Level::All`,
  the sanitizer's safe elements; links in `http`, `https`, `mailto` or relative; images in `http`, `https` or
  relative; scripts, styles, frames, forms and event handlers removed; **no cap on the input length**.

- **`UjoBodyConverter`**, the bundle's `BodyConverter`: the reader of the draft's format, from `DefaultCompiler`,
  reads the source into a UJO document **at `Level::Forum`** — the level `compile()` reads at, so the published text
  says what the draft showed: raw HTML written in Markdown is text, never a `raw` block, and a `raw` block written in
  UJO becomes a paragraph showing its HTML as text —; the document's `time` is set to the moment of the publication,
  in milliseconds since the Unix epoch; `phil/ujo`'s encoder writes it as JSON, and that JSON is compiled and
  purified. HTML and plain text, which the draft compiles as written, are converted into blocks by their readers;
  what UJO has no block for is dropped.
- **Raw HTML, for a trusted role only.** With [`raw_html_role`](configuration.md#raw_html_role) set, a publisher
  granted that role by Symfony's authorization checker has the source read at `Level::All`: the raw HTML of the
  written formats and the `raw` blocks of UJO are published as `raw` blocks, their HTML kept as `phil/ujo`'s `raw`
  profile allows — the elements of a page's structure, no script, style, class or event — and purified again at
  `Level::All`. **The draft of an editor granted that role shows it the same way**: `BodyDataMapper` compiles their
  source for `Level::All`, through `compileFor()` of a `LevelAwareCompiler`, so the preview of their draft renders the
  raw HTML as its publication will; every other editor's draft shows it as text. Without the key, the default, nobody
  writes raw HTML; a save or a publication from the console, which has no signed-in user, never does.

The application replaces any of them by aliasing `MarkupCompiler`, `HtmlPurifier` or `BodyConverter` to its own
service ([extension-points.md](extension-points.md#markup-ports)). The converter reads through `DefaultCompiler`
even when `MarkupCompiler` is aliased elsewhere. A template field holding rich text is the application's to compile
and purify, with the same services.

### The lifecycle of a body

| Row | `body_source` | `body_format` | `body_html` |
|---|---|---|---|
| Draft, as written | the author's text | the author's format: `markdown`, `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `html`, `text`, `ujo` or a format of an add-on | compiled from the text, purified |
| Live, once published | the UJO JSON the text converts into, its `time` the moment of the publication | `ujo` | compiled from the UJO, purified |

- **Publishing never touches the draft row.** The draft is frozen with its version and keeps its text; the next edit
  forks a version in the format it is written in, and the live row keeps the UJO of the version readers are served.
- **The conversion runs before anything changes.** A body that cannot be read — UJO written around the data mapper
  that is not a valid document — makes `publish()` throw `InvalidContent`, and the locale stays where it was.
- **Removing a draft puts the frozen draft row of the published version back**: the draft holds again the text its
  author published, in its own format. Only when that row no longer exists — history pruned, or a locale published
  before the conversion existed — is the live row put back, its body the published UJO, format `ujo`.
- **The columns do not change.** The UJO is stored in `body_source`, a text column without a length, and `ujo` fits
  the `VARCHAR(16)` of `body_format`. A live row published before the conversion existed keeps its format until it
  is published again; nothing needs migrating.

## Adding a capability of your own

An application capability is an interface and a trait, a `CapabilityMapping` subclass for its columns, and a
data mapper, a merger and a normalizer. All four are tagged by autoconfiguration and run beside the bundle's.
**A capability without a merger is neither read through the aggregator nor copied by publishing or forking.**
The full recipe is in [extension-points.md](extension-points.md#an-application-capability).
