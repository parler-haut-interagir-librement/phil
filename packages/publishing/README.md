# phil/publishing

**The events of a page that goes online or offline: its canonical address, its language, whether it may be indexed,
and how its owner names it.**

phil/publishing holds two events and their contract. `PagePublished` says a page went online, or went online again
with new content; `PageRetired` says it went offline, taken offline or deleted. Each carries the page's canonical
address — a `Phil\Url\Url`, absolute and normalised —, its language as a BCP 47 tag, an « indexable » flag, and the
identifier the package that owns the page gives it. Both implement `Contract\PageEvent`, which a listener reads
without caring which of the two it got. **It does not** dispatch anything, store pages, build addresses or decide
what is indexable: the package that owns a page does, once the change is stored.

It is for applications where several packages follow what readers can see: a sitemap that adds and removes its
entries, an HTTP cache that purges a page, a search engine ping, a feed hub, a federation outbox. The owner of the
pages dispatches the events on whatever dispatcher the application has; each follower listens to them, and none of
them knows the others. Other events of a page's lifecycle may join these two later.

phil/publishing is a plain PHP library on `phil/url`, with no dependency on a framework. Its events are plain
immutable objects, dispatched through a PSR-14 dispatcher such as Symfony's EventDispatcher.

## Status

| Part | State |
|---|---|
| `PagePublished` and `PageRetired`, with their checks | Implemented and tested |
| `Contract\PageEvent` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [The events](doc/events.md) | The four fields, their checks, when an owner dispatches each event, and how a listener reads them. |
| [Extension points](doc/extension-points.md) | What an owner and a listener rely on, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/publishing
```

The owner of a page dispatches, once the page is stored:

```php
use Phil\Publishing\PagePublished;
use Phil\Url\Url;

$dispatcher->dispatch(new PagePublished(
    Url::fromString('https://example.org/fr/news/opening'),
    'fr',
    indexable: true,
    ownerId: 'news:42',
));
```

A follower listens, here with Symfony's EventDispatcher:

```php
use Phil\Publishing\PageRetired;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PurgeRetiredPage
{
    public function __invoke(PageRetired $event): void
    {
        $this->cache->purge($event->canonicalUrl->toString());
    }
}
```

## Requirements

PHP 8.5 or later, and `phil/url`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
