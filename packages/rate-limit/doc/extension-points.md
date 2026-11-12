# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Guard the rate limits of a bundle | Add `new RateLimitGuard()` in `build()`, call `requireComponent()` and `watch()` in `loadExtension()` |
| Accept a local store | Pass `singleServer: true` to `watch()`, usually from a `single_server` option of the bundle |
| Offer the option that turns a limit off | Pass its name as `switch` to `watch()` |
| Ask whether a service is local | Call `new StoreLocality()->isLocal($container, $id)` from a compiler pass |

## Locality of your own

`StoreLocality` is final, and the pass takes it as its only argument. A bundle that knows of another local store — a
pool of its own on shared memory — checks it before noting it, rather than replacing the class:

```php
if (!$this->isSharedMemory($pool)) {
    RateLimitGuard::watch($builder, $pool, 'acme.rate_limit.cache_pool', $singleServer);
}
```

## Stable names

`RateLimitGuard::STORES_PARAMETER` is the hidden parameter every bundle writes to; a bundle never writes it by hand,
`watch()` does. `StoreLocality::LOCAL_ADAPTERS` and `StoreLocality::LOCAL_CLASSES` list what is local.
