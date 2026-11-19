# Scrubbing

`ErrorTracker` hands every event to the `Scrubber` before the fingerprint, the store or the alerter see it.

## What is filtered

| Where | What | Kept as |
|---|---|---|
| Headers and tags | a sensitive name | the name, with `[Filtered]` |
| The request's address | every value of the query, whatever its name | `name=[Filtered]` |
| The request's address | the fragment | dropped |
| The request's address, `Referer`, `Origin` | the segment after a declared token path prefix | `[Filtered]` |
| `Referer`, `Origin` | every value of the query, and the fragment | as for the request's address |
| Every text | an e-mail address | `[email]` |
| Every text | the credentials of an address, `scheme://user:password@` | `scheme://[Filtered]@` |
| Every text | a `Bearer`, `Basic` or `Token` credential | `Bearer [Filtered]` |
| Every text | a `name=value` or `name: value` pair with a sensitive name | `name=[Filtered]` |

The texts are the messages, the values of the other headers and tags, and the path of the address. The frames are
left as they are: they hold paths and function names, never arguments.

A name is sensitive when it contains, case-insensitively, one of `pass`, `secret`, `token`, `api-key`, `api_key`,
`apikey`, `auth`, `credential`, `session`, `cookie`, `csrf`, `xsrf`, `private-key`, `private_key`, `signature`, `card`,
`cvv`, `iban`, `ssn`. `Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Csrf-Token` and `user_password` are
filtered; so is a name such as `author`, on the safe side.

## Fields of the application

```php
use Phil\ErrorTracking\Scrubber;

$scrubber = new Scrubber(['birth_date', 'x-tenant-secret']);
```

Each field is matched like the defaults, as a case-insensitive part of a name.

## Tokens in the path

A token in the path, such as the one of `/reset-password/reset/{token}`, survives the filtering of the query. The
routes that carry one are declared, by the prefix the token follows:

```php
use Phil\ErrorTracking\Scrubber;

$scrubber = new Scrubber([], ['/reset-password/reset/', '/invitation/']);

$scrubber->url('https://example.com/reset-password/reset/a1b2c3?lang=fr');
// https://example.com/reset-password/reset/[Filtered]?lang=[Filtered]
```

- A prefix is one or more path segments between slashes. Anything else (`reset`, `/reset`, `shop/reset/`) throws
  `InvalidOption`.
- A prefix matches where the path starts only, after the scheme and the host, without regard to case:
  `/shop/reset-password/reset/x` is not under `/reset-password/reset/`.
- Where one declared prefix starts another (`/p/` and `/p/x/`), the segment after each is filtered: `/p/x/abc`
  becomes `/p/[Filtered]/[Filtered]`.
- The request's address, `Referer` and `Origin` are filtered this way; a message is not, being free text.

## The client's address

The scrubber never sees a raw address: `RequestSummary::anonymized()` has already turned it into what a strategy of
`phil/ip-anonymizer` keeps — a truncated address, a keyed digest, a network. The headers through which proxies pass on
the raw address — `Forwarded`, `X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, `True-Client-IP`, `X-Client-IP`
and the others of `RequestSummary::CLIENT_ADDRESS_HEADERS` — never reach it either: `RequestSummary` drops them.

The texts are not searched for addresses: a message that quotes the address of a server, such as a database that does
not answer, keeps it.

## What the scrubber cannot know

A message that holds a name, a postal address or an identifier of the application in plain words keeps it, past the
patterns above. Messages that the application writes itself are the place to leave personal data out.
