# Service addresses

An application often talks to services it runs beside it: a speech-to-text server, a PDF renderer, a search engine.
In a container network, their addresses name a host without a domain or an IP address — `http://whisper:8080`,
`http://gotenberg:3000`, `http://10.0.0.5:9200`. `Url::fromString()` refuses those hosts, on purpose: an address a
member, an editor or another site gives must never name a machine of the application's own network.

`ServiceUrl` reads them, for the hosts the application declares and no other.

## Declaring the hosts

The hosts come from the application's configuration, never from a form or a request:

```php
use Phil\Url\ServiceHosts;
use Phil\Url\ServiceUrl;

$hosts = new ServiceHosts('whisper', 'gotenberg', '10.0.0.5', '::1');

$whisper = ServiceUrl::fromString('http://whisper:8080', $hosts);
$whisper->toString();  // "http://whisper:8080/"
$whisper->origin();    // "http://whisper:8080"

ServiceUrl::fromString('http://redis:6379/', $hosts);     // InvalidUrl: "redis" is not a declared service host.
ServiceUrl::fromString('https://example.com/', $hosts);   // InvalidUrl: a public host is refused too
Url::fromString('http://whisper:8080');                   // InvalidUrl: "whisper" is not a host name.
```

| `ServiceHosts` accepts | It refuses |
|---|---|
| A name without a domain, `whisper` | A port, `whisper:8080` — the port belongs to the address |
| A name of a private zone, `pdf.internal` | A path, a query, a fragment, a user name, an escape, a space or a control |
| An IPv4 address, `10.0.0.5`, written short or in full | An IPv6 zone, `fe80::1%eth0` |
| An IPv6 address, with or without brackets, `::1` or `[::1]` | More than 256 hosts |

Each host is kept as an address names it: in lowercase, in its ASCII form, one trailing dot dropped, an IPv4 address
in full (`127.1` is `127.0.0.1`), an IPv6 address in brackets and compressed (`[2001:DB8::0:1]` is `[2001:db8::1]`).
`all()` lists them; `declares($host)` compares a host given in that form.

## What a service address keeps of `Url`

`http` or `https` only, no user name nor password, no port 0, 2048 characters at most, and the path, the query and
the fragment normalised by the WHATWG URL Standard as [`Url` normalises them](addresses.md#normalisation). A
`ServiceUrl` has the parts `scheme`, `host`, `port`, `path`, `query` and `fragment`, and `toString()`, `origin()` and
`equals()`. It has no `display()`, `isOn()` or kind: it is never shown to people nor detected among kinds.

A `ServiceUrl` is not a `Url`, and neither converts to the other: a client of public sites, `phil/safe-http`'s among
them, never receives a service address by mistake, and a service client never reads an address a visitor wrote.

## Resolving an endpoint

`resolve()` resolves a reference against the address as a browser resolves a link, and refuses one that leaves the
scheme, the host or the port:

```php
$api = ServiceUrl::fromString('http://whisper:8080/v1/', $hosts);

$api->resolve('inference');      // http://whisper:8080/v1/inference
$api->resolve('/health?full=1'); // http://whisper:8080/health?full=1
$api->resolve('//gotenberg/');   // InvalidUrl: leaves the service at http://whisper:8080
$api->resolve('https://whisper:8080/'); // InvalidUrl: another scheme
```

A reference longer than 2048 characters is refused before it is resolved.

## Clients that read their address this way

- A client of a whisper.cpp server reads its base address, `http://whisper:8080`, from the configuration with
  `ServiceUrl::fromString()`, then resolves `/inference` on it.
- A client of a PDF renderer, such as the Gotenberg at `http://gotenberg:3000` that `phil/paged-media` sends its
  documents to, reads its base address the same way, then resolves `/forms/chromium/convert/html` on it.

A Symfony application builds one `ServiceHosts` from a parameter — `%env(csv:SERVICE_HOSTS)%`, say — and shares it:
the list is immutable and holds nothing of a request.
