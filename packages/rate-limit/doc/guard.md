# The guard

`RateLimitGuard` fails the container build on a rate limit that would not limit. It checks two things, at two moments.

## A rate limit without its component

```php
RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'acme.rate_limit');
```

When the first argument is false, it throws `InvalidConfigurationException` at once, while the bundle loads its
configuration:

```text
acme.rate_limit needs symfony/rate-limiter: install it, or set "enabled" to false.
```

The bundle passes the result of `interface_exists()` rather than the library testing it, so that a test can ask for
either case.

## A store local to each server

```php
RateLimitGuard::watch($builder, 'cache.app', 'acme.rate_limit.cache_pool', singleServer: false, switch: 'enabled');
```

`watch()` notes a cache pool or a storage service under the configuration path that names it. Nothing is noted when
`singleServer` is true: on one server, a local store counts every request. `switch`, when given, is the option beside
the path that turns the rate limit off, offered in the message.

The notes go to the hidden parameter `.phil_rate_limit.guarded_stores`, keyed by path, so that two limits counting in
the same pool are both reported. `RateLimitGuard::watched()` reads them back.

When the container is compiled, the pass reads the parameter and removes it. Outside the `prod` environment it stops
there: a developer's filesystem pool is what it should be. In `prod`, the first store that `StoreLocality` finds local
throws:

```text
acme.rate_limit.cache_pool: the store "cache.app" is local to each server, so a client could spend the limit once per
server. Name a cache pool on a shared store — Redis, Memcached, a database —, set "single_server" to true on a single
server, or set "enabled" to false.
```

A store whose service is not defined is left to the container, which reports it its own way.

## What is local

`StoreLocality::isLocal()` follows the definitions, not the objects: nothing is built.

| The service | Local |
|---|---|
| A child of `cache.adapter.filesystem`, `cache.adapter.apcu`, `cache.adapter.array` or `cache.adapter.system`, directly or through other pools | Yes |
| A definition of `FilesystemAdapter`, `PhpFilesAdapter`, `ApcuAdapter`, `ArrayAdapter` or `InMemoryStorage`, its class given or read from a parameter | Yes |
| A `CacheStorage` whose first argument, or `$pool`, is a reference to a local pool | Yes |
| A `RateLimiterFactory` whose second argument, or `$storage`, is a reference to a local storage | Yes |
| A limiter of the framework's configuration, `limiter.<name>`, a child of the abstract `limiter` whose storage is local | Yes |
| A `RateLimiterBuilder`, such as the framework's `limiter_builder`, whose first argument, or `$storage`, is a reference to a local storage | Yes |
| An alias of any of these | Yes |
| Any other adapter or storage — Redis, Memcached, PDO, DBAL, a class of the application | No |
| A chain of more than 32 services, or one that loops | No, left to the container |

The framework's `cache.app` is a child of `cache.adapter.filesystem` unless the application configures another adapter,
so a bundle that counts in `cache.app` by default is refused in production until the application names a shared pool.
A `ChainAdapter` is taken as shared, whatever its members: name a pool on a single shared adapter for a rate limit.

## Limiters built at runtime

Symfony's `RateLimiterBuilder` creates factories while the application runs — `slidingWindow()`, `fixedWindow()`,
`tokenBucket()`, `compound()` —, for a limit that depends on the client's plan, say. The container never holds those
factories, so the guard cannot read them. It reads the builder instead: every factory the builder creates counts in the
builder's own storage, so a builder on a shared storage gives shared limiters.

```php
RateLimitGuard::watch($builder, StoreLocality::BUILDER, 'acme.plans', singleServer: false);
```

The framework defines `limiter_builder` on the storage of `rate_limiter.builder`: its `storage_service`, or a
`CacheStorage` on its `cache_pool`, `cache.rate_limiter` by default, a child of `cache.app`. A bundle that creates its
limiters with the builder watches `limiter_builder` beside its other stores.

What the guard cannot see:

- a `RateLimiterBuilder` or a `RateLimiterFactory` built with `new` in the application's code, with its own storage;
- a storage chosen at runtime, after the container is compiled.

Such a limiter is checked by its author: give it a storage service of the container, and watch that service.
