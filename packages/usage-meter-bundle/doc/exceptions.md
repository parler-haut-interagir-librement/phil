# Exceptions

The bundle has no exception of its own.

| When | What is thrown |
|---|---|
| The container is built with a meter that has no period or two, a limit that is not `unlimited` or a whole number from zero, an unknown time zone, buckets that do not divide the window, an anchor that is not a date, or a name that is not a meter name | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`, prefixed `phil_usage_meter.meters:`, the library's `InvalidUsage` as its cause |
| The container is built with a rate limiter on a meter the `meters` section does not declare | `InvalidConfigurationException` |
| The container is built with rate limiters and without `PhilRateLimitBundle` registered | `InvalidConfigurationException` |
| The container is built with `store: dbal` without `doctrine/dbal` | `InvalidConfigurationException` |
| A keyed limiter receives a malformed `Idempotency-Key` header | `Symfony\Component\HttpKernel\Exception\BadRequestHttpException`, rendered as 400 |
| A request would take its subject past the quota of a limiter's meter | `phil/rate-limit-bundle`'s `TooManyRequests`, rendered as 429 |

The library's own exceptions — `QuotaExceeded`, `UnknownMeter`, `InvalidUsage` — reach the code that calls
`UsageMeter` directly.
