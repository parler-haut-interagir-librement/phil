# Reference

| Class | Role |
|---|---|
| `Phil\UsageMeterBundle\PhilUsageMeterBundle` | The bundle: the `phil_usage_meter` configuration and its services |
| `Phil\UsageMeterBundle\Infrastructure\DependencyInjection\UsageMeterServices` | Registers the services; the store names, the service identifiers and the limiters' tag as constants |
| `Phil\UsageMeterBundle\Infrastructure\Configuration\ConfiguredMeters` | Builds the `Meters` of the `meters` section; the factory of the `Meters` service |
| `Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand` | `phil:usage-meter:purge` |
| `Phil\UsageMeterBundle\Infrastructure\RateLimit\MeterLimiter` | A limiter of `phil/rate-limit-bundle` that counts on a meter, once per `Idempotency-Key` for a keyed limiter; `IDEMPOTENCY_HEADER` |

| Service | Class |
|---|---|
| `Phil\UsageMeter\UsageMeter`, public | `UsageMeter` |
| `Phil\UsageMeter\Meters` | `Meters`, from `ConfiguredMeters::fromConfiguration()` |
| `Phil\UsageMeter\UsageStore` | An alias of the store chosen |
| `Phil\UsageMeter\Dbal\DbalUsageStore` | With `store: dbal` |
| `Phil\UsageMeter\Dbal\UsageSchemaListener` | With `store: dbal` and `doctrine/orm` |
| `Phil\UsageMeter\InMemoryUsageStore` | With `store: memory` |
| `phil_usage_meter.limits` | `LimitChain`, when a limit provider or the subscription features are configured |
| `phil_usage_meter.limits.subscription` | `FeatureLimits`, when the subscription features are on |
| `phil_usage_meter.rate_limiter.<name>` | `MeterLimiter`, tagged `phil_rate_limit.limiter`, one per entry of `rate_limiters` |
| `Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand` | `PurgeUsageCommand` |

| Constant | Value |
|---|---|
| `UsageMeterServices::STORE_DBAL` | `dbal` |
| `UsageMeterServices::STORE_MEMORY` | `memory` |
| `UsageMeterServices::LIMITS` | `phil_usage_meter.limits` |
| `UsageMeterServices::SUBSCRIPTION_LIMITS` | `phil_usage_meter.limits.subscription` |
| `UsageMeterServices::RATE_LIMITER_PREFIX` | `phil_usage_meter.rate_limiter.` |
| `UsageMeterServices::RATE_LIMITER_TAG` | `phil_rate_limit.limiter` |
| `MeterLimiter::IDEMPOTENCY_HEADER` | `Idempotency-Key` |
