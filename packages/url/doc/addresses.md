# Addresses

`Url::fromString()` reads an absolute web address.

| Accepted | Refused |
|---|---|
| `http` and `https`, in any case | Any other scheme, an address without one, text around it |
| A host name of labels joined by dots, international ones included | A bare name (`localhost`), an IP address (`http://192.168.0.1/`, `http://[::1]/`), a top-level domain of digits, an underscore, an empty label (`https://.`, `https://shop..example/`, `https://example.com../`), a label over 63 characters, a host over 253 |
| A port from 1 to 65535, a path, a query, a fragment | Port 0, spaces, a user name or a password (`https://ada@…`) |
| 2048 characters at most | Longer |

Every refusal throws `InvalidUrl`, an `UrlException`.

## Normalisation

| Read | Kept as |
|---|---|
| `HTTPS://Example.COM/Shop` | `https://example.com/Shop` — the path keeps its case |
| `https://example.com` | `https://example.com/` |
| `https://example.com:443/` | `https://example.com/` — the default port dropped |
| `https://bücher.example/` | `https://xn--bcher-kva.example/` |
| `https://example.com./?#` | `https://example.com/` — one trailing dot, empty query and fragment dropped |

`equals()` compares normalised addresses.

## Reading it

| Member | Gives |
|---|---|
| `scheme`, `host`, `port`, `path`, `query`, `fragment` | The parts; `port` is `null` for the default |
| `toString()` | The normalised address |
| `display()` | As people read it: `example.com/shop` for `https://www.example.com/shop/`, `bücher.example` for `https://xn--bcher-kva.example/`; a host whose letters mix scripts or pass for another's stays in ASCII |
| `isOn(...$domains)` | Whether the host is one of these domains or under one: `m.facebook.com` is on `facebook.com` |
| `segments()` | The path's segments, empty ones dropped |
