<!-- phil-dev source-sha256=ebf8b7c8b3ef668760b9cf227948909e1221007434326cde257b36c7dff815d6 -->
# Référence

| Classe | Rôle |
|---|---|
| `Phil\UsageMeterBundle\PhilUsageMeterBundle` | Le bundle : la configuration `phil_usage_meter` et ses services |
| `Phil\UsageMeterBundle\Infrastructure\DependencyInjection\UsageMeterServices` | Enregistre les services ; les noms des stockages, les identifiants de service et l'étiquette des limiteurs en constantes |
| `Phil\UsageMeterBundle\Infrastructure\Configuration\ConfiguredMeters` | Construit les `Meters` de la section `meters` ; la fabrique du service `Meters` |
| `Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand` | `phil:usage-meter:purge` |
| `Phil\UsageMeterBundle\Infrastructure\RateLimit\MeterLimiter` | Un limiteur de `phil/rate-limit-bundle` qui compte sur un compteur, une fois par `Idempotency-Key` pour un limiteur à clé ; `IDEMPOTENCY_HEADER` |

| Service | Classe |
|---|---|
| `Phil\UsageMeter\UsageMeter`, public | `UsageMeter` |
| `Phil\UsageMeter\Meters` | `Meters`, tiré de `ConfiguredMeters::fromConfiguration()` |
| `Phil\UsageMeter\UsageStore` | Un alias du stockage choisi |
| `Phil\UsageMeter\Dbal\DbalUsageStore` | Avec `store: dbal` |
| `Phil\UsageMeter\Dbal\UsageSchemaListener` | Avec `store: dbal` et `doctrine/orm` |
| `Phil\UsageMeter\InMemoryUsageStore` | Avec `store: memory` |
| `phil_usage_meter.limits` | `LimitChain`, quand un fournisseur de limites ou les fonctionnalités d'abonnement sont configurés |
| `phil_usage_meter.limits.subscription` | `FeatureLimits`, quand les fonctionnalités d'abonnement sont activées |
| `phil_usage_meter.rate_limiter.<name>` | `MeterLimiter`, avec l'étiquette `phil_rate_limit.limiter`, un par entrée de `rate_limiters` |
| `Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand` | `PurgeUsageCommand` |

| Constante | Valeur |
|---|---|
| `UsageMeterServices::STORE_DBAL` | `dbal` |
| `UsageMeterServices::STORE_MEMORY` | `memory` |
| `UsageMeterServices::LIMITS` | `phil_usage_meter.limits` |
| `UsageMeterServices::SUBSCRIPTION_LIMITS` | `phil_usage_meter.limits.subscription` |
| `UsageMeterServices::RATE_LIMITER_PREFIX` | `phil_usage_meter.rate_limiter.` |
| `UsageMeterServices::RATE_LIMITER_TAG` | `phil_rate_limit.limiter` |
| `MeterLimiter::IDEMPOTENCY_HEADER` | `Idempotency-Key` |
