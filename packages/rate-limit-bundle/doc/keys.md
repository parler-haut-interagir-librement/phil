# Keys

A key is who a request counts for. A limiter names its default strategy with `key`; a rule names another with its own.

| Strategy | The key |
|---|---|
| `ip` | `ip:` and the HMAC-SHA-256 of the client's address, as Symfony reads it behind its trusted proxies |
| `user` | `user:` and the HMAC-SHA-256 of the signed-in user's identifier, normalized; no key without a user |
| `user_or_ip` | The `user` key, else the `ip` key |
| `ip_route` | `ip_route:` and the HMAC-SHA-256 of the `ip` key and the route's name, or its path without a route |
| a service id | What the service's `KeyResolver::keyOf()` returns |

The hashes are keyed by `secret`, the application's secret by default: the storage never holds an address or an email,
and one client gives the same key from one request to the next. Changing the secret starts every count again.

## Normalized identifiers

A key built from what a person types or is known by counts that person once, whatever the form of the text, as
Symfony's login throttling counts a username. Before the `user` strategy hashes an identifier, `IdentifierKey`
decomposes its compatibility forms (NFKD), folds its case, removes the combining marks, the format characters
(zero-width spaces and joiners, direction marks, soft hyphens) and the control characters, and trims it:

| Typed | Counted as |
|---|---|
| `Ada@Example.org`, `ADA@EXAMPLE.ORG` | `ada@example.org` |
| `Àda`, `A` followed by a combining grave accent | `ada` |
| `ａda` (full-width), `a` + zero-width space + `da` | `ada` |
| `Straße` | `strasse` |

An identifier that is not valid UTF-8 is trimmed and lower-cased byte by byte. A strategy of the application that
builds its key from such a value calls `IdentifierKey::normalize()` before hashing it; one built from an opaque token,
whose case matters, keeps it as it is.

An IPv4 address written as an IPv6 one, `::ffff:203.0.113.7`, as a dual-stack socket reports an IPv4 client, counts as
`203.0.113.7`: one client never holds two counts because two servers listen differently.

A form's submissions count under the key followed by `|form:` and the form's name, apart from the client's other
requests. A key longer than 200 bytes is replaced by `sha256:` and its SHA-256.

## Networks

By default, an IPv6 address counts by its /64, the network one subscriber is given, and an IPv4 address counts whole:
a client cannot escape its limit by changing hosts within its network.

With phil/ip-anonymizer installed and `network.enabled`, the address is truncated to the prefixes given before it is
hashed — /32 in IPv4 and /64 in IPv6 by default.

```yaml
phil_rate_limit:
    network: { enabled: true, ipv4_prefix: 32, ipv6_prefix: 64 }
```

## The application's keys

```php
use Phil\RateLimitBundle\Application\Contract\KeyResolver;

final readonly class ApiClientKey implements KeyResolver
{
    public function keyOf(Request $request): ?string
    {
        $client = $request->attributes->get('api_client_id');

        return \is_string($client) ? 'client:'.$client : null;
    }
}
```

With autoconfiguration, the service is a strategy under its id, which a rule or a limiter names:
`#[RateLimit('api', key: ApiClientKey::class)]`. A service a limiter or a form names as its key joins the strategies
even without autoconfiguration. A key holds no secret and no personal data in clear: hash what identifies a person.
