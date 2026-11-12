# Reading

## The client

```php
$client = new SafeHttpClient(
    client: $httpClient,         // the transport; HttpClient::create() by default
    limits: new Limits(),        // what one read may cost
    resolve: [],                 // fixed addresses for host names, as curl's --resolve
);
```

The client wraps the transport in Symfony's `NoPrivateNetworkHttpClient`; whatever transport it is given, the guard
stays. It keeps nothing of one read for the next but the addresses the guard resolved, which `reset()` forgets: a
worker calls it between messages, as `kernel.reset` does in Symfony.

## The limits

| Limit | Default | Bounds | Bounds what |
|---|---|---|---|
| `timeout` | 5.0 | 0.1 to 60 seconds | The silence of the host, never longer than what remains of the whole duration |
| `maxDuration` | 10.0 | 0.1 to 120 seconds | The whole read, every redirect included |
| `maxBytes` | 1,048,576 | 1 KiB to 64 MiB | The body kept, after decompression |
| `maxRedirects` | 3 | 0 to 10 | The redirects followed |
| `userAgent` | `PhilSafeHttp/0.1` | One line, no control character | The `User-Agent` of every request |

Limits out of their bounds throw `InvalidLimits`. Give the `User-Agent` the name of the site and a way to reach
whoever runs it, as the robots of link previews do: a site that sees an unknown reader blocks it.

## The rule of a read

`ReadRule` says what of the body is read:

| Field | Default | Role |
|---|---|---|
| `accept` | `*/*` | The `Accept` header, one line |
| `types` | none | The media types whose body is read, in lowercase; any when empty, the others not read |
| `stopAfter` | null | A mark after which the transfer stops, found without regard to case, even split between two chunks |

`ReadRule::htmlHead()` reads HTML and XHTML up to `</head`, for the tags of a page.

## Reading

| Call | Reads |
|---|---|
| `get($url, $rule)` | The status, the headers, and the body the rule reads |
| `head($url)` | The status and the headers, no body |

`$url` is a `Phil\Url\Url`, or a string the guard reads. A redirect — `301`, `302`, `303`, `307` or `308` with a
`Location` — is followed with the same method; another `3xx`, or one without `Location`, is the answer.

## The answer

`SafeResponse`:

| Member | Holds |
|---|---|
| `url` | The address that answered, after the redirects |
| `redirects` | The addresses redirected to, in order, the last one being `url` |
| `status`, `isSuccessful()` | The status; whether it is `2xx` |
| `headers`, `header($name)` | The headers by lowercase name; the first value of one |
| `mediaType()`, `charset()` | The media type of `Content-Type` in lowercase, and its character set as written |
| `body` | The body as far as it was read |
| `complete` | Whether the body was read to its end: false when it was cut at `maxBytes` or after the stop mark, or not read |

## The exceptions

All implement `SafeHttpException`.

| Exception | When |
|---|---|
| `RefusedAddress` | The address or a redirect is not an HTTP or HTTPS address of a host name, or its host is or resolves to a private network |
| `TooManyRedirects` | The redirects outnumber `maxRedirects` |
| `FetchFailed` | The host does not answer, stays silent past `timeout`, the read outlasts `maxDuration`, or the connection breaks |
| `InvalidLimits` | Limits, or a header of a rule, out of their bounds |
