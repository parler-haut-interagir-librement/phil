# Reference

Every public type of the package, with its role. The types marked internal are not part of the public API.

## Checking

| Type | Role |
|---|---|
| `LinkChecker` | Checks the links of contents and yields one `LinkReport` per content: `checkContent(ContentLinks)`, `check(iterable<ContentLinks>)`. Takes a probe, an extractor, a normaliser, a cache, the internal pages, the internal hosts and the two lifetimes of a result; `MAX_TTL` is 30 days. |
| `ContentLinks` | One content to check: `id`, `page` (a phil/url `Url`, against which relative links resolve), `html`. |
| `UrlNormalizer` | The key under which two links are checked once: `key(Url)`, without the fragment, the query sorted only with `sortQuery`; `withoutFragment(Url)`, the address requested. |
| `LinkStatus` | `Alive`, `PermanentRedirect`, `TemporaryRedirect`, `Dead`, `MissingAnchor`, `Invalid`, `Review`, `Refused`; `isBroken()` for the three first broken ones, `isRedirect()`. |
| `LinkResult` | What the check of an address found: `status`, `code`, `target`, `reason`; `toArray()` and `fromArray()`, as a cache keeps it. |

## Extraction

| Type | Role |
|---|---|
| `Extraction\LinkExtractor` | Reads the links of HTML with `Dom\HTMLDocument` and resolves them: `extract(string $html, Url $page)`. `maxBytes` (4 MiB, `MAX_BYTES` 64 MiB) and `maxLinks` (2,000, `MAX_LINKS` 100,000). |
| `Extraction\ExtractedLinks` | The links in document order, and `truncated` when a limit was reached. |
| `Extraction\Link` | One link: `href` as written, `url` resolved or null, `element`, `attribute`, `text`, and `status` when judged without a request (`Invalid`, `Refused`). `MAX_TEXT` is 200 characters. |
| `Extraction\AnchorIndex` | Internal. The `id`s and `a[name]`s of a page, for its fragments. |
| `Extraction\SrcsetParser` | Internal. The addresses of a `srcset`, by the HTML Standard's rules. |

## Requests

| Type | Role |
|---|---|
| `Probe\HttpLinkProbe` | The `LinkProbe` over phil/safe-http: `HEAD`, then a `GET` without body after 405 or 501, or a `GET` that reads the HTML when asked; the redirects and their permanence; `USER_AGENT` is `PhilLinkChecker/0.1`. Resettable. |
| `Probe\ProbeResult` | What a probe found: `result`, the `html` of the page when asked and HTML, and `complete` when that HTML was read to its end. |
| `Probe\HopRecorder` | Internal. The transport under phil/safe-http that keeps the codes of the redirects of one check. |
| `Throttle\IntervalThrottle` | A pace per host in memory, on a Symfony clock: `interval` (1 s, `MAX_INTERVAL` 60 s), `maxHosts` (1,000, `MAX_HOSTS` 100,000). Resettable. |

## Ports

| Type | Role |
|---|---|
| `Contract\LinkProbe` | `probe(Url $url, bool $withBody = false): ProbeResult`. |
| `Contract\HostThrottle` | `wait(string $host): void`. |
| `Contract\InternalPages` | `html(Url $url): ?string`, the HTML of a page of the site without a request, or null. |
| `Contract\DeadLinkFixer` | `supports(LinkReport, CheckedLink): bool` and `fix(LinkReport, CheckedLink): void`. |

## Report

| Type | Role |
|---|---|
| `Report\LinkReport` | The links of one content and their results: `broken()`, `toReview()`, `redirected()`, `refused()`, `isClean()`, `summary()`, `toText()`, `toJson()`; `truncated`. |
| `Report\CheckedLink` | One link and its result; `toText()`, one line of a report, and its JSON form. |

## Internal

| Type | Role |
|---|---|
| `Memo` | Internal. What one batch has found, by key, bounded. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\LinkCheckerException` | Every exception of the library. |
| `Exception\InvalidOption` | An option out of its bounds, or an internal host that is not lowercase ASCII. |

A check itself throws nothing for a link: a failure of the network is a status.
