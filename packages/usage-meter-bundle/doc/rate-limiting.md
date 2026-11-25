# Rate limiting

The rate limiters of this bundle are limiters of `phil/rate-limit-bundle` that count on a meter. That bundle holds the
requests to them — by `#[RateLimit]`, the route default `_rate_limit` or the `rate_limit` option of a form — and answers
the refusals; this one counts each request on the limiter's meter, for the request's key. A meter is a meter: a rolling
window makes a sliding limit — 600 requests in the last hour, in buckets of a minute —, a calendar period a daily
allowance, a billing cycle a plan's allowance.

```yaml
phil_rate_limit:
    headers: draft

phil_usage_meter:
    meters:
        requests: { limit: 600, rolling: 3600, buckets: 60 }
        reports: { limit: 20, calendar: day }
    rate_limiters:
        api: { meter: requests, key: ip }
        members: { meter: requests, key: user }
        reports: { meter: reports, key: App\Usage\AccountKey, amount: 1 }
```

Each entry of `rate_limiters` is a limiter of that name, beside those of `phil_rate_limit.limiters`: two limiters of one
name fail the container build. `PhilRateLimitBundle` must be registered.

## Limiting a controller

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
final class SearchController
{
    #[RateLimit('reports', amount: 5)]
    public function report(): Response
    {
        // ...
    }
}
```

The order of the rules, the route default, the forms and the unknown names are those of `phil/rate-limit-bundle`.

## Who a request counts for

The limiter's `key` — or the rule's — is a key strategy of `phil/rate-limit-bundle`: `ip` and `user` as HMAC-SHA-256
hashes under its secret, `user_or_ip`, `ip_route`, or a service implementing its `KeyResolver`. The key is the meter's
subject: the store never holds an address or an email. A strategy that finds no key leaves the request uncounted; pair
a `user` limiter with an `ip` one, or take `user_or_ip`, so that anonymous requests are limited too.

## Retries and `Idempotency-Key`

A limiter with `idempotency_key: true` counts once a request the client sends again with the same `Idempotency-Key`
header — a payment form submitted twice, a call retried after a timeout:

```yaml
phil_usage_meter:
    rate_limiters:
        orders: { meter: orders, key: user, idempotency_key: true }
```

```http
POST /orders HTTP/1.1
Idempotency-Key: "8e03978e-40d5-43e8-bc93-6894a57f9324"
```

- Only a method that changes something is keyed: POST, PUT, PATCH and DELETE. A GET, HEAD or OPTIONS request is
  counted every time, whatever header it carries, so that a client cannot read for free.
- The value is 1 to 255 printable ASCII characters, quoted as a structured-field string or not, without a quote or a
  backslash inside: `"abc"` and `abc` are the same key. Another value is answered `400 Bad Request`. A request
  without the header is counted as usual.
- The store never keeps the client's value: it keeps the HMAC-SHA-256, under a key derived from `idempotency_secret` by
HKDF-SHA256 under `phil_usage_meter.meter_limiter`, of the limiter's
  name, the method, the URI with its query, the SHA-256 of the body and the value. The same key on another action,
  another URI or another body is another key, and counts; two limiters keyed on one meter count the request each. A
  key is scoped to the subject, so one client's keys never meet another's.
- A replayed request still reaches the controller: the limiter only refrains from counting it again. Keep the action
  itself idempotent, by the same header, when it must not happen twice.

A limiter without `idempotency_key` ignores the header. Code that meters an action with a key of its own — a message
identifier, a job identifier — calls `UsageMeter::consume()` itself.

## The answer

When a request would take its subject past the quota, nothing is counted and `phil/rate-limit-bundle` answers
`429 Too Many Requests` with `Retry-After` — the seconds until the window frees some of its use: the next period, or the
oldest bucket leaving a rolling window — and its rate-limit headers: the meter's limit for that subject, what is left,
and the same seconds as the reset. A counted request gets the headers of the limiter with the least left. A limiter
whose meter has no limit for the subject adds none.

The limit is the subject's: the limit providers and subscription features apply to rate limiters as to any meter, so
a paying account may be allowed more requests than an anonymous address.
