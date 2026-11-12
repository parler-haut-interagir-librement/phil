# Limiting requests

A rule names a limiter and, optionally, what one request consumes and who it counts for. It is written three ways.

## The attribute

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
final class ExportController
{
    #[RateLimit('exports', amount: 5, key: 'user')]
    public function export(): Response
    {
        // ...
    }
}
```

`#[RateLimit]` goes on a controller class or an action, and repeats. An empty name, an empty key or an amount below one
is refused when the attribute is built.

## The route default

```yaml
search:
    path: /search
    controller: App\Controller\SearchController
    defaults:
        _rate_limit: [api, { limiter: search, key: ip_route, amount: 2 }]
```

`_rate_limit` holds a limiter's name, a map of `limiter`, `amount` and `key`, or a list of up to sixteen of them.

## The order

The attributes of the class come first, then those of the action, then the route's rules. Each consumes on its limiter
in that order; the first that refuses stops the request, and those before it keep what they consumed. A rule whose key
strategy finds no key — `user` without a signed-in user — is left out.

## What is checked when the application is built

`Infrastructure\Http\RateLimitCacheWarmer` reads every route when Symfony builds the container: the attributes of its
controller and its `_rate_limit` default. A limiter or a key strategy nothing declares, or a rule of another shape,
stops the build with the name of the route and what is declared. The warmer is not optional, so `cache:warmup`,
`cache:clear` and the first request of a fresh container all run it.

A controller written as a closure has no class to read: its rules are checked at its first request, where the same
mistake throws `UnknownLimiter`.

Only the main request is counted: a sub-request, such as a fragment or the error controller, is not.

## Other packages' limiters

A limiter is any service implementing `Application\Contract\Limiter`, tagged `phil_rate_limit.limiter` with its `name`
and, optionally, its default `key` and `amount`. A package that counts requests its own way — on quotas, on a billing
meter — gives its limiters this tag, and the rules name them like the bundle's.
