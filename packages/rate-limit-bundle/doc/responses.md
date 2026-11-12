# Responses

## An accepted request

The response carries the headers of the limiter with the least left, when one of its limiters has a limit:

```http
RateLimit-Policy: "api";q=100;w=60
RateLimit: "api";r=42;t=17
```

`q` is the limit, `w` the window in seconds for a fixed or a sliding window, `r` what is left, and `t` the seconds until
the limiter is back to its full capacity. When a policy gives no such moment, `t` is the window's length, an upper
bound. With `headers: legacy`, the same values are `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset`.

## A refusal

`TooManyRequests`, a `TooManyRequestsHttpException`, is answered `429 Too Many Requests` with `Retry-After` — the
seconds until the limiter allows the next hit, rounded up — and the headers of the limiter that refused.

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

A limiter in observation refuses nothing: where it would have, `Application\Event\RateLimitObserved` is dispatched with
the same fields, a warning is logged, and the request goes on. See [Observing a limit](configuration.md#observing-a-limit).
