# Addresses

`Url::fromString()` reads an absolute web address as a browser reads it: PHP's URI extension parses it by the WHATWG
URL Standard, `Uri\WhatWg\Url`, then `Url` applies its own rules below.

| Accepted | Refused |
|---|---|
| `http` and `https`, in any case | Any other scheme, an address without one, text around it |
| A host name of labels joined by dots, international ones included | A bare name (`localhost`), an IP address (`http://192.168.0.1/`, `http://[::1]/`), a top-level domain of digits, an underscore, an empty label (`https://.`, `https://shop..example/`, `https://example.com../`), a label over 63 characters, a host over 253 |
| A port from 1 to 65535, a path, a query, a fragment | Port 0, spaces, a user name or a password (`https://ada@…`, `https://@…`), a third slash or a backslash before the host (`https:///path`, `https://\example.com/`) |
| 2048 characters at most, as written and once normalised | Longer: `<` written 1000 times becomes `%3C` 1000 times |

Every refusal throws `InvalidUrl`, an `UrlException`.

## Normalisation

| Read | Kept as |
|---|---|
| `HTTPS://Example.COM/Shop` | `https://example.com/Shop` — the path keeps its case |
| `https://example.com` | `https://example.com/` |
| `https://example.com:443/` | `https://example.com/` — the default port dropped |
| `https://bücher.example/` | `https://xn--bcher-kva.example/` |
| `https://example.com./?#` | `https://example.com/` — one trailing dot, empty query and fragment dropped |
| `https://example.com/a/./b/../c` | `https://example.com/a/c` — the segments `.` and `..` resolved |
| `https://example.com/a/%2e%2E/b`, `/a/.%2e/b`, `/a/%2e./b` | `https://example.com/b` — escaped dots are dots |
| `https://example.com\a\..\b` | `https://example.com/b` — a backslash is a slash, as in a browser |
| `https://example.com/a%2Fb` | `https://example.com/a%2Fb` — an escaped slash stays in its segment |
| `https://example.com/<script>"` | `https://example.com/%3Cscript%3E%22` |
| `https://example.com/thé?q=<b>#"x"` | `https://example.com/th%C3%A9?q=%3Cb%3E#%22x%22` |

The path, the query and the fragment are percent-encoded by the sets of the WHATWG URL Standard: in the path, a
space, `"`, `#`, `<`, `>`, `?`, a backtick, `{`, `}` and every character outside printable ASCII; in the query, a
space, `"`, `#`, `<`, `>`, `'` and the characters outside printable ASCII; in the fragment, a space, `"`, `<`, `>`, a
backtick and the characters outside printable ASCII. A letter outside ASCII is encoded as UTF-8. An escape already
written is kept as written, neither decoded nor put in uppercase: `%7e` and `~` are two different addresses for
`equals()`, and `%2F` is a character of its segment, never a separator, so `segments()` reads `/docs/..%2Fadmin` as
`['docs', '..%2Fadmin']`. A `..` inside the query or the fragment is text, kept as written.

`equals()` compares normalised addresses.

## Allowing paths by prefix

`path` and `segments()` give the path the browser opens, so a prefix checked on them cannot be climbed out of:
`https://example.com/docs/../admin`, `/docs/%2e%2e/admin` and `/docs\..\admin` all have the path `/admin`.

```php
use Phil\Url\Url;

function isDocumentation(string $text): bool
{
    $url = Url::fromString($text);

    return $url->isOn('example.com') && 'docs' === ($url->segments()[0] ?? null);
}

isDocumentation('https://example.com/docs/install'); // true
isDocumentation('https://example.com/docs/../admin'); // false: its path is /admin
```

Check the prefix on `Url`, never on the text it was read from.

## Reading it

| Member | Gives |
|---|---|
| `scheme`, `host`, `port`, `path`, `query`, `fragment` | The parts; `port` is `null` for the default |
| `toString()` | The normalised address |
| `display()` | As people read it: `example.com/shop` for `https://www.example.com/shop/`, `bücher.example` for `https://xn--bcher-kva.example/`; a host whose letters mix scripts or pass for another's stays in ASCII; the escaped letters of the path and query are shown as letters, `example.com/thé`, while an escape of ASCII, an invisible character, a space or a control stays escaped |
| `isOn(...$domains)` | Whether the host is one of these domains or under one: `m.facebook.com` is on `facebook.com` |
| `segments()` | The path's segments, empty ones dropped |
