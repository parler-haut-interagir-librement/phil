# Responses

## An accepted request

The response carries the headers of the limiter with the least left — of the route default and of Symfony's
`#[RateLimit]` alike —, when one of its limiters has a limit:

```http
RateLimit-Policy: "api";q=100;w=60
RateLimit: "api";r=42;t=17
```

`q` is the limit, `w` the window in seconds for a fixed or a sliding window, `r` what is left, and `t` the seconds until
the limiter is back to its full capacity. When a policy gives no such moment, `t` is the window's length, an upper
bound. With `headers: legacy`, the same values are `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset`.

These are the fields of the IETF draft, which Symfony does not write. Symfony's `exposeHeaders` option writes its own
`X-RateLimit-Limit`, `X-RateLimit-Remaining` and `X-RateLimit-Reset` beside them, and makes the response private; leave
it off on a login or a password reset, where the headers tell an attacker how many attempts are left.

## A refusal

`TooManyRequests`, a `TooManyRequestsHttpException`, is answered `429 Too Many Requests` with `Retry-After` — the
seconds until the limiter allows the next hit, rounded up — and the headers of the limiter that refused, whether a route
default or Symfony's `#[RateLimit]` named it.

A refusal of Symfony's attribute on a limiter of the framework's `rate_limiter` configuration, a
`TooManyRequestsHttpException` caused by a `RateLimitExceededException`, gets the same body with its `Retry-After`
alone: the bundle does not know that limiter's policy, and names no limiter to the page.

To a request whose format is JSON — `json`, `jsonld`, `jsonapi` or `problem` — or whose first acceptable type is
`application/json` or ends with `+json`, the body is an RFC 9457 problem:

```json
{"type": "about:blank", "title": "Too many requests", "status": 429, "detail": "Too many requests. Try again in 12 seconds.", "retry_after": 12}
```

To another request, with Twig, the body is `error_template`, rendered with `locale`, `title`, `detail`, `retry_after` and
`limiter`. Without Twig, Symfony's error page answers, with the same headers. The title and the detail are translated in
the `phil_rate_limit` domain; the bundle gives English and French.

Override the page in `templates/bundles/PhilRateLimitBundle/too_many_requests.html.twig`, or name another template.

## The events

Before a refusal is answered, `Application\Event\RateLimitExceeded` is dispatched with the limiter's name, the key the
request counted under — a hash —, the decision, the route's name and, for a form, its name. Listen to it to log, count
or alert:

```php
#[AsEventListener]
final readonly class LogRefusals
{
    public function __invoke(RateLimitExceeded $event): void
    {
        $this->logger->notice('Rate limit {limiter} refused a request.', ['limiter' => $event->limiter, 'route' => $event->route]);
    }
}
```

Symfony's `RateLimitExceededEvent` follows, with the limiter's name, the same hashed key and a `RateLimit` of the
decision, so that a listener of Symfony's event hears the bundle's refusals too. On a limiter of the framework's
configuration, Symfony's listener dispatches it itself, with the key as Symfony computed it, before any hashing, and
`RateLimitExceeded` is not dispatched.

A limiter in observation refuses nothing: where it would have, `Application\Event\RateLimitObserved` is dispatched with
the same fields, a warning is logged, and the request goes on. See [Observing a limit](configuration.md#observing-a-limit).
