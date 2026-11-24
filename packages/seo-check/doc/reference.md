# Reference

Every public type of the package, with its role. Names are relative to `Phil\SeoCheck`.

## The check

| Type | Kind | Role |
|---|---|---|
| `SeoChecker` | `final readonly class` | Runs the rules on a page: `check(CheckedPage $page, string $locale = 'en'): Report`. The default rules when none are given; a rule that throws becomes the warning `check.rule-failed`. |
| `CheckedPage` | `final readonly class` | The page as it was published — `canonicalUrl`, `locale`, `indexable`, `indexableByChannel` — and its renderings; `of()` reads each `RenderedPage`, `primary()`, `isIndexableIn(?string $channel)`. |
| `RenderedPage` | `final readonly class` | A rendering as the application gives it: address, HTML, status, headers in lower case, channel; `header(string $name)`. |
| `Rendering` | `final readonly class` | A `RenderedPage` and the `PageMarkup` read from it. |
| `PageCheckFactory` | `final readonly class` | `fromEvent(PagePublished $event): ?CheckedPage`, from `RenderedPages` and, when given, `ChannelIndexing`. |
| `Report` | `final readonly class`, `Countable` | The findings in the order of the rules: `findings(Severity $minimum)`, `withSeverity()`, `ofRule()`, `message()`, `translate()`, `inLocale()`, `isEmpty()`, `toArray()`. No overall score. |
| `Finding` | `final readonly class` | One point to correct: rule, severity, message, and the subject it is about; `toArray(string $message)`. |
| `Severity` | `enum` | `Error`, `Warning`, `Info`; `isAtLeast(Severity $minimum)`. |
| `Message` | `final readonly class` | A key of the domain `phil_seo_check` and the values of its placeholders. |

## The ports

| Type | Kind | Role |
|---|---|---|
| `Contract\SeoRule` | interface | One check: `name()`, `check(CheckedPage $page): iterable<Finding>`. |
| `Contract\RenderedPages` | interface | The HTML of a page without a request: `renderingsOf(PagePublished $event)`, `renderingAt(Url $url)`. |
| `Contract\LinkStatuses` | interface | The status code of each link known: `statuses(list<Url> $links): array<string, int>`. |
| `Contract\ChannelIndexing` | interface | Whether a page is indexable in a channel: `isIndexable(PagePublished $event, string $channel): ?bool`. |
| `Contract\ReportRenderer` | interface | `render(Report $report): string`. |

## The rules

| Type | Name | Role |
|---|---|---|
| `Rule\DefaultRules` | — | `create(?RenderedPages $pages = null, ?LinkStatuses $statuses = null): list<SeoRule>`, in the order of the report. |
| `Rule\HttpStatus` | `http-status` | Every rendering answers with a success; the page at its own address does not redirect. |
| `Rule\PageSize` | `page-size` | The page is no larger than the reader reads. |
| `Rule\DocumentTitle` | `document-title` | One title, not empty, between `MIN_LENGTH` (10) and `MAX_LENGTH` (60) characters. |
| `Rule\MetaDescription` | `meta-description` | One meta description, not empty, between `MIN_LENGTH` (50) and `MAX_LENGTH` (160) characters. |
| `Rule\DocumentLanguage` | `document-language` | The `lang` of the document and `Content-Language` name the published language. |
| `Rule\SingleH1` | `single-h1` | One first-level heading, with text. |
| `Rule\Canonical` | `canonical` | One absolute canonical address, the published one, on every path to the page. |
| `Rule\Indexing` | `indexing` | The indexing directives agree with the flag « indexable », by channel. |
| `Rule\Hreflang` | `hreflang` | Valid and reciprocal translations, `MAX_PAGES` (50) read at most. |
| `Rule\CrawlableLinks` | `crawlable-links` | Links with an `href`, not `javascript:`. |
| `Rule\LinkText` | `link-text` | Link texts that say where they lead. |
| `Rule\GenericLinkTexts` | — | `DEFAULTS`, the generic link texts by primary language, and `normalize()`. |
| `Rule\ImageAlt` | `image-alt` | Images with an `alt`, empty for decoration. |
| `Rule\BrokenLinks` | `broken-links` | No dead link, from `LinkStatuses`, `MAX_LINKS` (1,000) asked about at most. |
| `Bridge\StructuredData\StructuredDataRule` | `structured-data` | The JSON-LD of the page, by the `Validator` of phil/structured-data. |
| `Bridge\SocialCards\ShareTagsRule` | `share-tags` | The share tags of the page, by the `TagCheck` of phil/social-cards. |
| `Bridge\LinkChecker\LinkCheckerStatuses` | — | `LinkStatuses` by the `LinkChecker` of phil/link-checker; `PAGE`, the address its links are checked at. |

## The markup

| Type | Kind | Role |
|---|---|---|
| `Markup\MarkupReader` | `final readonly class` | Reads a `RenderedPage` with `Dom\HTMLDocument`: `MAX_BYTES` (5,000,000), `MAX_ELEMENTS` of each kind (5,000), `MAX_TEXT` characters of each text (1,000); the character set of `Content-Type`, else of the page, else UTF-8. |
| `Markup\PageMarkup` | `final readonly class` | What was read: titles, `<meta>` by name, language, first-level headings, canonical and alternate links, links, images, whether the page was cut; `meta(string $name)`. |
| `Markup\Link` | `final readonly class` | An `<a>`: `href` as written or null, text. |
| `Markup\Alternate` | `final readonly class` | A `<link rel="alternate" hreflang>`: `hreflang`, `href`. |
| `Markup\Image` | `final readonly class` | An `<img>`: `src`, `alt`, whether it is hidden from readers. |
| `Markup\RobotsDirectives` | `final readonly class` | Reads the indexing directives of a rendering from `<meta>` and `X-Robots-Tag`: `AGENTS`, `VALUED`, `MAX_DIRECTIVES` (200). |
| `Markup\RobotsDirective` | `final readonly class` | One directive: name, crawler or null, source `meta` or `header`; `forbidsIndexing()`. |
| `Markup\Address` | `final class` | An address read in a page, resolved against the page's own and read by phil/url: `resolve()`, `isAbsolute()`, `trim()`. |

## The report's formats and messages

| Type | Kind | Role |
|---|---|---|
| `Render\TextRenderer` | `final readonly class`, `ReportRenderer` | One line per point, for a console or a log. |
| `Render\JsonRenderer` | `final readonly class`, `ReportRenderer` | `Report::toArray()` as JSON, keys and placeholders included. |
| `Render\HtmlRenderer` | `final readonly class`, `ReportRenderer` | A `<section>` with a list, classes `isError`, `isWarning`, `isInfo`, every text escaped, no style nor script. |
| `Translation\Messages` | `final class` | `DOMAIN`, `resources()`, `translator()`, `translate()`, `locale()`: the messages for symfony/translation, English the fallback. |
| `Translation\English` | `final class` | The English messages, by key. |
| `Translation\French` | `final class` | The French messages, by key. |

## Exceptions

| Type | Kind | Role |
|---|---|---|
| `Exception\SeoCheckException` | interface | What every exception of the package implements. |
| `Exception\InvalidPage` | `final class` | A page without rendering, a status outside 100–599, or a header name that is not a token. |
