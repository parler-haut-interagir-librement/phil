# phil/rate-limit-bundle

**Rate limits declared on Symfony controllers, routes and forms.**

phil/rate-limit-bundle holds requests to named limiters: `#[RateLimit]` on a controller or an action, the route default
`_rate_limit`, and the `rate_limit` option of a form. The limiters are fixed windows, sliding windows, token buckets and
compounds of `symfony/rate-limiter`, declared in the bundle's configuration with their storage, or those of other
packages. Each request counts under a key — the client's address, the signed-in user, the address and the route, or the
application's own —, always a keyed hash, never an address in clear. A refusal is answered `429 Too Many Requests` with
`Retry-After` and the `RateLimit-Policy` and `RateLimit` fields of the IETF draft: a problem in JSON to an API, a Twig
page to a browser, an error on the form. Each refusal is dispatched as an event.

## Status

| Part | State |
|---|---|
| `#[RateLimit]` on classes and actions, the route default `_rate_limit` | Implemented and tested |
| Fixed window, sliding window, token bucket, compound and factory limiters, checked at build | Implemented and tested |
| Keys `ip`, `user`, `user_or_ip`, `ip_route` and the application's, the network with phil/ip-anonymizer | Implemented and tested |
| `429` with `Retry-After`, draft or legacy `RateLimit` headers, problem JSON, Twig page in English and French | Implemented and tested |
| Form submissions limited by form name | Implemented and tested |
| The `RateLimitExceeded` event, limiters of other packages by tag | Implemented and tested |
| Limiters in observation: the `RateLimitObserved` event and a warning, without refusing | Implemented and tested |
| A store local to each server refused in `prod`, with the guard of phil/rate-limit | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the storage. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Limiting requests](doc/rules.md) | The attribute, the route default, the order of the rules. |
| [Keys](doc/keys.md) | Who a request counts for, and how the keys are hashed. |
| [Responses](doc/responses.md) | The `429`, its headers, the problem in JSON, the page, the event. |
| [Forms](doc/forms.md) | Limiting the submissions of a form by its name. |
| [Extension points](doc/extension-points.md) | Limiters, key strategies, the event, the template. |
| [Testing](doc/testing.md) | The bundle's tests and its benchmark. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_rate_limit.yaml
phil_rate_limit:
    cache_pool: cache.rate_limits
    limiters:
        api: { policy: sliding_window, limit: 100, interval: '1 minute' }
        reset: { policy: fixed_window, limit: 5, interval: '15 minutes', key: ip_route }
        contact: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes' } }
    forms:
        contact: contact
```

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
final class SearchController
{
    #[RateLimit('reset')]
    public function resetPassword(): Response
}
```

A login form is limited by Symfony itself: `security.firewalls.<name>.login_throttling` counts each attempt before the
password is checked and groups IPv6 addresses by /64. The bundle limits everything else: APIs, the other forms, the
controllers and routes. See [Login forms](doc/configuration.md#login-forms).

## Requirements

PHP 8.5 or later and Symfony 8.2 or later with FrameworkBundle; TwigBundle for the refusal page, the form component for
forms, security for the user keys, phil/ip-anonymizer for network keys, a PSR-3 logger for the warnings of a limiter
in observation. phil/rate-limit, required, guards the stores in production.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
