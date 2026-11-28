# Limiting requests

A rule names a limiter and, optionally, what one request consumes and who it counts for. It is written three ways:
Symfony's `#[RateLimit]` attribute on a controller, the route default `_rate_limit`, and the `rate_limit` option of a
form ([Forms](forms.md)).

## Symfony's attribute

```php
use Symfony\Component\HttpKernel\Attribute\RateLimit;

#[RateLimit('api')]
final class ExportController
{
    #[RateLimit('exports', key: 'user', tokens: 5)]
    public function export(): Response
    {
        // ...
    }
}
```

The attribute is Symfony's own, applied by Symfony's listener. Each limiter of the bundle is a factory tagged
`rate_limiter` with its name, beside the limiters of the framework's `rate_limiter` configuration, so the attribute
names them alike. On a limiter of the bundle:

| Option | What it does |
|---|---|
| `limiter` | The limiter's name. |
| `key` | Left out, or the key Symfony computes for it — the client's address and the path —: the limiter's own key strategy. The name of a key strategy — `ip`, `user`, `user_or_ip`, `ip_route` or a `KeyResolver` service id —: that strategy. Anything else — a route parameter written `{id}`, an expression's result —: that value, kept as its keyed hash. |
| `tokens` | What one request consumes; Symfony's default is one, and the limiter's `amount` does not apply. |
| `methods` | The HTTP methods limited, as Symfony reads them. |
| `if` | A boolean, a closure or an expression: when false, nothing is consumed. Never make it depend on what the client chooses. |
| `exposeHeaders` | Symfony's `X-RateLimit-*` headers, beside the bundle's own `RateLimit-Policy` and `RateLimit`. |

The consumption goes through the bundle: the key is hashed, a limiter in observation lets the request through, a
refusal dispatches `RateLimitExceeded` and Symfony's `RateLimitExceededEvent` and is answered by the bundle's responder,
and the response carries the bundle's headers. A strategy that finds no key — `user` without a signed-in user —
consumes nothing.

An action may ask for what the attribute consumed, without consuming again:

```php
use Symfony\Component\RateLimiter\RateLimit as ConsumedRateLimit;

#[RateLimit('exports', key: 'user', tokens: 5)]
public function export(?ConsumedRateLimit $limit): Response
```

The argument is nullable when no attribute may apply, a `methods` or an `if` leaving the request out; otherwise Symfony
throws a `LogicException`. It holds the limits of the attributes only, not those of the route default.

The attribute needs the framework's `rate_limiter`, enabled by default: with it disabled, Symfony's listener is not
there, and the build stops on the first route whose controller carries the attribute.

## The route default

```yaml
search:
    path: /search
    controller: App\Controller\SearchController
    defaults:
        _rate_limit: [api, { limiter: search, key: ip_route, amount: 2 }]
```

`_rate_limit` holds a limiter's name, a map of `limiter`, `amount` and `key`, or a list of up to sixteen of them; each
is read as a `Domain\ValueObject\Rule`. The listener reads the rules on `kernel.controller`, then removes the request
attribute: Symfony's attribute listener keeps what its attributes consumed under that same name.

## The order

On one request, the limits are consumed in this order, the first refusal stopping the request and the limits before it
keeping what they consumed:

| Step | When | What |
|---|---|---|
| 1 | `kernel.controller`, before Symfony's attribute listener | The route default `_rate_limit`, in the order written. |
| 2 | `kernel.controller`, once the controller is known | Symfony's `#[RateLimit]`, those of the class before those of the action, before the arguments are resolved: a refused request loads no entity. |
| 3 | `kernel.controller_arguments` | `#[IsGranted]`, and a `#[RateLimit]` whose `key` or `if` reads the arguments: a closure, or an expression that names `args`. |
| 4 | The controller | A form's `rate_limit` option, on the submission. |

A request that `#[IsGranted]` refuses has therefore consumed its limits already: an attacker without access still
spends the limit, and an honest client refused by access does too. To count only the requests that pass the access
check, give the limit an `if` that reads the arguments, which moves it after `#[IsGranted]`, or limit the action with
the form option.

## What is checked when the application is built

`Infrastructure\Http\RateLimitCacheWarmer` reads every route when Symfony builds the container: its `_rate_limit`
default, each rule naming a declared limiter and key strategy, and the `#[RateLimit]` attributes of its controller,
each naming a limiter of the bundle or of the framework's configuration. A name nothing declares, or a rule of another
shape, stops the build with the name of the route and what is declared. The warmer is not optional, so `cache:warmup`,
`cache:clear` and the first request of a fresh container all run it.

A controller written as a closure has no class to read: its attributes are checked at its first request, where
Symfony throws on an unknown limiter.

The route default is counted on the main request only: a sub-request, such as a fragment or the error controller, is
not. Symfony's attribute counts wherever Symfony applies it.

## Other packages' limiters

A limiter is any service implementing `Application\Contract\Limiter`, tagged `phil_rate_limit.limiter` with its `name`
and, optionally, its default `key` and `amount`. A package that counts requests its own way — on quotas, on a billing
meter — gives its limiters this tag, and the attribute, the route default and the forms name them like the bundle's.
A limiter of the framework's configuration of the same name stops the build: the attribute could not tell them apart.
