# Installation

```sh
composer require phil/rate-limit
```

The package needs PHP 8.5 or later, `symfony/config` and `symfony/dependency-injection` 8.2 or later. It suggests
`symfony/rate-limiter`, whose limiters it guards; neither the rate limiter nor `symfony/cache` is required, since the
library names their classes without loading them.

## In a bundle

A bundle that builds rate limits adds the pass once, in `build()`, and notes its stores while it loads its
configuration:

```php
public function build(ContainerBuilder $container): void
{
    $container->addCompilerPass(new RateLimitGuard());
}
```

Every bundle may add the pass: the first one to run checks the stores of all of them, and the others find nothing left.
See [The guard](guard.md) for what `requireComponent()` and `watch()` take.

## In an application

An application has nothing to install by hand: the bundles that need the guard require the library. On one server, it
sets the `single_server` option those bundles offer, and keeps its local pool.
