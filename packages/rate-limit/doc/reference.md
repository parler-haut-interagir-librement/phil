# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\RateLimit\RateLimitGuard` | The compiler pass; `requireComponent()`, `watch()` and `watched()` for the bundles that build rate limits |
| `Phil\RateLimit\StoreLocality` | Whether a cache pool or a storage service keeps its data on the server, read from the definitions |

## `RateLimitGuard`

| Member | Role |
|---|---|
| `STORES_PARAMETER` | `.phil_rate_limit.guarded_stores`, the hidden parameter of the noted stores, removed by the pass |
| `__construct(StoreLocality $locality = new StoreLocality())` | The pass, with the rule of locality |
| `requireComponent(bool $installed, string $path): void` | Throws `InvalidConfigurationException` when the rate limiter is missing |
| `watch(ContainerBuilder $container, string $store, string $path, bool $singleServer = false, ?string $switch = null): void` | Notes a store to check |
| `watched(ContainerBuilder $container): array` | The noted stores, by path, each with its `store` and `switch` |
| `process(ContainerBuilder $container): void` | Checks and removes the noted stores; throws in `prod` on a local one |

## `StoreLocality`

| Member | Role |
|---|---|
| `LOCAL_ADAPTERS` | The abstract adapters of the framework whose pools are local |
| `LOCAL_CLASSES` | The classes of local pools and storages |
| `MAX_DEPTH` | 32, the most services followed from the one asked about |
| `isLocal(ContainerBuilder $container, string $id): bool` | Whether that service, or the pool it is given, is local |
