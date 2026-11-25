# Browser errors

## The endpoint

With `browser.enabled` and `config/routes_browser.php` imported, `POST /_errors/browser` receives the reports of the
Stimulus controller. It is stateless and answers with an empty body whose status tells the receipt:

| Status | When |
|---|---|
| `204` | the report was read and handed to the tracker, which may still sample it out or throttle it |
| `415` | the media type is neither `application/json` nor `text/plain` |
| `413` | the body is larger than `browser.max_bytes`; no more than one byte past the bound is ever read |
| `403` | the `Origin` header is missing or not allowed, or the token is wrong |
| `429` | the client posted more than `browser.rate_limit` allows |
| `400` | the body is not a report |

The checks run in that order, the cheapest first.

## The origin and the token

The `Origin` header is the defence against a page of another site posting in a visitor's name: it must be one of
`browser.origins`, or the endpoint's own origin when none is configured. Each configured origin is read by `phil/url`
and kept as a browser sends it, `https://example.com:8443`; one with a path, a query, a fragment or a space fails the
build. A token, when `browser.token` is set, is
compared in constant time with the `X-Error-Tracking-Token` header. The token is written in the page, so it is not a
secret from the visitor: it keeps out the scripts that do not load the page.

## The report

```json
{"type": "TypeError", "message": "x is undefined", "url": "https://example.com/cart", "stack": "TypeError: …\n    at …"}
```

- `url`, required: an `http` or `https` address that `phil/url` reads, of 2,048 bytes at most, without space or
  control character; `localhost` is accepted, an IP address, credentials or port 0 are refused. The page is kept
  normalised — host in lower case and in its ASCII form, default port dropped, `.` and `..` resolved — and without its
  query nor its fragment;
- `message`, required: cut to 2,048 characters, its control characters turned to spaces;
- `type`: a JavaScript name, `Error` when it is anything else;
- `stack`: read up to 16 KiB, line by line, in the formats of Chromium (`at fn (url:line:column)`) and of Firefox and
  Safari (`fn@url:line:column`), up to `browser.max_frames` frames; other lines are ignored. A frame's file is its
  script address without query nor fragment, and it is the application's when `phil/url` reads it and it comes from
  the page's origin, compared on the normalised scheme, host and port.

The event is of the `browser` source, with the page as the request's address, the `User-Agent` as its only header, and
the client's address truncated. The tracker scrubs it like any other. The
throttle counts a keyed digest of the client's address, never the address, under a key derived from the secret by
HKDF-SHA256 under `phil_error_tracking.browser_throttle`. When phil/rate-limit-bundle is registered, it
goes through a limiter of that bundle named `phil_error_tracking.browser`, over the same window, which decorating the
service `phil_error_tracking.browser_throttle.limit` replaces; without that bundle it counts on `symfony/rate-limiter`
directly. See [the configuration](configuration.md#what-fails-the-build) for what a rate limit needs.

## The Stimulus controller

```twig
<body data-controller="phil--error-tracking-bundle--capture"
      data-phil--error-tracking-bundle--capture-endpoint-value="{{ path('phil_error_tracking_browser') }}"
      data-phil--error-tracking-bundle--capture-token-value="{{ error_tracking_token }}">
```

The token value is `browser.token`, which the application passes to its template; leave the attribute out without a
token. While it is connected, the controller listens to the window's `error` and `unhandledrejection` events and posts a
report for each: at most `limit` per page (10 by default, `data-…-limit-value`), and the same message once. It sends the
page's origin and path, never its query nor its fragment, by a `fetch` kept alive and without credentials. It sets no
cookie and stores nothing. An error of a script of another origin, which the browser reduces to `Script error.`, is
not sent.

With AssetMapper, the bundle maps `assets/src` under `@phil/error-tracking-bundle`, the name Symfony UX gives the
controllers of a package, so that StimulusBundle finds the controller its `controllers.json` declares. The controller is
fetched eagerly: its code loads with the page, so that an error thrown early is caught as well.

The parser of the endpoint is a fuzz target: see [Testing](testing.md).
