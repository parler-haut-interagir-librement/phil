<!-- phil-dev source-sha256=c646158456018abe3cb3fb84a678b9e09e73f85b5ffc60dba4ef4b1379520545 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent ;
- Symfony 8.2 ou plus récent, avec FrameworkBundle ;
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

## Mode worker

Chaque service du bundle ne garde rien entre les requêtes : la décision d'une requête voyage dans un attribut de
requête, les décomptes dans le stockage. Un *worker* de longue durée sert chaque requête avec les mêmes services.
