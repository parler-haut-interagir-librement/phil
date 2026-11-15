# Configuration

```yaml
phil_video:
    tracking:
        enabled: false
        endpoint: /_phil/video/progress
        consent: null          # a ViewingConsent service; nobody agreed when null
        store: memory          # memory, cache, or a ViewingStore service
        cache_pool: cache.app  # the PSR-6 pool of the cache store
    kit_subdirectory: null
```

| Key | Default | Meaning |
|---|---|---|
| `tracking.enabled` | `false` | Whether viewings are counted at all |
| `tracking.endpoint` | `/_phil/video/progress` | The path the player sends viewings to: the route of `@PhilVideoBundle/config/routes.php` |
| `tracking.consent` | `null` | The service, a `ViewingConsent`, that says whether the current viewer agreed — the application's consent manager. `NoViewingConsent` when null: nobody agreed, nothing is counted |
| `tracking.store` | `memory` | `memory`, emptied between requests, for tests; `cache`, in the pool below; or the service of a `ViewingStore` |
| `tracking.cache_pool` | `cache.app` | The PSR-6 pool of the `cache` store |
| `kit_subdirectory` | `null` | Where the recipes of the kit are under the components directory; the subdirectory of `ux_toolkit` when null |
