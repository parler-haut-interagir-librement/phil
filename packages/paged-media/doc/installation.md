# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, enums with constants |
| `ext-ctype` | Page selections, colours and counts are checked character by character |
| `ext-mbstring` | Text is split into characters and converted to Windows-1252 and UTF-16 |
| `ext-zlib` | Streams are compressed, and transparent PNG images decompressed |
| `ext-uri` | Gotenberg's service addresses are read by the WHATWG URL parser |
| `phil/url` | The public addresses Gotenberg fetches, downloads and timestamp authorities, are read and checked |

The native writer has no other dependency: no binary, no service, no font file of its own.

The Gotenberg client needs Symfony HttpClient, which the library suggests, and a Gotenberg 9 to talk to:

```sh
composer require symfony/http-client
docker run --rm -p 3000:3000 gotenberg/gotenberg:9
```

## Installing

```sh
composer require phil/paged-media
```

It is a library, not a bundle: there is nothing to register. A Symfony application creates a `Document` or a
`Gotenberg` where it needs one, wraps them in services of its own, or installs `phil/paged-media-bundle`, which
configures both behind one service.
