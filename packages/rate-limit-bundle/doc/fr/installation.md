<!-- phil-dev source-sha256=bfcb1f3e636cd24a8c4b5062bbbc833509a0a831294c81ce79c6d2ea6b2f5fe7 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent ; les extensions intl et mbstring sont suggérées, pour la vitesse de la normalisation des
  identifiants dont une clé est construite, que les polyfills de `symfony/string` font de la même façon sans elles ;
- Symfony 8.2 ou plus récent, avec FrameworkBundle et son `rate_limiter`, activé par défaut, dont l'écouteur applique
  l'attribut `#[RateLimit]` et l'argument `RateLimit` ;
- au besoin TwigBundle pour la page de refus, le composant de formulaire pour les formulaires, SecurityBundle pour les
  clés `user` et `user_or_ip`, le traducteur pour le français, symfony/lock pour des décomptes exacts sous la
  concurrence, phil/ip-anonymizer pour des clés par réseau, et un journal PSR-3 comme MonologBundle pour les
  avertissements d'un limiteur en observation ;
- phil/rate-limit, installé avec le bundle, dont la garde refuse en production un stockage propre à chaque serveur.

## Installation

```sh
composer require phil/rate-limit-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
];
```

## Le stockage

Les limiteurs gardent leur état dans un pool de cache, `cache.app` par défaut. Ce pool vit sur le disque d'un seul
serveur : pour une application sur plusieurs serveurs, ou dans des *workers* qui doivent partager leurs décomptes,
déclarez un pool partagé et nommez-le.

```yaml
framework:
    cache:
        pools:
            cache.rate_limits: { adapter: cache.adapter.redis, provider: '%env(REDIS_URL)%' }

phil_rate_limit:
    cache_pool: cache.rate_limits
```

Un limiteur nomme son propre pool avec `cache_pool`, ou un service `StorageInterface` avec `storage`. Un pool, un
stockage ou une fabrique de verrous qui n'est pas défini fait échouer la construction du conteneur, avec le limiteur
qui en a besoin, plutôt que la première requête.

## Le verrou

Un limiteur lit son état, décide et le réécrit. Sans verrou, deux requêtes d'une même clé sur deux workers lisent le
même état, et toutes deux peuvent passer une limite qui n'a de place que pour l'une d'elles. Chaque limiteur réserve
donc sous la fabrique de verrous `lock.factory` quand elle est définie — `framework.lock` activé, avec symfony/lock —,
comme FrameworkBundle le fait pour ses propres limiteurs. Son magasin est partagé par les serveurs, comme le pool :

```yaml
framework:
    lock: '%env(REDIS_URL)%'
```

`lock_factory` nomme une autre fabrique, pour le bundle ou pour un limiteur ; un nom qui n'est pas défini fait échouer
la construction. `lock_factory: null` réserve sans verrou.

## Mode worker

Chaque service du bundle ne garde rien entre les requêtes : la décision d'une requête voyage dans un attribut de
requête, les décomptes dans le stockage. Un *worker* de longue durée sert chaque requête avec les mêmes services.
