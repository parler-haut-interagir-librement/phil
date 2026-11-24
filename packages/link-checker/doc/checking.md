# Checking links

A check takes a content from its HTML to its report in four steps: the links are read, each address gets a key, each
key is checked once, and each link gets the status of its key — and of its anchor.

## The links read

`LinkExtractor::extract($html, $page)` parses the HTML with `Dom\HTMLDocument`, PHP's HTML5 parser, the way a browser
does: a missing end tag, a stray one, an attribute without quotes are read as a browser reads them, and a link left
open around later content is reopened there, as a browser reopens it. No regular expression ever reads the HTML.

| Element | Attributes read |
|---|---|
| `a` | `href` |
| `img` | `src`, then each address of `srcset` |
| `link` | `href`, unless its `rel` holds only `preconnect` or `dns-prefetch`, which name an origin |
| `iframe` | `src` |
| `source` | `src`, then each address of `srcset` |

The links come in document order, each with the element and the attribute that carry it and the words a reader sees:
the text of a link — or, without text, the alternative text of its image, then its title —, the alternative text or
the title of an image or a frame, the `rel` of a `link`. Those words and the address as written are kept on one line,
whitespace and control characters collapsed, and cut to 200 characters.

Each address is resolved against the page's address, or against the first `<base href>` of the document when that
resolves to a web address, by the WHATWG URL Standard (`Uri\WhatWg\Url`), then read by phil/url: the scheme and the
host in lowercase, an international host in ASCII (`bücher.example` becomes `xn--bcher-kva.example`), the default port
dropped.

- An address of another scheme — `mailto:`, `tel:`, `javascript:`, `data:`, `ftp:` — is left out, and so are an empty
  `href` and a lone `#`, which point at the page itself.
- An address that cannot be parsed is kept as **invalid**.
- An address phil/url refuses — an IP address, credentials, `localhost` — is kept as **refused**, and never requested.

A content is read up to `maxBytes` (4 MiB by default, 64 MiB at most) and at most `maxLinks` links are kept (2,000 by
default, 100,000 at most). Past either, the extraction stops and the report says it was truncated.

## The key of an address

`UrlNormalizer::key()` gives the key under which links are checked once: the address read by phil/url, without its
fragment. `?b=2&a=1` and `?a=1&b=2` stay two keys unless the normaliser is built with `sortQuery: true`: most sites
read the parameters in any order, some do not, and only the caller knows its links.

```php
new LinkChecker($probe, normalizer: new UrlNormalizer(sortQuery: true));
```

Sorted, the parameters are ordered by name; those of the same name keep their order, which some sites read.

## The requests

`HttpLinkProbe` asks each key through phil/safe-http:

1. The throttle waits until a request to the host may leave.
2. A `HEAD` request: the redirects are followed, each checked, within the limits of phil/safe-http (3 by default);
   a host that is or resolves to a private, loopback, link-local or reserved network is refused, at the first address
   and at each redirect.
3. When the server answers `HEAD` with 405 or 501, the throttle waits again, and a `GET` follows, whose body is
   never read: the transfer stops once the status and the headers are in.

The `User-Agent` is `PhilLinkChecker/0.1`, unless the `Limits` given to the probe set another one; a site that checks
its links names itself and a way to reach it there.

Only the address a link names is requested. The probe never reads another site's page to find more links, and reads a
body only for a page of the site itself, to look for an anchor.

## The statuses

| Status | When | Broken |
|---|---|---|
| `Alive` | 2xx, without a redirect | no |
| `PermanentRedirect` | 2xx after redirects that are all 301 or 308; `target` holds the final address | no |
| `TemporaryRedirect` | 2xx after redirects of which one at least is 302, 303 or 307; `target` holds the final address | no |
| `Dead` | 404 or 410, after redirects or not; or a host name that does not resolve | yes |
| `MissingAnchor` | the page of an internal link was found, not the element of its fragment | yes |
| `Invalid` | the address as written cannot be parsed | yes |
| `Review` | any other code — 429, 403, 401, 400, 5xx, 3xx without a location —, a timeout, a TLS error, a broken connection, too many redirects | no |
| `Refused` | the address, or a redirect, is not public | no |

A result to review is never counted as dead: a server that limits its rate, refuses robots or is down for a while
says nothing of the link. `LinkResult::$reason` names the failure in a few words of English — `timeout`, `TLS error`,
`host not found`, `too many redirects`, `not a public address` — and `$code` keeps the HTTP code of the last answer.

## The anchors

A link to the content's own page — `#intro`, or its full address with a fragment — is looked for in the content
itself. A link to one of the `internalHosts` given to the checker — the hosts of the site — is checked so:

1. `InternalPages::html()` is asked for the page, without its fragment. When it gives the HTML, the page counts as
   alive, without any request.
2. Otherwise, a link without fragment is probed as any other; a link with a fragment is probed with a `GET` that reads
   the HTML of the page, up to the bytes of the limits.
3. In the page found — alive or redirected —, the fragment must name the `id` of an element or the `name` of an `a`
   element, as written or percent-decoded. An empty fragment and `top` always point at the top of the page.

A page read only in part — past the bytes of the limits or of the extractor — may hold the element further down: its
anchors are then not judged, and the link keeps the status of its page. The anchors of another site are never checked.

## The report

A `LinkReport` lists the links of one content in document order: a link written twice is listed twice, each with its
place, and checked once.

```php
$report->broken();      // Dead, MissingAnchor, Invalid
$report->toReview();    // Review
$report->redirected();  // PermanentRedirect, TemporaryRedirect
$report->refused();     // Refused
$report->isClean();     // no broken link
$report->summary();     // ['checked' => 12, 'broken' => 1, 'review' => 1, 'redirected' => 2, 'refused' => 0]
```

As text, a summary line, then one line for each link that is not alive:

```text
Links of article-42 (https://www.example.com/news/42): 12 checked, 1 broken, 1 to review, 2 redirected, 0 refused.
- dead 404: https://example.org/gone (a href "Old study")
- permanent-redirect 200: https://example.org/old (a href "Report") -> https://example.org/reports/2026
- temporary-redirect 200: https://example.net/promo (img src "Banner") -> https://example.net/promo/fall
- review 429: https://example.com/busy (a href "Shop")
```

As JSON, every link, alive ones included:

```json
{"content":"article-42","page":"https://www.example.com/news/42","truncated":false,
 "summary":{"checked":12,"broken":1,"review":1,"redirected":2,"refused":0},
 "links":[{"url":"https://example.org/gone","href":"https://example.org/gone","element":"a","attribute":"href",
           "text":"Old study","status":"dead","code":404,"target":null,"reason":null}]}
```
