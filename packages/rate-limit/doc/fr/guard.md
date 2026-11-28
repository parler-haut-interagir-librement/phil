<!-- phil-dev source-sha256=ef6f3213bbf42e15d6ad051302f7cf9e90a01ea314b9714a005b650b82ad1d43 -->
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
| Une `RateLimiterFactory` dont le deuxième argument, ou `$storage`, est une référence à un stockage local | Oui |
| Un limiteur de la configuration du framework, `limiter.<nom>`, enfant de l'abstrait `limiter`, dont le stockage est local | Oui |
| Un `RateLimiterBuilder`, comme le `limiter_builder` du framework, dont le premier argument, ou `$storage`, est une référence à un stockage local | Oui |
| Un alias de l'un d'eux | Oui |
| Tout autre adaptateur ou stockage : Redis, Memcached, PDO, DBAL, une classe de l'application | Non |
| Une chaîne de plus de 32 services, ou qui boucle | Non, laissée au conteneur |

Le `cache.app` du framework est un enfant de `cache.adapter.filesystem` sauf si l'application configure un autre
adaptateur : un bundle qui compte dans `cache.app` par défaut est donc refusé en production tant que l'application ne
nomme pas un pool partagé. Un `ChainAdapter` est jugé partagé, quels que soient ses membres : nommez un pool sur un seul
adaptateur partagé pour une limite de débit.

## Les limiteurs bâtis à l'exécution

Le `RateLimiterBuilder` de Symfony crée des fabriques pendant que l'application tourne (`slidingWindow()`,
`fixedWindow()`, `tokenBucket()`, `compound()`), pour une limite qui dépend du forfait du client, par exemple. Le
conteneur ne détient jamais ces fabriques : la garde ne peut donc pas les lire. Elle lit le constructeur à la place :
chaque fabrique qu'il crée compte dans le stockage du constructeur, si bien qu'un constructeur sur un stockage partagé
donne des limiteurs partagés.

```php
RateLimitGuard::watch($builder, StoreLocality::BUILDER, 'acme.plans', singleServer: false);
```

Le framework définit `limiter_builder` sur le stockage de `rate_limiter.builder` : son `storage_service`, ou un
`CacheStorage` sur son `cache_pool`, `cache.rate_limiter` par défaut, un enfant de `cache.app`. Un bundle qui crée ses
limiteurs avec le constructeur surveille `limiter_builder` à côté de ses autres magasins.

Ce que la garde ne peut pas voir :

- un `RateLimiterBuilder` ou une `RateLimiterFactory` bâtis avec `new` dans le code de l'application, avec leur propre
  stockage ;
- un stockage choisi à l'exécution, une fois le conteneur compilé.

Un tel limiteur est vérifié par son auteur : donnez-lui un service de stockage du conteneur, et surveillez ce service.
