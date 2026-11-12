<!-- phil-dev source-sha256=a7718dc7c058c326c1113bd92f5e1748965f667b6942a3aa038a62f579846c30 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\RateLimit\RateLimitGuard` | La passe de compilation ; `requireComponent()`, `watch()` et `watched()` pour les bundles qui bâtissent des limites de débit |
| `Phil\RateLimit\StoreLocality` | Si un pool de cache ou un service de stockage garde ses données sur le serveur, lu dans les définitions |

## `RateLimitGuard`

| Membre | Rôle |
|---|---|
| `STORES_PARAMETER` | `.phil_rate_limit.guarded_stores`, le paramètre caché des magasins notés, retiré par la passe |
| `__construct(StoreLocality $locality = new StoreLocality())` | La passe, avec la règle de localité |
| `requireComponent(bool $installed, string $path): void` | Lève `InvalidConfigurationException` quand le rate limiter manque |
| `watch(ContainerBuilder $container, string $store, string $path, bool $singleServer = false, ?string $switch = null): void` | Note un magasin à vérifier |
| `watched(ContainerBuilder $container): array` | Les magasins notés, par chemin, chacun avec son `store` et son `switch` |
| `process(ContainerBuilder $container): void` | Vérifie et retire les magasins notés ; lève en `prod` sur un magasin local |

## `StoreLocality`

| Membre | Rôle |
|---|---|
| `LOCAL_ADAPTERS` | Les adaptateurs abstraits du framework dont les pools sont locaux |
| `LOCAL_CLASSES` | Les classes des pools et stockages locaux |
| `MAX_DEPTH` | 32, le plus de services suivis depuis celui qui est demandé |
| `isLocal(ContainerBuilder $container, string $id): bool` | Si ce service, ou le pool qu'il reçoit, est local |
