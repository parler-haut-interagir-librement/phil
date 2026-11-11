<!-- phil-dev source-sha256=20dd175bacc2b366eea83faffab5c338170fa43099baa0c6b40f0d6767a1c315 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilHttpCacheTagsBundle` | `final class`, `AbstractBundle` | Alias `phil_http_cache_tags` ; `RESOLVER_TAG` |
| `Domain\ValueObject\CacheTag` | `final readonly class` | Une étiquette |
| `Domain\Exception\InvalidCacheTag` | exception | Une étiquette impossible |
| `Application\ResponseTags` | service | Les étiquettes de la réponse en cours de rendu |
| `Application\Invalidator` | service | Les étiquettes à purger, purgées une seule fois |
| `Application\CacheTagPurger`, `CacheTagResolver` | interfaces | Un cache à purger ; les étiquettes d'une entité |
| `Infrastructure\Http\CacheTag` | attribut | Les étiquettes d'un contrôleur |
| `Infrastructure\Http\ResponseTagsListener` | écouteur | Les étiquettes de l'attribut ; l'en-tête |
| `Infrastructure\Http\InvalidationFlushListener` | écouteur | La purge à la fin d'une requête ou d'une commande |
| `Infrastructure\Doctrine\InvalidateOnFlushListener` | écouteur Doctrine | Les entités d'un flush invalidées |
| `Infrastructure\Purger\VarnishPurger`, `CloudflarePurger`, `PurgeFailed` | purgeurs | Varnish xkey ; l'API de Cloudflare |
| `Infrastructure\Twig\CacheTagExtension` | extension Twig | `cache_tag()` |
