# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilHttpCacheTagsBundle` | `final class`, `AbstractBundle` | Alias `phil_http_cache_tags`; `RESOLVER_TAG` |
| `Domain\ValueObject\CacheTag` | `final readonly class` | One tag |
| `Domain\Exception\InvalidCacheTag` | exception | A tag that cannot be |
| `Application\ResponseTags` | service | The tags of the response being rendered |
| `Application\Invalidator` | service | The tags to purge, purged once |
| `Application\CacheTagPurger`, `CacheTagResolver` | interfaces | A cache to purge; the tags of an entity |
| `Infrastructure\Http\CacheTag` | attribute | A controller's tags |
| `Infrastructure\Http\ResponseTagsListener` | listener | The attribute's tags; the header |
| `Infrastructure\Http\InvalidationFlushListener` | listener | The purge at the end of a request or a command |
| `Infrastructure\Doctrine\InvalidateOnFlushListener` | Doctrine listener | A flush's entities invalidated |
| `Infrastructure\Purger\VarnishPurger`, `CloudflarePurger`, `PurgeFailed` | purgers | Varnish xkey; Cloudflare's API |
| `Infrastructure\Twig\CacheTagExtension` | Twig extension | `cache_tag()` |
