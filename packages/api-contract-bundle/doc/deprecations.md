# Deprecations

## As a server

A controller marked `#[DeprecatedEndpoint]` announces its deprecation on every response, as RFC 9745 and RFC 8594
say:

```php
use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;

#[DeprecatedEndpoint(deprecatedAt: '2026-11-01', sunsetAt: '2027-05-01', link: 'https://example.com/changes')]
```

```http
Deprecation: @1793491200
Sunset: Sat, 01 May 2027 00:00:00 GMT
Link: <https://example.com/changes>; rel="deprecation"
```

Dates are ISO 8601, in UTC unless they say; either may be left out, not both. The attribute builds its signal when
it is read, and the bundle reads every one of the controller services (tagged `controller.service_arguments`, as
autoconfiguration does) when the container is compiled: a date that cannot be read, a sunset before the deprecation
or a link with a space fails the build with a `LogicException` naming the controller, not each request to the
endpoint. A controller that is not such a service is checked at its first request. `Deprecation` and `Sunset` replace
any the controller set; the `Link` is added beside the response's own `Link` headers, such as pagination or
preload, never in their place.

## As a client

`DeprecationObservingHttpClient` decorates the `http_client` service. When a response carries `Deprecation` or
`Sunset`, the signal is recorded for its endpoint in `ObservedDeprecations`, and a warning is logged once a day per
endpoint. The endpoint is the request's `extra.endpoint` option, or else its method and path, every segment that
does not read as a name replaced by `{id}`. A name is a letter, then letters, digits, `-` and `_`, with at most two
digits and 32 characters: numbers, UUIDs, hashes, e-mail addresses, encoded values and file names are replaced.

The path is the one a browser reads, by PHP's WHATWG URL parser: a backslash is a slash, `.` and `..` segments are
resolved, written plainly or escaped, a letter outside ASCII is percent-encoded, and the user name and password of a
URL never reach it. Any host is read, an IP address or `localhost` included; a relative URL, which the client resolves
against its `base_uri`, is read from the root.

```php
$client->request('GET', 'https://partner.example/orders/42', ['extra' => ['endpoint' => 'partner.order']]);
// without the option: "GET /orders/{id}"
```

A value spelt like a word — a username, a slug — cannot be told from a name: `GET /users/jdoe` is recorded and
logged as it is. Give such calls an `extra.endpoint`, so that no identifier reaches the record or the log.

`CacheObservedDeprecations` keeps the record in `cache.app`, one item per endpoint and an index of their names. An
endpoint's item is written when its daily warning is due or its signal changed, not on every call, and kept eight
days from that write: an endpoint the application no longer calls, or whose API withdrew its signal, leaves the
record a week after it was last seen. The command lists it:

```bash
bin/console phil:api-contract:deprecations
# GET /orders/{id}: deprecated since 2026-11-01, goes away on 2027-05-01 — https://example.com/changes
```

It fails while an endpoint has a sunset: run in CI, it tells the team before the endpoint goes away.
