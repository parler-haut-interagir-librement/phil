# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, builds `ContainerBuilder` instances by hand: no kernel, no cache, no network. It
covers each local adapter and class, the parents, aliases and `CacheStorage` followed, the bound on the chain and a loop,
the messages with and without the option that turns a limit off, and the check in and out of `prod`. In the monorepo,
Infection holds the package at 100 % of covered mutants.

## A bundle that uses the guard

A bundle tests its own wiring by loading its extension into a `ContainerBuilder` and reading
`RateLimitGuard::watched($container)`, and its refusal with a kernel booted in `prod` on its default `cache.app`.
