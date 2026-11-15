# phil/safe-http

**Reading other sites safely: an HTTP client on Symfony HttpClient that refuses private networks, follows redirects it
checks itself, and bounds the time, the size and the redirects of what it reads.**

A site that reads addresses given by its members or by other sites — a link preview, a link check, an oEmbed
endpoint, a feed, a mention sent by another site — must not let them reach its own network: a cloud metadata endpoint,
a database, an administration page on the loopback. phil/safe-http reads such addresses:

- every address, the first and each redirect, is an absolute HTTP or HTTPS address of a host name, read by phil/url:
  no IP address in any of its spellings, no user name nor password;
- Symfony's `NoPrivateNetworkHttpClient` refuses a host that is or resolves to a loopback, private, link-local or
  reserved address, and connects to the address it checked;
- the redirects are followed one by one, each checked, up to a count;
- the silence of the host and the whole duration of the read, redirects included, are bounded;
- the headers of an answer are bounded in bytes and in count, counted as they arrive;
- the body is read in chunks, only for the media types asked for, up to a number of bytes or a stop mark, and the
  transfer is then cancelled; a chunk is cut to the bytes that remain before it joins the body;
- `send()` makes a request of any method, with headers and a body, under the same guards: the headers it is given
  never leave the scheme, host and port of the first address, and a request with a body, or of a method other than
  `GET`, `HEAD` or `OPTIONS`, follows no redirect.

A read gives a `SafeResponse` — the address that answered, the redirects, the status, the headers and the body as far
as it was read — or an exception: the address or a redirect is refused, the redirects are too many, the request is
refused before it is sent, or the read failed.
An error status is an answer, so that a link check can tell a `404` from a timeout.

## Status

| Part | State |
|---|---|
| `SafeHttpClient`: `get()`, `head()`, `send()`; `Limits`, `ReadRule`, `SafeResponse` | Implemented and tested |
| `AddressGuard` | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reading](doc/reading.md) | The client, its limits, the rules of a read, sending a request with headers and a body, the answer and the exceptions. |
| [The guard](doc/guard.md) | What an address must be, redirects, private networks, and what the guard does not cover. |
| [Extension points](doc/extension-points.md) | The transport, fixed addresses, and what stays closed. |
| [Testing](doc/testing.md) | The package's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SafeHttp\Exception\SafeHttpException;

$client = new SafeHttpClient(limits: new Limits(userAgent: 'ExampleBot/1.0 (+https://example.com/bot)'));

try {
    $page = $client->get($address, ReadRule::htmlHead());
} catch (SafeHttpException) {
    $page = null; // refused, too many redirects, or no answer in time
}

if (null !== $page && 200 === $page->status) {
    // $page->body: the page up to the end of its head, 1 MiB at most
}

// A request of another platform's web service, its credentials kept on that host.
$token = $client->send('POST', 'https://platform.example/oauth/token', ['Authorization' => 'Basic '.$credentials, 'Content-Type' => 'application/x-www-form-urlencoded'], 'grant_type=client_credentials', new ReadRule('application/json'));
```

## Requirements

PHP 8.5 or later with the mbstring and URI extensions, `phil/url`, and Symfony's HttpClient, its contracts and
HttpFoundation, whose `IpUtils` the private network check uses.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
