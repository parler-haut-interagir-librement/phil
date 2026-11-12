<!-- phil-dev source-sha256=4678975cfe8aa6b0c5b6c7ecf7c2170816a18d4ac561212f76a114fc489f2941 -->
# La garde

`RateLimitGuard` fait échouer la construction du conteneur sur une limite de débit qui ne limiterait rien. Elle vérifie
deux choses, à deux moments.

## Une limite sans son composant

```php
RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'acme.rate_limit');
```

Quand le premier argument est faux, elle lève `InvalidConfigurationException` sur-le-champ, pendant que le bundle charge
sa configuration :

```text
acme.rate_limit needs symfony/rate-limiter: install it, or set "enabled" to false.
```

Le bundle passe le résultat de `interface_exists()` au lieu de laisser la bibliothèque le tester, pour qu'un test puisse
demander l'un ou l'autre cas.

## Un magasin local à chaque serveur

```php
RateLimitGuard::watch($builder, 'cache.app', 'acme.rate_limit.cache_pool', singleServer: false, switch: 'enabled');
```

`watch()` note un pool de cache ou un service de stockage sous le chemin de configuration qui le nomme. Rien n'est noté
quand `singleServer` est vrai : sur un seul serveur, un magasin local compte toutes les requêtes. `switch`, quand il est
donné, est l'option voisine du chemin qui coupe la limite, proposée dans le message.

Les notes vont dans le paramètre caché `.phil_rate_limit.guarded_stores`, par chemin, pour que deux limites qui comptent
dans le même pool soient toutes deux signalées. `RateLimitGuard::watched()` les relit.

À la compilation du conteneur, la passe lit le paramètre et le retire. Hors de l'environnement `prod`, elle s'arrête
là : le pool sur système de fichiers d'un poste de développement est ce qu'il doit être. En `prod`, le premier magasin
que `StoreLocality` juge local lève :

```text
acme.rate_limit.cache_pool: the store "cache.app" is local to each server, so a client could spend the limit once per
server. Name a cache pool on a shared store — Redis, Memcached, a database —, set "single_server" to true on a single
server, or set "enabled" to false.
```

Un magasin dont le service n'est pas défini est laissé au conteneur, qui le signale à sa façon.

## Ce qui est local

`StoreLocality::isLocal()` suit les définitions, pas les objets : rien n'est construit.

| Le service | Local |
|---|---|
| Un enfant de `cache.adapter.filesystem`, `cache.adapter.apcu`, `cache.adapter.array` ou `cache.adapter.system`, directement ou par d'autres pools | Oui |
| Une définition de `FilesystemAdapter`, `PhpFilesAdapter`, `ApcuAdapter`, `ArrayAdapter` ou `InMemoryStorage`, sa classe donnée ou lue d'un paramètre | Oui |
| Un `CacheStorage` dont le premier argument, ou `$pool`, est une référence à un pool local | Oui |
| Un alias de l'un d'eux | Oui |
| Tout autre adaptateur ou stockage : Redis, Memcached, PDO, DBAL, une classe de l'application | Non |
| Une chaîne de plus de 32 services, ou qui boucle | Non, laissée au conteneur |

Le `cache.app` du framework est un enfant de `cache.adapter.filesystem` sauf si l'application configure un autre
adaptateur : un bundle qui compte dans `cache.app` par défaut est donc refusé en production tant que l'application ne
nomme pas un pool partagé. Un `ChainAdapter` est jugé partagé, quels que soient ses membres : nommez un pool sur un seul
adaptateur partagé pour une limite de débit.
