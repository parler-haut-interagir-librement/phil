# Purging

`Invalidator::invalidate('menu')` gathers tags to purge; they are purged once, each tag once, at the end of:

| Where | When |
|---|---|
| A request | `kernel.terminate`, once the response is sent |
| A command | `console.terminate` |
| A Messenger worker's message | `WorkerMessageHandledEvent` or `WorkerMessageFailedEvent`: a page an asynchronous job changes, a publication `phil/scheduled-transitions-bundle` runs from Symfony Scheduler, is purged as soon as the message is done, not when the worker stops |

## When an entity changes

A `CacheTagResolver` names the tags of the responses that show an entity; with autoconfiguration, the bundle finds
it. With `invalidate_entities`, every entity a Doctrine flush inserts, updates or deletes is passed to the resolvers.

```php
final class PageTags implements CacheTagResolver
{
    public function tagsFor(object $entity): iterable
    {
        return $entity instanceof Page ? ['page:'.$entity->id, 'page-list'] : [];
    }
}
```

`Invalidator::invalidateEntity($entity)` does the same for an object that is not flushed.

## The caches

| Purger | What it sends |
|---|---|
| `VarnishPurger` | A `PURGE` request to each Varnish, the tags in `xkey-purge`, separated by spaces, for the xkey module |
| `CloudflarePurger` | `POST /zones/{zone}/purge_cache` with `{"tags": […]}`, a hundred tags per call, with the API token |

Every Varnish is purged, and every Cloudflare call made, even when one fails. An answer of 400 or more, or a cache
that cannot be reached, is then one `PurgeFailed` that names each failure, the transport error as its previous one:
those responses stay stale until they expire. `InvalidationFlushListener` logs it as an error, on the `http_cache`
channel, with the container's `logger`, so that a response already sent or a worker keeps going; without a logger, it
is thrown.
