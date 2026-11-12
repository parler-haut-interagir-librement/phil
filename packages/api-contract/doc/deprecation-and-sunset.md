# Deprecation and Sunset

**State:** `DeprecationSignal`, `HttpDate` and `LinkHeader` are implemented and tested;
`ObservedDeprecations` is a port only.

## The rule

An unversioned contract never publishes a `/v2`. A change that cannot be additive is a **new resource next to
the old one**, and the old one **announces its own retirement in every response** until it is removed:

| Header | Specification | Value |
|---|---|---|
| `Deprecation` | RFC 9745 | The date the resource was, or will be, deprecated, as a structured-field date: `@` followed by a Unix timestamp, for example `@1688169599`. |
| `Sunset` | RFC 8594 | The date the resource stops being served, as an HTTP-date (RFC 9110 section 5.6.7), for example `Sun, 31 Dec 2023 23:59:59 GMT`. |
| `Link` | RFC 8288 | `<uri>; rel="deprecation"`, the document that says what to do instead. |

The minimum notice between the two dates, and who decides them, belong to the application:
`noticeInDays()` gives the figure, the library does not judge it.

## `DeprecationSignal`

`Phil\ApiContract\Deprecation\DeprecationSignal`, a readonly value object with three public
properties: `?DateTimeImmutable $deprecatedAt`, `?DateTimeImmutable $sunsetAt`, `?string $link`.

### Server side: `of()` and `toHeaders()`

```php
public static function of(?DateTimeImmutable $deprecatedAt, ?DateTimeImmutable $sunsetAt = null, ?string $link = null): self
```

`of()` throws `InvalidContractValue` when:

- both dates are `null` (a signal carries a deprecation date, a sunset date, or both);
- the sunset precedes the deprecation (equal dates are allowed);
- the link is empty, or contains `>`, whitespace or a control character: it would break the `Link` header or
  inject another header.

Both dates are truncated to whole seconds, since both headers carry whole seconds; a signal is therefore equal to
its own round trip.

```php
use Phil\ApiContract\Deprecation\DeprecationSignal;

$signal = DeprecationSignal::of(
    new DateTimeImmutable('@1688169599'),
    new DateTimeImmutable('2023-12-31 23:59:59 UTC'),
    'https://example.com/guide/deprecations#tenants',
);

foreach ($signal->toHeaders() as $name => $value) {
    $response->headers->set($name, $value);
}
// Deprecation: @1688169599
// Sunset: Sun, 31 Dec 2023 23:59:59 GMT
// Link: <https://example.com/guide/deprecations#tenants>; rel="deprecation"
```

`toHeaders()` returns `array<string, string>` keyed by header name and **writes no header for an absent part**.
If the response already carries other `Link` values, add this one rather than replacing them.

### Client side: `fromHeaders()`

```php
/** @param list<string> $linkHeaders every Link header line of the response */
public static function fromHeaders(?string $deprecation, ?string $sunset, array $linkHeaders = []): ?self
```

The reader is tolerant: **a malformed value is ignored, as though absent.**

- `Deprecation` must be an sf-date: `@` and an optionally signed integer of at most fifteen digits. Surrounding
  whitespace and structured-field parameters (`@1688169599;foo=bar`) are dropped. `true` (the form of the drafts
  before RFC 9745), an HTTP-date, a bare number, a decimal or sixteen digits are ignored.
- `Sunset` is read by `HttpDate::parse()`; anything else is ignored.
- If neither date is usable, `fromHeaders()` returns `null`, even when a deprecation link is present.
- **Contradictory dates keep the sunset.** A sender that wrote its sunset before its deprecation still
  announced a retirement; the sunset is the date that breaks the caller, so the deprecation date is dropped.
  Equal dates are not contradictory.
- The link is the first target with relation `deprecation` across every `Link` line, found by
  `LinkHeader::firstWithRelation()`.

### Comparing with now

| Method | Returns |
|---|---|
| `isDeprecatedAt(DateTimeInterface $now): bool` | `true` when a deprecation date exists and is at or before `$now`. |
| `isSunsetAt(DateTimeInterface $now): bool` | `true` when a sunset date exists and is at or before `$now`. |
| `noticeInDays(): ?int` | Whole days between the two dates, rounded down (`+90 days -1 second` is `89`), or `null` if either date is missing. |

An absent date is never reached.

Constants: `DEPRECATION_HEADER = 'Deprecation'`, `SUNSET_HEADER = 'Sunset'`, `LINK_HEADER = 'Link'`,
`LINK_RELATION = 'deprecation'`.

## `HttpDate`

`Phil\ApiContract\Http\HttpDate`, static helpers for the HTTP-date of RFC 9110 section 5.6.7.

- `format(DateTimeInterface $moment): string` writes the preferred IMF-fixdate, in UTC, whatever the time zone of
  the input: `Sun, 06 Nov 1994 08:49:37 GMT`.
- `parse(string $value): ?DateTimeImmutable` accepts the three forms a recipient must accept: IMF-fixdate,
  RFC 850 (`Sunday, 06-Nov-94 08:49:37 GMT`) and asctime (`Sun Nov  6 08:49:37 1994`). Surrounding whitespace is
  trimmed and runs of spaces are collapsed. It returns `null` rather than throwing for anything else: an
  ISO 8601 date, a zone other than `GMT`, an impossible day (`31 Feb`), a weekday that does not match the date,
  trailing text. The parsed instant has no fraction of a second.

RFC 850 dates carry a two-digit year; PHP reads it in the 1970 to 2069 window, which gives the same answer as
RFC 9110's fifty-year rule for any date a deprecation can carry.

## `LinkHeader`

`Phil\ApiContract\Deprecation\LinkHeader::firstWithRelation(array $headers, string $relation): ?string`
implements just enough of RFC 8288 to find the target of a relation:

- each element of `$headers` is one `Link` line; a line holds comma-separated `<uri>; param=value` entries;
- `rel` holds one or more space-separated relation types, compared case-insensitively, quoted or bare
  (`rel="help deprecation"`, `rel=deprecation`, `REL="Deprecation"` all match `deprecation`);
- only the first `rel` parameter of an entry counts;
- a `; rel=` inside another parameter's quoted value is not a parameter;
- a relation that merely contains the word (`deprecation-notes`) does not match;
- an empty target (`<>`) is skipped;
- the first match, across lines in order, wins; no match returns `null`.

## `ObservedDeprecations` (port)

Where a client keeps the deprecation signals it has received, endpoint by endpoint. It feeds three readers the
application writes: a log that warns **once per endpoint and per day** rather than on every call, a profiler
panel, and a verification command that a CI runs and that fails while any endpoint it calls carries a sunset.
The contract is in [extension-points.md](extension-points.md#observeddeprecations).

A client-side flow, using only the library's types:

```php
$signal = DeprecationSignal::fromHeaders(
    $response->getHeaders(false)['deprecation'][0] ?? null,
    $response->getHeaders(false)['sunset'][0] ?? null,
    $response->getHeaders(false)['link'] ?? [],
);

if (null !== $signal && $observed->record('GET /api/tenants', $signal, $clock->now())) {
    $logger->warning('The API endpoint {endpoint} is deprecated.', [
        'endpoint' => 'GET /api/tenants',
        'sunset' => $signal->sunsetAt?->format(DATE_ATOM),
        'link' => $signal->link,
    ]);
}
```

An "endpoint" is a route name or a method and path template, **never a URL with identifiers in it**.

## Not in the library

The HTTP client decorator that calls `fromHeaders()`, the log, the verification command and the server-side
listener that adds the headers to a deprecated route. `phil/api-contract-bundle` brings all four to Symfony
applications.
