<!-- phil-dev source-sha256=39eb49031c7e42f7923f98556e69f8130f8deca2868fc747bf54f479f8807d76 -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `symfony/dependency-injection`, `symfony/config`, `symfony/http-kernel` | ^8.2 | La classe du bundle, sa configuration, les écouteurs |
| `doctrine/orm` | facultatif | Les étiquettes invalidées à chaque flush |
| `symfony/http-client` | facultatif | La purge de Varnish ou de Cloudflare |
| `twig/twig` | facultatif | `cache_tag()` |
| `psr/log` | facultatif | Une purge refusée journalisée plutôt que lancée ; le `logger` du conteneur |
| `symfony/messenger` | facultatif | Les étiquettes recueillies dans le message d'un worker, purgées une fois qu'il est traité |

```sh
composer require phil/http-cache-tags-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\HttpCacheTagsBundle\PhilHttpCacheTagsBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_http_cache_tags.yaml
phil_http_cache_tags:
    header: xkey                 # xkey (Varnish), cache-tag (Cloudflare) or surrogate-key (Fastly)
    purger: varnish              # none, varnish or cloudflare
    varnish:
        urls: ['http://varnish']
        header: xkey-purge       # xkey-softpurge for a soft purge
    cloudflare:
        zone: '%env(CLOUDFLARE_ZONE)%'
        token: '%env(CLOUDFLARE_TOKEN)%'
    invalidate_entities: true    # tags of the entities a Doctrine flush changes
```

`purger: varnish` sans URL, ou `purger: cloudflare` sans zone ni jeton, est refusé à la construction du conteneur. Un
service `CacheTagPurger` propre à l'application, déclaré comme alias, remplace celui qui est configuré.
