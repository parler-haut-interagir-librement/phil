# phil/link-checker

**Find the dead links in a site's content before readers do: each address checked once, paced per host, anchors
verified, private networks refused.**

phil/link-checker is a plain PHP library. It checks the links of the contents a site already knows — its articles,
pages and descriptions — rather than crawling the site: an application hands it the HTML of a content and the address
of the page that shows it, and gets back a **`LinkReport`**: every link, where it stands, and what its check found.

**`LinkExtractor`** reads the links of the HTML with PHP's HTML5 parser, as a browser reads them, malformed or not —
`a[href]`, `img[src]` and `srcset`, `link[href]`, `iframe[src]`, `source` — and resolves each against the page by the
WHATWG URL Standard. **`UrlNormalizer`** gives the key under which two links to the same address are checked once; the
order of the query only counts when the caller asks for it. **`HttpLinkProbe`** asks each address through
phil/safe-http, which refuses the private networks and bounds the time, the bytes and the redirects: `HEAD` first,
then a `GET` without body when the server refuses `HEAD`, each paced per host by a **`HostThrottle`**, with a neutral
`User-Agent` the application can change.

**`LinkChecker`** classifies each link: alive; redirected, permanently or temporarily, with its target; dead (404, 410,
a host name that does not exist); to review (429, 403, a timeout, a TLS error), never counted as dead; refused, for an
address that is not public. A link to the site itself must find its `#fragment` in the target page, read without a
request when the application gives it through **`InternalPages`**. Results are kept in a Symfony cache between
batches, and every address is asked once per batch. Only the address a link names is ever requested: the checker never
follows a link further into another site.

## Status

| Part | State |
|---|---|
| `LinkExtractor`, `ExtractedLinks`, `Link`: links of HTML read by the HTML5 parser, resolved, bounded | Implemented and tested |
| `UrlNormalizer`: deduplication key, query sorted only on request | Implemented and tested |
| `HttpLinkProbe`: `HEAD` then bounded `GET` over phil/safe-http, redirects and their permanence | Implemented and tested |
| `IntervalThrottle` and the `HostThrottle` port: a pace per host, on a clock | Implemented and tested |
| `LinkChecker`: classification, anchors of internal pages, `InternalPages`, cache, batches | Implemented and tested |
| `LinkReport`, `CheckedLink`: the report as text and JSON | Implemented and tested |
| The `DeadLinkFixer` port | Declared; implemented by a bundle or the application |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and a first check from start to end. |
| [Checking links](doc/checking.md) | The links read, the key, the requests, each status, the anchors, the report. |
| [Extension points](doc/extension-points.md) | The ports, batches and queues, the cache, the throttle, the internal pages. |
| [Continuous integration](doc/ci.md) | Checking the links of a repository's files with lychee, beside this library. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/link-checker
```

```php
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\Url\Url;

$checker = new LinkChecker(new HttpLinkProbe(), cache: $cache, internalHosts: ['www.example.com']);
$report = $checker->checkContent(new ContentLinks('article-42', Url::fromString('https://www.example.com/news/42'), $html));

echo $report->toText();      // a summary, then one line per link that is not alive
$json = $report->toJson();   // every link, for a screen or an API
```

## Requirements

PHP 8.5 or later, with `ext-dom`, `ext-mbstring` and `ext-uri`; phil/safe-http and phil/url; Symfony's HttpClient,
Clock and the cache and service contracts.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
