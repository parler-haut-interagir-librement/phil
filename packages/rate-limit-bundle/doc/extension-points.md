# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports written by the application or another package

### `Limiter`

`Phil\RateLimitBundle\Application\Contract\Limiter` — a limiter the rules name. Register it tagged
`phil_rate_limit.limiter` with `name`, and optionally `key`, `amount` and `observe`, a boolean that reports its refusals
without enforcing them.

| Method | Contract |
|---|---|
| `consume(string $key, int $amount, Request $request): Decision` | Consumes and decides; keeps its state in its storage, never in the service |

```yaml
services:
    app.quota_limiter:
        class: App\RateLimit\QuotaLimiter
        tags: [{ name: phil_rate_limit.limiter, key: user, amount: 1 }]
```

The name is 1 to 64 letters, digits, `_`, `.` and `-`; two limiters of one name, a tag without a name, an amount below
one or a class that does not implement the interface fail the build.

### `KeyResolver`

`Phil\RateLimitBundle\Application\Contract\KeyResolver` — who a request counts for. Autoconfigured services are
strategies under their id; a tag `phil_rate_limit.key_resolver` with `strategy` gives a shorter name.

| Method | Contract |
|---|---|
| `keyOf(Request $request): ?string` | A key, 1 to 200 bytes, hashed when it identifies a person; `null` leaves the request uncounted |

### `AddressNormalizer`

`Phil\RateLimitBundle\Application\Contract\AddressNormalizer` — the address hashed by the `ip` key. Replace the
service `phil_rate_limit.network_address`, or decorate `phil_rate_limit.key.ip`.

| Method | Contract |
|---|---|
| `normalize(string $address): string` | The text hashed in place of the address |

## Services

| Service | Role |
|---|---|
| `Phil\RateLimitBundle\Application\RateLimitEnforcer` (public) | Holds a request to rules, from code that limits its own way |
| `phil_rate_limit.key.ip`, `.user`, `.user_or_ip`, `.ip_route` | The key strategies |
| `phil_rate_limit.limiter.<name>`, `phil_rate_limit.factory.<name>` | Each configured limiter and its factory |

```php
$decision = $enforcer->enforce($request, [new RateLimit('exports', 5)]);
```

## Events

| Event | When |
|---|---|
| `Phil\RateLimitBundle\Application\Event\RateLimitExceeded` | A limiter refused a request or a submission, before it is answered |
| `Phil\RateLimitBundle\Application\Event\RateLimitObserved` | A limiter in observation would have refused a request or a submission, which goes on |

## Templates and translations

| Item | Override |
|---|---|
| `@PhilRateLimit/too_many_requests.html.twig` | `templates/bundles/PhilRateLimitBundle/`, or `error_template` |
| The `phil_rate_limit` domain | `translations/phil_rate_limit.<locale>.yaml` |
