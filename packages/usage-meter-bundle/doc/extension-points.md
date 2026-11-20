# Extension points

The bundle wires the library; what is specific to the application stays in its own services, named in the
configuration.

## A store

Any service implementing `Phil\UsageMeter\UsageStore`, named in `store`, replaces the bundle's: a store on Redis, on
another database API. It keeps the promises of the interface — an atomic increment under a ceiling, a key counted once,
a refused increment leaving nothing behind — and the library's contract tests check them.

```yaml
phil_usage_meter:
    store: App\Usage\RedisUsageStore
```

## Limit providers

A `Phil\UsageMeter\LimitProvider` says how much a subject may use of a meter, or `null` to let the next one say. The
providers of `limit_providers` are asked in order, the subscription features after them, the meter's own limit last:

```php
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\LimitProvider;
use Phil\UsageMeter\Meter;

final readonly class AdminOverrides implements LimitProvider
{
    public function __construct(private OverrideRepository $overrides)
    {
    }

    public function limitFor(string $subject, Meter $meter): ?Limit
    {
        $value = $this->overrides->find($subject, $meter->name);

        return null === $value ? null : Limit::parse($value);
    }
}
```

```yaml
phil_usage_meter:
    limit_providers: [App\Usage\AdminOverrides]
    subscription_features: true
```

## An anchor provider

A `Phil\UsageMeter\AnchorProvider` gives a subject its own anchor — the day it subscribed — for the meters counted by
billing cycle; the meter's `billing` anchor serves the subjects it returns `null` for.

```yaml
phil_usage_meter:
    anchor_provider: App\Usage\SubscriptionAnchors
```

## Keys of the rate limiters

Who a request counts for is a key strategy of `phil/rate-limit-bundle`, named in a limiter's `key`: the bundle's `ip`,
`user`, `user_or_ip` and `ip_route`, or a service implementing its `KeyResolver` — an account, an API key's identifier.
The key is the meter's subject, 1 to 200 bytes: an identifier, never personal data in clear. See that bundle's
documentation of keys.

## The meter itself

`Phil\UsageMeter\UsageMeter` is a public service, autowired by its class: controllers, message handlers and commands
call `consume()` before an action, `record()` after one, `usage()` to show what is left.
