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
| `maxHeaderBytes` | 65,536 | 1 KiB to 1 MiB | The headers of an answer, every line counted, status lines included |
| `maxHeaders` | 100 | 1 to 1,000 | The header lines of an answer, status lines left out |

Limits out of their bounds throw `InvalidLimits`. Give the `User-Agent` the name of the site and a way to reach
whoever runs it, as the robots of link previews do: a site that sees an unknown reader blocks it.

## The headers

The headers of an answer are counted as the transport reports them, through its progress, and once more when they are
all in. Past `maxHeaderBytes` or `maxHeaders`, the transfer is aborted and the read throws `FetchFailed`. The body is
bounded in the same spirit: a chunk is cut to the bytes that remain of `maxBytes` before it joins the body, so the body
held never exceeds the limit, whatever the size of the chunk the transport hands over.

What each transport of Symfony HttpClient bounds by itself, before these limits:

| Transport | Bounds of its own | When the limits of the package apply |
|---|---|---|
| curl (`CurlHttpClient`) | libcurl refuses a header line over 100 KiB (`CURL_MAX_HTTP_HEADER`), and, in its recent versions, an answer whose headers pass 300 KiB in all or 5,000 lines | During the reception, at each progress of the transfer |
| native (`NativeHttpClient`) | None on the count or the total: PHP's `http` stream wrapper keeps every line, of any length; only the status line (127 bytes) and `Location` (8,182 bytes) are bounded | Once PHP's wrapper has read every header: the silence and the whole duration still bound how long that takes |
| amphp (`AmpHttpClient`) | amphp/http-client's header size limit, left at its default of 16 KiB; Symfony lifts its body size limit | Once the headers are in |

Symfony HttpClient bounds neither the count nor the bytes of the headers, and keeps every line twice: raw in the
`response_headers` information, and by name.

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
| `send($method, $url, $headers, $body, $rule)` | Any method, with its headers and body: the status, the headers, and the body the rule reads |

`$url` is a `Phil\Url\Url`, or a string the guard reads. A redirect — `301`, `302`, `303`, `307` or `308` with a
`Location` — is followed with the same method; another `3xx`, or one without `Location`, is the answer.

## Sending

`send()` makes a request of any method under the guards of `get()`: the first address and each redirect checked, the
private networks refused, the silence, the whole duration, the redirects and the bytes of the answer bounded by the
limits. It is the call for a token endpoint, a web service of another platform, a webhook to send.

```php
$response = $client->send(
    'POST',
    'https://platform.example/oauth/token',
    ['Authorization' => 'Basic '.base64_encode($id.':'.$secret), 'Content-Type' => 'application/x-www-form-urlencoded'],
    http_build_query(['grant_type' => 'client_credentials', 'scope' => $scope]),
    new ReadRule('application/json', ['application/json']),
);
```

What it checks before any request, with `InvalidRequest`:

| Part | Rule |
|---|---|
| The method | Letters only, written in capitals by the client, 20 at most, as Symfony HttpClient takes them; `CONNECT`, which opens a tunnel, and `TRACE`, which echoes the request and its credentials, are refused |
| A header's name | A token of RFC 9110, not digits only — PHP keeps such a name as an integer key, which Symfony HttpClient reads as a whole header line; never `Accept`, which the rule gives, nor `User-Agent`, which the limits give, nor a header of the connection or of the message's framing — `Host`, `Content-Length`, `Transfer-Encoding`, `Connection`, `Keep-Alive`, `Upgrade`, `TE`, `Trailer`, `Expect` — nor any `Proxy-` header |
| A header's value | One line, not empty, no control character |
| The headers | `maxHeaderBytes` and `maxHeaders` of the limits, each counted as the line `Name: value` |
| The body | `maxBytes` of the limits |

The message of an `InvalidRequest` names a header, never its value.

### Redirects of a request

Only a request of `GET`, `HEAD` or `OPTIONS` without a body follows redirects, each checked as for `get()`. Any other
request — a `POST`, a `PUT`, a `DELETE`, or a `GET` with a body — follows none: a redirect answered to it throws
`RefusedRedirect`, so a body is never sent twice, nor to a host it was not meant for. Another `3xx`, or one without
`Location`, is the answer, as for `get()`.

### Headers and credentials

The headers given to `send()` go to the scheme, the host and the port of the first address only. A redirect to the
same origin carries them; a redirect anywhere else — another host, a subdomain, another port, HTTPS to HTTP — carries
none of them, only the `Accept` of the rule and the `User-Agent` of the limits. An `Authorization`, a `Cookie` or an
API key never reach a host the request did not name.

The client writes no log, and none of its messages names a header's value: the exceptions name the address, the
method and the status. A transport given to the client keeps its own logger, if it has one; Symfony HttpClient's logs
name the method and the address of a request, never its headers.

## The answer

`SafeResponse`:

| Member | Holds |
|---|---|
| `url` | The address that answered, after the redirects |
| `redirects` | The addresses redirected to, in order, the last one being `url` |
| `status`, `isSuccessful()` | The status; whether it is `2xx` |
| `headers`, `header($name)` | The headers by lowercase name; the first value of one |
| `mediaType()`, `charset()` | The media type of `Content-Type` in lowercase, and its character set as written, read by ASCII classes whatever the locale of the process |
| `body` | The body as far as it was read |
| `complete` | Whether the body was read to its end: false when it was cut at `maxBytes` or after the stop mark, or not read |

## The exceptions

All implement `SafeHttpException`.

| Exception | When |
|---|---|
| `RefusedAddress` | The address or a redirect is not an HTTP or HTTPS address of a host name, or its host is or resolves to a private network |
| `TooManyRedirects` | The redirects outnumber `maxRedirects` |
| `FetchFailed` | The host does not answer, stays silent past `timeout`, the read outlasts `maxDuration`, the headers pass `maxHeaderBytes` or `maxHeaders`, or the connection breaks |
| `RefusedRedirect` | A request with a body, or of a method other than `GET`, `HEAD` or `OPTIONS`, was answered by a redirect |
| `InvalidRequest` | A method, a header or a body that `send()` refuses, before any request |
| `InvalidLimits` | Limits, or a header of a rule, out of their bounds |
