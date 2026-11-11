# Extension points

What an application implements, tags, configures or relies on.

## Ports

### `CacheTagResolver`

`Phil\HttpCacheTagsBundle\Application\CacheTagResolver` — the tags of the responses that show an entity. A service
implementing it is tagged `phil_http_cache_tags.resolver` by autoconfiguration. See [Purging](purging.md).

| Method | Contract |
|---|---|
| `tagsFor(object $entity): iterable<string>` | The tags to purge when the entity changes, none for an entity it does not know; cheap, since every entity a flush changes is passed to every resolver |

### `CacheTagPurger`

`Phil\HttpCacheTagsBundle\Application\CacheTagPurger` — the cache in front. `VarnishPurger` or `CloudflarePurger`, by
`purger`; a service of the application's own, aliased to the port, replaces the configured one — a Fastly purger, a
CDN of its own.

| Method | Contract |
|---|---|
| `purge(list<string> $tags): void` | Purges every response with one of the tags; tries every target even when one fails, then throws one `PurgeFailed` naming each failure |

## Tagging

| From | How |
|---|---|
| A controller | `#[CacheTag('page:{id}', 'menu')]`, `{name}` a request attribute |
| A template | `cache_tag()` |
| Code | `ResponseTags::add()` |

See [Tagging](tagging.md).

## Configuration

| Option | What it extends |
|---|---|
| `header` | The header the tags are written in: `xkey`, `cache-tag` or `surrogate-key` |
| `purger` | Which cache is purged: none, `varnish` or `cloudflare` |
| `varnish`, `cloudflare` | Where and how |
| `invalidate_entities` | Whether every entity a Doctrine flush changes is passed to the resolvers |

## Services

| Service | How an application relies on it |
|---|---|
| `Invalidator` | `invalidate()` and `invalidateEntity()`, for tags and objects no flush carries; purged once at the end of the request, command or message |
| `ResponseTags` | The tags of the current response; public, reset between requests |

Purge failures are logged on the `http_cache` channel: a Monolog handler of the application's on that channel sends
them where it wants.
