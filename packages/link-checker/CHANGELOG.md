# Changelog

All notable changes to `phil/link-checker` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `LinkExtractor`: the links of an HTML content — `a[href]`, `img[src]` and `srcset`, `link[href]`, `iframe[src]`,
  `source` — read by `Dom\HTMLDocument` in document order, resolved against the page or its first `<base href>` by the
  WHATWG URL Standard and read by phil/url; other schemes, empty links and `preconnect` left out; addresses that cannot
  be parsed kept as invalid, those that are not public as refused; the words of each link on one line; bounded by
  bytes and by links, with `ExtractedLinks::$truncated`.
- `UrlNormalizer`: the deduplication key without the fragment, the query sorted by name only with `sortQuery`.
- `HttpLinkProbe` over phil/safe-http: `HEAD`, then a `GET` that reads no body after 405 or 501, or a `GET` that
  reads the HTML of an internal page; the private networks refused; redirects followed within the limits, permanent
  only when every one is 301 or 308; 404 and 410 and unknown hosts dead; anything else to review, with a short reason;
  the `User-Agent` `PhilLinkChecker/0.1`, set by the limits.
- `HostThrottle` and `IntervalThrottle`: a pace per host on a Symfony clock, bounded in hosts, resettable.
- `LinkChecker`: every address checked once per batch, results kept in a Symfony cache by an xxh128 key, a result to
  review for a shorter lifetime; the anchors of the content itself and of the site's pages looked for by `id` and
  `a[name]`, through `InternalPages` without a request or over HTTP, never judged on a page read in part; batches by
  `check(iterable)`.
- `LinkReport` and `CheckedLink`: the links of a content with their place, status, code, target and reason, sorted into
  broken, to review, redirected and refused, rendered as text and as JSON.
- The `LinkProbe`, `InternalPages` and `DeadLinkFixer` ports, and `InvalidOption` under `LinkCheckerException`.
- The fuzz target `extract`, the benchmark `ExtractBench`, and the documentation in `doc/`: installation, checking,
  extension points, continuous integration with lychee, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
