# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `symfony/dependency-injection`, `symfony/config`, `symfony/http-kernel` | ^8.2 | The bundle class, its configuration, the listeners |
| `doctrine/orm` | optional | Tags invalidated at each flush |
| `symfony/http-client` | optional | Purging Varnish or Cloudflare |
| `twig/twig` | optional | `cache_tag()` |
| `psr/log` | optional | A refused purge logged rather than thrown; the container's `logger` |
| `symfony/messenger` | optional | Tags gathered in a worker's message purged once it is handled |

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

`purger: varnish` without URLs, or `purger: cloudflare` without a zone and a token, is refused when the container is
built. A `CacheTagPurger` service of the application's own, aliased, replaces the configured one.
