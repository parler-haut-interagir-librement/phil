# phil/rate-limit

**The production guard of rate limits over `symfony/rate-limiter`: a container that would build a limit that does not
limit fails to build.**

A rate limit counts in a store. When that store is a cache pool or a storage each server keeps for itself — the
filesystem pool `cache.app` is one by default —, an application behind a load balancer counts on each server apart, and
a client can spend the limit once per server. A limit enabled without `symfony/rate-limiter` installed fails only on
the first request that needs it.

phil/rate-limit turns both into errors of the container build:

- `RateLimitGuard::requireComponent()` refuses a rate limit enabled without the component, and names the option that
  turns it off;
- `RateLimitGuard::watch()` notes each store a bundle configures, and the compiler pass `RateLimitGuard` refuses, in the
  `prod` environment, one that `StoreLocality` finds local to the server: a pool on the filesystem, APCu, array or
  system adapter of `symfony/cache`, the `InMemoryStorage` of `symfony/rate-limiter`, or a `CacheStorage` given such a
  pool. An application on one server says so, and its stores are not noted.

It reads the service definitions only: limiting stays the work of `symfony/rate-limiter`, which the library neither
wraps nor replaces. Several bundles share one hidden parameter and one check, however many of them add the pass.

It is a plain PHP library whose requirements are `symfony/config` and `symfony/dependency-injection`; the cache and the
rate limiter components are named by their classes and need not be installed.

## Status

| Part | State |
|---|---|
| A rate limit refused without `symfony/rate-limiter` | Implemented and tested |
| Stores noted by configuration path, `single_server`, the option that turns a limit off | Implemented and tested |
| Local pools by adapter or class, `InMemoryStorage`, `CacheStorage` followed to its pool, aliases and parents followed, bounded | Implemented and tested |
| The check in `prod` only, once for every bundle | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, adding the guard to a bundle. |
| [The guard](doc/guard.md) | What is refused, when, the messages, what is local and what is shared. |
| [Extension points](doc/extension-points.md) | Using the guard from a bundle, deciding locality yourself. |
| [Testing](doc/testing.md) | The package's tests. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/rate-limit
```

```php
use Phil\RateLimit\RateLimitGuard;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class AcmeBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RateLimitGuard());
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'acme.rate_limit');
        RateLimitGuard::watch($builder, $config['rate_limit']['cache_pool'], 'acme.rate_limit.cache_pool', $config['rate_limit']['single_server'], 'enabled');
        // ... the CacheStorage and the RateLimiterFactory of symfony/rate-limiter
    }
}
```

## Requirements

PHP 8.5 or later, `symfony/config` and `symfony/dependency-injection` 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
